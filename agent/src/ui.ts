import { renderMarkdown, escapeHtml } from './markdown';
import type { ToolEndEvent, ToolStartEvent } from './bus';

/**
 * Renders the conversation transcript: user turns, streamed assistant text,
 * reasoning, and a live card per tool call.
 *
 * The transcript is append-only. Every method is safe to call while replaying a
 * restored conversation, which is how a reloaded page redraws itself.
 */
export type NoticeKind = 'error' | 'warn' | 'success';

/** How many toast notices to keep above the composer. */
const MAX_NOTICES = 3;

export class Transcript {
  private root: HTMLElement;
  private noticesHost: HTMLElement | null;
  private toolCards = new Map<string, HTMLElement>();
  private streamingBubble: HTMLElement | null = null;
  private streamingText = '';

  constructor(root: HTMLElement, noticesHost: HTMLElement | null = null) {
    this.root = root;
    this.noticesHost = noticesHost;
  }

  clear(): void {
    // Keep the empty-state placeholder; wipe everything else.
    for (const child of Array.from(this.root.children)) {
      if (!child.classList.contains('pi-chat-empty')) {
        child.remove();
      }
    }
    if (this.noticesHost) {
      this.noticesHost.innerHTML = '';
    }
    this.toolCards.clear();
    this.streamingBubble = null;
    this.streamingText = '';
  }

  get isEmpty(): boolean {
    return !this.root.querySelector('.pi-msg, .pi-tool, .pi-think');
  }

  addUserMessage(text: string): void {
    this.settleOpenSteps();
    const el = document.createElement('div');
    el.className = 'pi-msg pi-msg--user';
    el.textContent = text;
    this.append(el);
  }

  /** Adds a token to the in-flight assistant bubble, creating it on first call. */
  appendAssistantDelta(delta: string): void {
    if (!this.streamingBubble) {
      this.streamingBubble = document.createElement('div');
      this.streamingBubble.className = 'pi-msg pi-msg--assistant pi-msg--streaming';
      this.streamingText = '';
      this.append(this.streamingBubble);
    }

    this.streamingText += delta;
    this.streamingBubble.innerHTML = renderMarkdown(this.streamingText);
    this.scroll();
  }

  /**
   * Seals the streaming bubble. The final text from the run item is preferred
   * over the accumulated deltas, which can be missing pieces if the provider
   * batched them. Also used to render a whole message during replay.
   */
  finalizeAssistantMessage(finalText?: string): void {
    const text = finalText ?? this.streamingText;
    this.settleOpenSteps();

    if (!this.streamingBubble) {
      if (text.trim()) {
        const el = document.createElement('div');
        el.className = 'pi-msg pi-msg--assistant';
        el.innerHTML = renderMarkdown(text);
        this.append(el);
      }
      return;
    }

    if (text.trim()) {
      this.streamingBubble.innerHTML = renderMarkdown(text);
      this.streamingBubble.classList.remove('pi-msg--streaming');
    } else {
      this.streamingBubble.remove();
    }

    this.streamingBubble = null;
    this.streamingText = '';
  }

  addReasoning(text: string): void {
    if (!text.trim()) {
      return;
    }

    this.closeStream();
    this.settleOpenSteps();

    const el = document.createElement('details');
    el.className = 'pi-think pi-think--running';
    el.innerHTML =
      '<summary>'
      + '<span class="pi-tl-dot" aria-hidden="true"></span>'
      + '<span class="pi-think-label">در حال فکر کردن</span>'
      + '</summary>'
      + `<div class="pi-think-body">${renderMarkdown(text)}</div>`;
    this.appendStep(el);
  }

  /** Draws a pending card the moment a tool starts, so latency is visible. */
  startTool(event: ToolStartEvent): void {
    this.closeStream();
    this.settleOpenSteps();

    const card = document.createElement('details');
    card.className = 'pi-tool pi-tool--running';
    card.innerHTML =
      '<summary>'
      + '<span class="pi-tl-dot" aria-hidden="true"></span>'
      + `<span class="pi-tool-name">${escapeHtml(event.tool)}</span>`
      + `<span class="pi-tool-summary">${escapeHtml(summarizeArgs(event.tool, event.args))}</span>`
      + '</summary>'
      + `<div class="pi-tool-body"><pre class="pi-code"><code>${escapeHtml(formatArgs(event.args))}</code></pre></div>`;

    this.toolCards.set(event.id, card);
    this.appendStep(card);
  }

