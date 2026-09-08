import { z } from 'zod';
import { defineTool, truncate } from './toolKit';
import { Preview } from './preview';

/**
 * Tools for driving the preview pane.
 *
 * These run entirely in this tab — no server round trip — because the preview
 * is same-origin with the chat page. They are what turns the agent from
 * "writes code" into "writes code and checks whether it actually worked".
 */
export function buildBrowserTools(preview: Preview) {
  return [
    defineTool(
      'browser_navigate',
      'Point the preview pane at a URL and wait for it to load. Use it to open the page your '
        + 'plugin affects, to reload after editing files (navigate to the same URL), or to visit an '
        + 'admin screen your plugin adds. Reloading is how you see your changes take effect.',
      z.object({
        url: z
          .string()
          .describe('Absolute URL, or a site-relative path starting with "/". Use "reload" to reload the current page.'),
      }),
      async (args) => {
        const raw = String(args.url ?? '').trim();

        if (!raw) {
          return { ok: false, output: 'Error: browser_navigate requires a "url".', meta: {} };
        }

        const target = raw === 'reload' ? preview.currentUrl() : normalizeUrl(raw);
        const { timedOut } = await preview.navigate(target);

        const url = preview.currentUrl();
        let title = '';
        let note = '';

        try {
          title = preview.doc().title;
        } catch (error) {
          note = ` ${error instanceof Error ? error.message : ''}`;
        }

        const errors = preview.takeErrors(false);

        return {
          ok: true,
          output:
            `Loaded ${url}${title ? ` — "${title}"` : ''}.`
            + (timedOut ? ' (The load event did not fire within 30s; the page may still be fetching something.)' : '')
            + note
            + (errors.length ? ` ${errors.length} console error(s)/warning(s) so far — call browser_console to read them.` : ''),
          meta: { url, title, errors: errors.length, timedOut },
        };
      },
    ),

    defineTool(
      'browser_read_page',
      'Read the content of the page in the preview pane — the visible text by default, or the raw '
        + 'HTML. Scope it with a CSS selector to read just one region. This is how you verify that '
        + 'your plugin actually rendered what you intended.',
      z.object({
        selector: z.string().default('').describe('CSS selector to read. Empty string reads the whole <body>.'),
        format: z
          .enum(['text', 'html'])
          .default('text')
          .describe('"text" gives the rendered text (what a visitor reads); "html" gives the markup.'),
        max_chars: z.number().int().default(8000).describe('Truncate the result at this many characters.'),
      }),
      async (args) => {
        const doc = preview.doc();
        const selector = String(args.selector ?? '').trim();
        const format = args.format === 'html' ? 'html' : 'text';
        const maxChars = clamp(Number(args.max_chars ?? 8000), 200, 60000);

        const root: Element | null = selector ? doc.querySelector(selector) : doc.body;

        if (!root) {
          return {
            ok: false,
            output: `Error: no element matches "${selector}" on ${preview.currentUrl()}. `
              + 'Use browser_query to discover what is actually on the page.',
            meta: { selector },
          };
        }

        const content = format === 'html'
          ? root.outerHTML
          : (root as HTMLElement).innerText ?? root.textContent ?? '';

        const cleaned = format === 'text' ? content.replace(/\n{3,}/g, '\n\n').trim() : content;

        return {
          ok: true,
          output: `${preview.currentUrl()} — ${selector || 'body'} (${format}, ${cleaned.length} chars):\n`
            + truncate(cleaned, maxChars),
          meta: { selector: selector || 'body', format, chars: cleaned.length },
        };
      },
    ),

    defineTool(
      'browser_query',
      'Find elements in the preview by CSS selector and report what each one is: tag, id, classes, '
        + 'text, position, and whether it is actually visible. Use it to check that your markup made '
        + 'it onto the page, and to get a selector you can pass to browser_click.',
      z.object({
        selector: z.string().describe('CSS selector, e.g. ".my-plugin-widget" or "#wpadminbar a".'),
        limit: z.number().int().default(20).describe('Maximum number of matches to describe.'),
      }),
      async (args) => {
        const doc = preview.doc();
        const selector = String(args.selector ?? '').trim();
        const limit = clamp(Number(args.limit ?? 20), 1, 200);

        if (!selector) {
          return { ok: false, output: 'Error: browser_query requires a "selector".', meta: {} };
        }

        let matches: Element[];

        try {
          matches = Array.from(doc.querySelectorAll(selector));
        } catch {
          return { ok: false, output: `Error: "${selector}" is not a valid CSS selector.`, meta: { selector } };
        }

        if (!matches.length) {
          return {
            ok: true,
            output: `No elements match "${selector}" on ${preview.currentUrl()}.`,
            meta: { selector, count: 0 },
          };
        }

        const described = matches.slice(0, limit).map((el, index) => {
          const box = el.getBoundingClientRect();
          const style = el.ownerDocument.defaultView?.getComputedStyle(el);
          const hidden = !box.width && !box.height;
          const invisible = style?.display === 'none' || style?.visibility === 'hidden' || style?.opacity === '0';
          const text = (el.textContent ?? '').replace(/\s+/g, ' ').trim();

          return `  [${index}] <${el.tagName.toLowerCase()}`
            + (el.id ? ` id="${el.id}"` : '')
            + (el.className && typeof el.className === 'string' ? ` class="${el.className.trim()}"` : '')
            + '>'
            + ` at (${Math.round(box.x)}, ${Math.round(box.y)}) ${Math.round(box.width)}x${Math.round(box.height)}`
            + (hidden || invisible ? ' [NOT VISIBLE]' : '')
            + (text ? ` text: "${text.slice(0, 120)}"` : '');
        });

        return {
          ok: true,
          output: `${matches.length} element(s) match "${selector}"`
            + (matches.length > limit ? `, showing ${limit}` : '') + ':\n'
            + described.join('\n'),
          meta: { selector, count: matches.length },
        };
      },
    ),

    defineTool(
      'browser_click',
      'Click something in the preview — either the first element matching a CSS selector, or '
        + 'whatever sits at a pixel coordinate. Waits afterwards so any handler or navigation the '
        + 'click triggered has run, then reports where the page ended up.',
      z.object({
        selector: z.string().default('').describe('CSS selector of the element to click. Leave empty when clicking by coordinate.'),
        x: z.number().default(-1).describe('X coordinate inside the preview viewport. Use with y, and no selector.'),
        y: z.number().default(-1).describe('Y coordinate inside the preview viewport. Use with x, and no selector.'),
      }),
      async (args) => {
        const doc = preview.doc();
        const selector = String(args.selector ?? '').trim();
        const x = Number(args.x ?? -1);
        const y = Number(args.y ?? -1);
        const before = preview.currentUrl();

        let target: Element | null = null;
        let how = '';

        if (selector) {
          try {
            target = doc.querySelector(selector);
          } catch {
            return { ok: false, output: `Error: "${selector}" is not a valid CSS selector.`, meta: {} };
          }

          if (!target) {
            return {
              ok: false,
              output: `Error: nothing matches "${selector}". Use browser_query to see what is on the page.`,
              meta: { selector },
            };
          }

          how = `"${selector}"`;
        } else if (x >= 0 && y >= 0) {
          target = doc.elementFromPoint(x, y);

          if (!target) {
            return {
              ok: false,
              output: `Error: no element at (${x}, ${y}) — the point may be outside the visible area.`,
              meta: { x, y },
            };
          }

          how = `point (${x}, ${y})`;
        } else {
          return { ok: false, output: 'Error: browser_click needs either a "selector" or both "x" and "y".', meta: {} };
        }

        const label = `<${target.tagName.toLowerCase()}`
          + (target.id ? ` id="${target.id}"` : '')
          + '>'
          + ((target.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 60) || '');

        target.scrollIntoView({ block: 'center' });
        (target as HTMLElement).click();

        await preview.settle();

        const after = preview.currentUrl();
        const errors = preview.takeErrors(false);

        return {
          ok: true,
          output: `Clicked ${how} — ${label}.`
            + (after !== before ? ` The page navigated to ${after}.` : ' The URL did not change.')
            + (errors.length ? ` ${errors.length} console error(s)/warning(s) recorded — call browser_console.` : ''),
          meta: { clicked: how, navigated: after !== before, url: after },
        };
      },
    ),

    defineTool(
      'browser_fill',
      'Type a value into an input, textarea, or select in the preview, firing the input/change '
        + 'events that JavaScript listens for. Use it with browser_click to exercise a form your '
        + 'plugin renders.',
      z.object({
        selector: z.string().describe('CSS selector of the field to fill.'),
        value: z.string().describe('The value to set.'),
      }),
      async (args) => {
        const doc = preview.doc();
        const selector = String(args.selector ?? '').trim();
        const value = String(args.value ?? '');

        const field = selector ? doc.querySelector(selector) : null;

        if (!field) {
          return { ok: false, output: `Error: nothing matches "${selector}".`, meta: { selector } };
        }

        if (
          !(field instanceof HTMLInputElement)
          && !(field instanceof HTMLTextAreaElement)
          && !(field instanceof HTMLSelectElement)
        ) {
          return {
            ok: false,
            output: `Error: <${field.tagName.toLowerCase()}> is not a fillable field.`,
            meta: { selector },
          };
        }

        field.focus();
        field.value = value;
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));

        return { ok: true, output: `Set ${selector} to "${value}".`, meta: { selector, value } };
      },
    ),

    defineTool(
      'browser_assets',
      'List the JavaScript and CSS the preview page pulled in, and check each one actually loaded. '
        + 'Every external asset is re-requested to report its real HTTP status, so a 404 from a wrong '
        + 'enqueue path or a bad plugin URL shows up plainly.',
      z.object({
        type: z.enum(['all', 'js', 'css']).default('all').describe('Which assets to report.'),
        only_problems: z.boolean().default(false).describe('Report only the assets that failed to load.'),
      }),
      async (args) => {
        const doc = preview.doc();
        const want = args.type === 'js' || args.type === 'css' ? args.type : 'all';
        const onlyProblems = Boolean(args.only_problems);

        const assets: { kind: 'js' | 'css'; url: string }[] = [];
        let inlineScripts = 0;
        let inlineStyles = 0;

        if (want !== 'css') {
          for (const el of Array.from(doc.querySelectorAll('script'))) {
            if (el.src) {
              assets.push({ kind: 'js', url: el.src });
            } else if ((el.textContent ?? '').trim()) {
              inlineScripts++;
            }
          }
        }

        if (want !== 'js') {
          for (const el of Array.from(doc.querySelectorAll('link[rel~="stylesheet"]'))) {
            const href = (el as HTMLLinkElement).href;
            if (href) {
              assets.push({ kind: 'css', url: href });
            }
          }
          inlineStyles = doc.querySelectorAll('style').length;
        }

        const checked = await Promise.all(assets.map(async (asset) => {
          try {
            const response = await fetch(asset.url, { method: 'GET', credentials: 'same-origin' });
            return { ...asset, status: response.status, ok: response.ok, note: '' };
          } catch {
            // Almost always a cross-origin asset the page can load but fetch()
            // cannot read — not evidence that the asset itself is broken.
            return { ...asset, status: 0, ok: true, note: 'not checkable from this page (cross-origin)' };
          }
        }));

        const problems = checked.filter((a) => !a.ok);
        const shown = onlyProblems ? problems : checked;

        if (!shown.length) {
          return {
            ok: true,
            output: onlyProblems
              ? `All ${checked.length} external asset(s) on ${preview.currentUrl()} loaded successfully.`
              : `No external ${want === 'all' ? 'JS/CSS' : want.toUpperCase()} assets on ${preview.currentUrl()}.`,
            meta: { total: checked.length, failed: problems.length },
          };
        }

        const lines = shown.map((a) => {
          const status = a.status === 0 ? a.note : `HTTP ${a.status}`;
          return `  ${a.ok ? 'ok  ' : 'FAIL'} [${a.kind}] ${status}  ${relativize(a.url)}`;
        });

        return {
          ok: true,
          output:
            `${preview.currentUrl()} — ${checked.length} external asset(s), ${problems.length} failed`
            + `, ${inlineScripts} inline script(s), ${inlineStyles} inline style block(s):\n`
            + lines.join('\n')
            + (problems.length
              ? '\n\nA failing asset is usually a wrong path in wp_enqueue_script/style — check the URL you passed to plugins_url().'
              : ''),
          meta: { total: checked.length, failed: problems.length, inlineScripts, inlineStyles },
        };
      },
    ),

    defineTool(
      'browser_console',
      'Read the JavaScript errors, warnings, and console messages the preview page has produced. '
        + 'Capture starts as soon as a page begins loading, so reload with browser_navigate and then '
        + 'read this to diagnose a script that is failing.',
      z.object({
        clear: z.boolean().default(false).describe('Clear the buffer after reading, so the next read only shows new messages.'),
      }),
      async (args) => {
        const entries = preview.takeErrors(Boolean(args.clear));

        if (!entries.length) {
          return {
            ok: true,
            output: `No JavaScript errors or console messages recorded for ${preview.currentUrl()}.`,
            meta: { count: 0 },
          };
        }

        return {
          ok: true,
          output: `${entries.length} message(s) from ${preview.currentUrl()}:\n`
            + truncate(entries.map((e) => `  [${e.at}] ${e.kind}: ${e.text}`).join('\n'), 8000),
          meta: { count: entries.length },
        };
      },
    ),
  ];
}

function normalizeUrl(value: string): string {
  if (/^https?:\/\//i.test(value)) {
    return value;
  }

  if (value.startsWith('/')) {
    return window.location.origin + value;
  }

  return `https://${value}`;
}

/** Trims the origin off same-site URLs so long asset lists stay readable. */
function relativize(url: string): string {
  return url.startsWith(window.location.origin) ? url.slice(window.location.origin.length) : url;
}

function clamp(value: number, min: number, max: number): number {
  return Number.isFinite(value) ? Math.min(Math.max(value, min), max) : min;
}
