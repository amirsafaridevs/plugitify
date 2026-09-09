import { run, user, type AgentInputItem } from '@openai/agents';
import { buildAgent, configureProvider } from './agent';
import { loadConfig } from './config';
import * as bus from './bus';
import { describeError } from './errors';
import { isEmptyHistory, sanitizeHistory } from './history';
import { Preview } from './preview';
import { replayHistory } from './restore';
import { setRunSignal } from './runControl';
import { clearChat, loadChat, loadPreviewUrl, saveChat, savePreviewUrl, type ToolMetaEntry } from './storage';
import { Transcript } from './ui';

/**
 * Entry point for the chat page.
 *
 * There is exactly one conversation per plugin. Its state lives in
 * localStorage as the same history array we hand the model, so a reload
 * redraws the transcript and carries straight on.
 */

/** A coding task is many read/edit/verify turns; the SDK default of 10 is far too low. */
const MAX_TURNS = 80;

/** How many times a failed run is retried before the user is told. */
const MAX_ATTEMPTS = 3;

function main(): void {
  const messagesEl = document.getElementById('pi-chat-messages');
  const noticesEl = document.getElementById('pi-chat-notices');
  const textarea = document.getElementById('pi-chat-textarea') as HTMLTextAreaElement | null;
  const sendBtn = document.getElementById('pi-chat-send') as HTMLButtonElement | null;
  const newChatBtn = document.getElementById('pi-chat-new');

  if (!messagesEl || !textarea || !sendBtn) {
    return;
  }

  const transcript = new Transcript(messagesEl, noticesEl);

  let config: ReturnType<typeof loadConfig>;
  let agent: ReturnType<typeof buildAgent>;
  let preview: Preview;
  /** Gate: don't write preview URL until after restore, or home clobbers storage. */
  let previewPersist = false;

  try {
    config = loadConfig();
    preview = new Preview((url) => {
      if (previewPersist) {
        savePreviewUrl(config.slug, url);
      }
    });
    configureProvider(config);
    agent = buildAgent(config, preview);
  } catch (error) {
    transcript.addNotice(
      error instanceof Error ? error.message : String(error),
      'error',
    );
    textarea.disabled = true;
    sendBtn.disabled = true;
    return;
  }

  let history: AgentInputItem[] = [];
  let toolMeta: Record<string, ToolMetaEntry> = {};
  let running = false;
  let controller: AbortController | null = null;

  // The sidecar that lets a restored transcript show result counts rather than
  // just raw tool output.
  bus.on('tool:start', (event) => transcript.startTool(event));
  bus.on('tool:end', (event) => {
    transcript.endTool(event);

    if (event.callId) {
      toolMeta[event.callId] = {
        durationMs: event.durationMs,
        ok: event.result.ok,
        meta: event.result.meta,
      };
    }
  });

  const persist = () => {
    const url = preview.currentUrl();
    savePreviewUrl(config.slug, url);

    const outcome = saveChat(config.slug, {
      previewUrl: url,
      history,
      toolMeta,
    });

    if (!outcome.ok) {
      transcript.addNotice('ذخیره‌ی گفتگو در حافظه‌ی مرورگر ممکن نشد.', 'warn');
    } else if (outcome.dropped > 0) {
      transcript.addNotice(
        `گفتگو طولانی شد؛ ${outcome.dropped} مورد از ابتدای آن برای جا شدن در حافظه حذف شد.`,
        'warn',
      );
    }
  };

  const browserPane = document.getElementById('pi-chat-browser');
  const browserLock = document.getElementById('pi-browser-lock');
  const browserToolbar = browserPane?.querySelector('.pi-browser-toolbar') as HTMLElement | null;
  const browserFrame = browserPane?.querySelector('.pi-browser-frame-wrap') as HTMLElement | null;

  const setBrowserLocked = (locked: boolean) => {
    browserPane?.classList.toggle('is-busy', locked);

    if (browserLock) {
      browserLock.hidden = !locked;
      browserLock.setAttribute('aria-hidden', locked ? 'false' : 'true');
    }

    // Keep focus out of the preview while the agent is driving it.
    if (browserToolbar) {
      if (locked) {
        browserToolbar.setAttribute('inert', '');
      } else {
        browserToolbar.removeAttribute('inert');
      }
    }

    if (browserFrame) {
      if (locked) {
        browserFrame.setAttribute('inert', '');
      } else {
        browserFrame.removeAttribute('inert');
      }
    }
  };

  const setRunning = (value: boolean) => {
    running = value;
    sendBtn.classList.toggle('pi-chat-send-btn--stop', value);
    sendBtn.title = value ? 'توقف' : 'ارسال';
    sendBtn.setAttribute('aria-label', value ? 'توقف' : 'ارسال');
    textarea.placeholder = value ? 'در حال کار…' : 'پیام خود را بنویسید...';
    setBrowserLocked(value);

    if (value) {
      transcript.showWorking();
    } else {
      transcript.hideWorking();
    }
  };

  const send = async (text: string): Promise<void> => {
    transcript.addUserMessage(text);
    setRunning(true);

    controller = new AbortController();
    setRunSignal(controller.signal);

    // The user's turn joins the conversation immediately, so it survives even
    // if the very first attempt dies.
    history = [...history, user(text)];

    try {
      for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
        // Resume from whatever is on the record: work already completed in a
        // failed attempt is kept rather than redone.
        const input = sanitizeHistory(history);
        let stream: Awaited<ReturnType<typeof run<typeof agent, true>>> | null = null;

        try {
          stream = await run(agent, input, {
            stream: true,
            maxTurns: MAX_TURNS,
            signal: controller.signal,
          });

          for await (const event of stream) {
            if (event.type === 'raw_model_stream_event') {
              if (event.data.type === 'output_text_delta') {
                transcript.appendAssistantDelta(event.data.delta);
              }
              continue;
            }

            if (event.type !== 'run_item_stream_event') {
              continue;
            }

            if (event.name === 'message_output_created') {
              transcript.finalizeAssistantMessage(extractMessageText(event.item));
              continue;
            }

            if (event.name === 'reasoning_item_created') {
              transcript.addReasoning(extractReasoningText(event.item));
            }

            // tool_called / tool_output are drawn from the bus instead, where
            // the arguments, structured meta, and timing arrive together.
          }

          await stream.completed;

          history = stream.history;
          transcript.finalizeAssistantMessage();

          // Aborting mid-stream usually ends the iterator cleanly rather than
          // throwing, so the stop has to be reported from the success path too
          // — otherwise a cancelled run is indistinguishable from a finished one.
          if (controller.signal.aborted) {
            transcript.cancelPendingTools();
            transcript.addNotice('اجرا متوقف شد. وضعیت تا همین‌جا ذخیره شد.', 'warn');
          }

          return;
        } catch (error) {
          history = salvageHistory(stream, history);
          transcript.cancelPendingTools();
          transcript.finalizeAssistantMessage();

          if (controller.signal.aborted) {
            transcript.addNotice('اجرا متوقف شد. وضعیت تا همین‌جا ذخیره شد.', 'warn');

            return;
          }

          const described = describeError(error, MAX_TURNS);

          if (!described.retryable || attempt === MAX_ATTEMPTS) {
            transcript.addNotice(
              described.retryable
                ? `${described.summary} پس از ${MAX_ATTEMPTS} تلاش موفق نشدیم.`
                : described.summary,
              'error',
              described.detail,
            );

            return;
          }

          transcript.addNotice(
            `${described.summary} تلاش دوباره (${attempt + 1} از ${MAX_ATTEMPTS})…`,
            'warn',
          );

          await delay(backoffMs(attempt), controller.signal);

          if (controller.signal.aborted) {
            transcript.addNotice('اجرا متوقف شد. وضعیت تا همین‌جا ذخیره شد.', 'warn');

            return;
          }
        }
      }
    } finally {
      history = sanitizeHistory(history);
      persist();
      setRunSignal(null);
      controller = null;
      setRunning(false);
      textarea.focus();
    }
  };

  const autoResize = () => {
    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
  };

  const submit = () => {
    if (running) {
      controller?.abort();
      return;
    }

    const value = textarea.value.trim();
    if (!value) {
      return;
    }

    textarea.value = '';
    autoResize();
    void send(value);
  };

  sendBtn.addEventListener('click', submit);

  textarea.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      submit();
    }
  });

  textarea.addEventListener('input', autoResize);

  newChatBtn?.addEventListener('click', () => {
    if (running) {
      controller?.abort();
    }

    history = [];
    toolMeta = {};
    clearChat(config.slug);
    transcript.clear();
    textarea.focus();
  });

  // Restore the conversation this browser left behind, if there is one.
  // Silently: the transcript reappearing is itself the feedback, and a banner
  // on every page load is noise.
  const saved = loadChat(config.slug);

  if (saved && !isEmptyHistory(saved.history)) {
    history = sanitizeHistory(saved.history);
    toolMeta = saved.toolMeta;
    replayHistory(transcript, history, toolMeta);
  }

  // Last preview URL wins: dedicated key, then chat sidecar, then site home.
  const restoredUrl =
    loadPreviewUrl(config.slug)
    || (saved?.previewUrl && saved.previewUrl !== 'about:blank' ? saved.previewUrl : '')
    || config.siteUrl
    || config.previewUrl;

  if (restoredUrl && !samePreviewUrl(preview.currentUrl(), restoredUrl)) {
    void preview.navigate(restoredUrl).finally(() => {
      previewPersist = true;
      savePreviewUrl(config.slug, preview.currentUrl());
    });
  } else {
    previewPersist = true;
    if (restoredUrl) {
      savePreviewUrl(config.slug, restoredUrl);
    }
  }

  textarea.focus();
}