  endTool(event: ToolEndEvent): void {
    const card = this.toolCards.get(event.id);
    if (!card) {
      return;
    }

    this.toolCards.delete(event.id);
    card.classList.remove('pi-tool--running');
    card.classList.add(event.result.ok ? 'pi-tool--ok' : 'pi-tool--error');

    const summary = card.querySelector('.pi-tool-summary');
    if (summary) {
      summary.textContent = summarizeResult(event);
    }

    const body = card.querySelector('.pi-tool-body');
    if (body) {
      body.innerHTML =
        '<div class="pi-tool-label">آرگومان‌ها</div>'
        + `<pre class="pi-code"><code>${escapeHtml(formatArgs(event.args))}</code></pre>`
        + '<div class="pi-tool-label">نتیجه</div>'
        + `<pre class="pi-code"><code>${escapeHtml(truncate(event.result.output, 4000))}</code></pre>`;
    }

    // A failed tool call is the one thing worth opening automatically — the
    // user should not have to hunt for why the agent changed course.
    if (!event.result.ok) {
      card.setAttribute('open', '');
    }

    this.scroll();
  }

  /**
   * Toast above the composer. `detail` (raw provider text) stays in a
   * disclosure so the bar stays calm without hiding anything useful.
   */
  addNotice(text: string, kind: NoticeKind = 'error', detail?: string): void {
    this.closeStream();

    const host = this.noticesHost ?? this.root;
    const icon = noticeIcon(kind);

    let el: HTMLElement;

    if (!detail) {
      el = document.createElement('div');
      el.className = `pi-notice pi-notice--${kind}`;
      el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
      el.innerHTML =
        `<span class="pi-notice__icon" aria-hidden="true">${icon}</span>`
        + `<span class="pi-notice__text">${escapeHtml(text)}</span>`;
    } else {
      el = document.createElement('details');
      el.className = `pi-notice pi-notice--${kind} pi-notice--expandable`;
      el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
      el.innerHTML =
        '<summary>'
        + `<span class="pi-notice__icon" aria-hidden="true">${icon}</span>`
        + `<span class="pi-notice__text">${escapeHtml(text)}</span>`
        + '</summary>'
        + `<pre class="pi-code"><code>${escapeHtml(truncate(detail, 2000))}</code></pre>`;
    }

    host.appendChild(el);

    while (host.childElementCount > MAX_NOTICES) {
      host.firstElementChild?.remove();
    }
  }

  /** Marks any still-running tool cards as interrupted after an aborted run. */
  cancelPendingTools(): void {
    for (const card of this.toolCards.values()) {
      card.classList.remove('pi-tool--running');
      card.classList.add('pi-tool--error');
      const summary = card.querySelector('.pi-tool-summary');
      if (summary && !summary.textContent) {
        summary.textContent = 'متوقف شد';
      }
    }
    this.toolCards.clear();
    this.settleOpenSteps();
  }

  /** Marks open timeline steps done — call after a run or history replay ends. */
  settleOpenSteps(): void {
    for (const el of Array.from(this.root.querySelectorAll('.pi-think--running'))) {
      el.classList.remove('pi-think--running');
      el.classList.add('pi-think--done');
      const label = el.querySelector('.pi-think-label');
      if (label) {
        label.textContent = 'فکر کردن';
      }
    }
  }

  scrollToEnd(): void {
    this.root.scrollTop = this.root.scrollHeight;
  }

  private closeStream(): void {
    if (this.streamingBubble) {
      this.finalizeAssistantMessage();
    }
  }

  /** Appends a think/tool step into the current timeline group. */
  private appendStep(el: HTMLElement): void {
    const timeline = this.ensureTimeline();
    timeline.appendChild(el);
    this.scroll();
  }

  private ensureTimeline(): HTMLElement {
    const last = this.root.lastElementChild;
    if (last?.classList.contains('pi-timeline')) {
      return last as HTMLElement;
    }

    const wrap = document.createElement('div');
    wrap.className = 'pi-timeline';
    this.root.appendChild(wrap);
    return wrap;
  }

  private append(el: HTMLElement): void {
    this.root.appendChild(el);
    this.scroll();
  }

  private scroll(): void {
    // Don't yank the view back down if the user has scrolled up to read.
    const distanceFromBottom = this.root.scrollHeight - this.root.scrollTop - this.root.clientHeight;
    if (distanceFromBottom < 160) {
      this.root.scrollTop = this.root.scrollHeight;
    }
  }
}

