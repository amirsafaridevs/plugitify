/**
 * Drives the preview iframe on behalf of the agent.
 *
 * The iframe normally shows a page from the same WordPress site as the chat, so
 * it is same-origin and its document is fully readable. The address bar lets
 * the user (or the agent) point it anywhere, though, so every accessor goes
 * through doc()/win(), which turn the resulting SecurityError into an
 * explanation the model can act on.
 */

export interface PageError {
  kind: 'error' | 'rejection' | 'console' | 'resource';
  text: string;
  at: string;
}

const MAX_ERRORS = 200;

export class Preview {
  private iframe: HTMLIFrameElement;
  private urlInput: HTMLInputElement | null;
  private errors: PageError[] = [];
  /** Documents already instrumented, so a re-hook doesn't double-wrap console. */
  private hooked = new WeakSet<Document>();
  private onUrlChange: ((url: string) => void) | null;

  constructor(onUrlChange?: (url: string) => void) {
    const iframe = document.getElementById('pi-chat-iframe');

    if (!(iframe instanceof HTMLIFrameElement)) {
      throw new Error('Preview iframe (#pi-chat-iframe) is missing from the page.');
    }

    this.iframe = iframe;
    this.urlInput = document.getElementById('pi-browser-url') as HTMLInputElement | null;
    this.onUrlChange = onUrlChange ?? null;

    this.iframe.addEventListener('load', () => {
      this.syncUrlBar();
      this.instrument();
    });

    this.instrument();
  }

  // ── Access ──────────────────────────────────────────────────────────

  win(): Window {
    const win = this.iframe.contentWindow;

    if (!win) {
      throw new Error('The preview frame has no window yet. Navigate it first with browser_navigate.');
    }

    return win;
  }

  doc(): Document {
    let doc: Document | null = null;

    try {
      doc = this.iframe.contentDocument;
    } catch {
      doc = null;
    }

    if (!doc) {
      throw new Error(
        `The preview is showing ${this.currentUrl()}, which is on a different origin, so its content `
        + 'cannot be read. Navigate it back to a page on this WordPress site first.',
      );
    }

    return doc;
  }

  currentUrl(): string {
    try {
      const href = this.iframe.contentWindow?.location.href;
      if (href && href !== 'about:blank') {
        return href;
      }
    } catch {
      // Cross-origin: fall back to whatever we last set.
    }

    return this.iframe.getAttribute('src') || 'about:blank';
  }

  // ── Navigation ──────────────────────────────────────────────────────

  /**
   * Points the frame at a URL and resolves once it has loaded.
   *
   * Resolving on timeout rather than rejecting is deliberate: a page that is
   * slow, or that never fires load because of a hanging subresource, is still
   * usually inspectable, and the agent is better served by a note than by a
   * failed tool call.
   */
  async navigate(url: string, timeoutMs = 30000): Promise<{ timedOut: boolean }> {
    this.errors = [];

    return new Promise((resolve) => {
      let settled = false;

      const finish = (timedOut: boolean) => {
        if (settled) {
          return;
        }
        settled = true;
        window.clearTimeout(timer);
        this.iframe.removeEventListener('load', onLoad);
        this.syncUrlBar();
        resolve({ timedOut });
      };

      const onLoad = () => finish(false);
      const timer = window.setTimeout(() => finish(true), timeoutMs);

      this.iframe.addEventListener('load', onLoad);
      this.iframe.src = url;

      // Instrument as soon as the new document exists, which is well before
      // `load` fires — otherwise every error thrown during page startup would
      // be missed.
      this.instrumentEarly();
    });
  }

  /** Waits out whatever a click kicked off: a navigation, or just handlers running. */
  async settle(ms = 600): Promise<void> {
    await new Promise((resolve) => window.setTimeout(resolve, ms));
    this.instrument();
  }

  private syncUrlBar(): void {
    const url = this.currentUrl();

    if (this.urlInput) {
      this.urlInput.value = url;
    }

    if (url && url !== 'about:blank') {
      this.onUrlChange?.(url);
    }
  }

  // ── Error capture ───────────────────────────────────────────────────

  /**
   * Polls briefly for the freshly-created document so handlers are attached
   * while it is still parsing, rather than after its scripts have already run.
   */
  private instrumentEarly(): void {
    let attempts = 0;

    const tick = () => {
      attempts++;

      if (this.instrument() || attempts > 120) {
        return;
      }

      window.setTimeout(tick, 10);
    };

    tick();
  }

  /** @return true once the current document has been instrumented. */
  private instrument(): boolean {
    let doc: Document | null = null;
    let win: Window | null = null;

    try {
      doc = this.iframe.contentDocument;
      win = this.iframe.contentWindow;
    } catch {
      return false;
    }

    if (!doc || !win || this.hooked.has(doc)) {
      return Boolean(doc && this.hooked.has(doc));
    }

    this.hooked.add(doc);

    win.addEventListener('error', (event: ErrorEvent | Event) => {
      const target = event.target as HTMLElement | null;

      // A failed <script>/<link>/<img> fires a non-cancelable error event whose
      // target is the element, not a thrown exception.
      if (target && target !== (win as unknown as EventTarget) && 'tagName' in target) {
        const src = (target as HTMLScriptElement).src || (target as HTMLLinkElement).href || '';
        this.record('resource', `${target.tagName.toLowerCase()} failed to load: ${src}`);
        return;
      }

      const e = event as ErrorEvent;
      this.record('error', `${e.message} (${e.filename ?? '?'}:${e.lineno ?? 0})`);
    }, true);

    win.addEventListener('unhandledrejection', (event: PromiseRejectionEvent) => {
      this.record('rejection', String(event.reason));
    });

    // `console` lives on the global scope rather than the DOM Window
    // interface, so reach it through the globalThis shape.
    const scope = win as Window & typeof globalThis;

    for (const level of ['error', 'warn'] as const) {
      const original = scope.console[level].bind(scope.console);
      scope.console[level] = (...args: unknown[]) => {
        this.record('console', `console.${level}: ${args.map(describe).join(' ')}`);
        original(...args);
      };
    }

    return true;
  }

  private record(kind: PageError['kind'], text: string): void {
    this.errors.push({ kind, text, at: new Date().toLocaleTimeString() });

    if (this.errors.length > MAX_ERRORS) {
      this.errors.shift();
    }
  }

  takeErrors(clear: boolean): PageError[] {
    const snapshot = [...this.errors];

    if (clear) {
      this.errors = [];
    }

    return snapshot;
  }
}

function describe(value: unknown): string {
  if (typeof value === 'string') {
    return value;
  }

  if (value instanceof Error) {
    return `${value.name}: ${value.message}`;
  }

  try {
    return JSON.stringify(value) ?? String(value);
  } catch {
    return String(value);
  }
}