/** Loose compare so trailing-slash / encoding differences don't force a reload. */
function samePreviewUrl(a: string, b: string): boolean {
  try {
    const left = new URL(a);
    const right = new URL(b);
    const norm = (u: URL) => `${u.origin}${u.pathname.replace(/\/$/, '') || ''}${u.search}${u.hash}`;
    return norm(left) === norm(right);
  } catch {
    return a === b;
  }
}

/**
 * After a failed or cancelled run, keep the most complete history available.
 * The stream knows about tool calls that finished before things went wrong;
 * falling back to what we already had loses that work.
 */
function salvageHistory(
  stream: { history?: AgentInputItem[] } | null,
  fallback: AgentInputItem[],
): AgentInputItem[] {
  try {
    const salvaged = stream?.history;

    if (Array.isArray(salvaged) && salvaged.length >= fallback.length) {
      return salvaged;
    }
  } catch {
    // The getter can throw on a torn-down stream; the fallback is still good.
  }

  return fallback;
}

/** Backs off 1s, then 3s, so a brief blip doesn't cost the user a long wait. */
function backoffMs(attempt: number): number {
  return attempt === 1 ? 1000 : 3000;
}

function delay(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise((resolve) => {
    const timer = window.setTimeout(finish, ms);

    function finish() {
      window.clearTimeout(timer);
      signal.removeEventListener('abort', finish);
      resolve();
    }

    signal.addEventListener('abort', finish, { once: true });
  });
}

/** RunItem shapes differ per provider; pull text out defensively. */
function extractMessageText(item: unknown): string {
  const raw = (item as { rawItem?: { content?: unknown } })?.rawItem;

  if (!raw || !Array.isArray(raw.content)) {
    return '';
  }

  return raw.content
    .map((part: unknown) => {
      const p = part as { type?: string; text?: string };
      return p?.type === 'output_text' || p?.type === 'text' ? p.text ?? '' : '';
    })
    .join('');
}

/**
 * Reasoning arrives as a summary in `content` and, on providers that expose it,
 * the fuller text in `rawContent`. Prefer whichever is present.
 */
function extractReasoningText(item: unknown): string {
  const raw = (item as { rawItem?: { content?: unknown; rawContent?: unknown } })?.rawItem;

  if (!raw) {
    return '';
  }

  const collect = (parts: unknown): string =>
    Array.isArray(parts)
      ? parts.map((part) => (part as { text?: string })?.text ?? '').join('\n').trim()
      : '';

  return collect(raw.rawContent) || collect(raw.content);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', main);
} else {
  main();
}