/** The one-line "what is this call doing" label shown before the result lands. */
function summarizeArgs(tool: string, args: Record<string, unknown>): string {
  const path = typeof args.path === 'string' ? args.path : '';

  switch (tool) {
    case 'read_file':
    case 'write_file':
    case 'edit_file':
    case 'delete_file':
    case 'create_directory':
    case 'delete_directory':
    case 'php_lint':
    case 'list_files':
      return path || '.';
    case 'move_path':
      return `${args.from ?? ''} → ${args.to ?? ''}`;
    case 'search_files':
    case 'glob_files':
      return String(args.pattern ?? '');
    case 'plugin_control':
    case 'debug_control':
      return String(args.action ?? 'status');
    case 'read_debug_log':
      return args.clear ? 'clear' : `${args.lines ?? 200} lines`;
    case 'browser_navigate':
      return String(args.url ?? '');
    case 'browser_read_page':
      return `${args.selector || 'body'} (${args.format ?? 'text'})`;
    case 'browser_query':
    case 'browser_fill':
      return String(args.selector ?? '');
    case 'browser_click':
      return args.selector ? String(args.selector) : `(${args.x ?? '?'}, ${args.y ?? '?'})`;
    case 'browser_assets':
      return String(args.type ?? 'all');
    case 'browser_console':
      return args.clear ? 'clear' : '';
    default:
      return '';
  }
}

/** Prefers a concrete count from `meta` over the generic argument label. */
function summarizeResult(event: ToolEndEvent): string {
  const { tool, args, result } = event;
  const meta = result.meta;

  if (!result.ok) {
    return truncate(result.output.replace(/^Error(\s*\[[^\]]+\])?:\s*/, ''), 90);
  }

  // A replayed conversation has no sidecar meta for older calls; fall back to
  // the argument label rather than printing "undefined".
  const hasMeta = Object.keys(meta).length > 0;

  switch (tool) {
    case 'read_file':
      return hasMeta ? `${meta.path ?? args.path} · ${meta.from ?? 1}-${meta.to ?? '?'} از ${meta.total_lines ?? '?'} خط` : String(args.path ?? '');
    case 'write_file':
      return hasMeta ? `${meta.path ?? args.path} · ${meta.lines ?? '?'} خط` : String(args.path ?? '');
    case 'edit_file':
      return hasMeta ? `${meta.path ?? args.path} · ${meta.replacements ?? 1} جایگزینی` : String(args.path ?? '');
    case 'search_files':
      return hasMeta ? `${meta.matches ?? 0} نتیجه در ${meta.files ?? 0} فایل` : String(args.pattern ?? '');
    case 'glob_files':
      return hasMeta ? `${meta.count ?? 0} فایل` : String(args.pattern ?? '');
    case 'list_files':
      return hasMeta ? `${meta.entries ?? 0} مورد` : String(args.path ?? '.');
    case 'php_lint':
      if (!hasMeta) {
        return String(args.path ?? '');
      }
      return meta.available === false ? 'در دسترس نیست' : `${meta.checked ?? 0} فایل · ${meta.errors ?? 0} خطا`;
    case 'browser_navigate':
      return `${meta.url ?? args.url}${meta.errors ? ` · ${meta.errors} خطای کنسول` : ''}`;
    case 'browser_read_page':
      return hasMeta ? `${meta.selector ?? 'body'} · ${meta.chars ?? 0} کاراکتر` : String(args.selector || 'body');
    case 'browser_query':
      return hasMeta ? `${meta.count ?? 0} المان · ${meta.selector ?? ''}` : String(args.selector ?? '');
    case 'browser_click':
      return hasMeta ? `${meta.clicked ?? ''}${meta.navigated ? ' · صفحه عوض شد' : ''}` : summarizeArgs(tool, args);
    case 'browser_assets':
      return hasMeta ? `${meta.total ?? 0} فایل · ${meta.failed ?? 0} ناموفق` : String(args.type ?? 'all');
    case 'browser_console':
      return hasMeta ? `${meta.count ?? 0} پیام` : '';
    default:
      return summarizeArgs(tool, args) || truncate(result.output, 80);
  }
}

function formatArgs(args: Record<string, unknown>): string {
  const entries = Object.entries(args).filter(([, value]) => value !== '' && value !== undefined);

  if (!entries.length) {
    return '{}';
  }

  return entries
    .map(([key, value]) => {
      const text = typeof value === 'string' ? value : JSON.stringify(value);
      return `${key}: ${truncate(text, 2000)}`;
    })
    .join('\n');
}

function truncate(text: string, max: number): string {
  return text.length <= max ? text : `${text.slice(0, max)}\n… (${text.length - max} کاراکتر دیگر)`;
}

function noticeIcon(kind: NoticeKind): string {
  // Compact filled glyphs sit on the colored badge circle from CSS.
  if (kind === 'success') {
    return '<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M1.5 5.2 3.8 7.5 8.5 2.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  }

  if (kind === 'warn') {
    return '<svg viewBox="0 0 10 10" fill="currentColor" aria-hidden="true"><rect x="4.25" y="2" width="1.5" height="4.2" rx="0.75"/><circle cx="5" cy="8" r="0.95"/></svg>';
  }

  return '<svg viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M2.5 2.5 7.5 7.5M7.5 2.5 2.5 7.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
}
