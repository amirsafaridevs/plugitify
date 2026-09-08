import type { AgentInputItem } from '@openai/agents';
import { trimHistory } from './history';

/**
 * Persists the one open conversation per plugin, in localStorage.
 *
 * What gets stored is the history itself — the exact array we send to the model
 * — rather than rendered HTML. That keeps a single source of truth: the
 * transcript is redrawn from the same data the model sees, so the two can never
 * drift. `toolMeta` rides alongside as a purely cosmetic sidecar (durations and
 * result counts), keyed by the model's call id.
 */

const VERSION = 2;

export interface ToolMetaEntry {
  durationMs: number;
  ok: boolean;
  meta: Record<string, unknown>;
}

export interface SavedChat {
  version: number;
  slug: string;
  savedAt: string;
  previewUrl: string;
  history: AgentInputItem[];
  toolMeta: Record<string, ToolMetaEntry>;
}

/** Scoped per plugin, so switching slugs doesn't mix two projects together. */
function storageKey(slug: string): string {
  return `plugitify:chat:v${VERSION}:${slug}`;
}

export function loadChat(slug: string): SavedChat | null {
  let raw: string | null;

  try {
    raw = window.localStorage.getItem(storageKey(slug));
  } catch {
    // Private mode, or storage disabled by policy.
    return null;
  }

  if (!raw) {
    return null;
  }

  try {
    const parsed = JSON.parse(raw) as SavedChat;

    if (parsed.version !== VERSION || !Array.isArray(parsed.history)) {
      return null;
    }

    return {
      ...parsed,
      toolMeta: parsed.toolMeta ?? {},
    };
  } catch {
    // Corrupt entry: drop it rather than breaking every future page load.
    clearChat(slug);

    return null;
  }
}

export interface SaveOutcome {
  ok: boolean;
  /** How many of the oldest items had to be dropped to make it fit. */
  dropped: number;
}

/**
 * Writes the conversation, shedding the oldest turns if it does not fit.
 *
 * A long agent session accumulates whole files in its tool results and can pass
 * the ~5 MB localStorage budget, so a quota failure is expected rather than
 * exceptional — losing the start of a conversation beats losing all of it.
 */
export function saveChat(slug: string, chat: Omit<SavedChat, 'version' | 'slug' | 'savedAt'>): SaveOutcome {
  let history = chat.history;
  let dropped = 0;

  for (let attempt = 0; attempt < 6; attempt++) {
    const payload: SavedChat = {
      version: VERSION,
      slug,
      savedAt: new Date().toISOString(),
      previewUrl: chat.previewUrl,
      history,
      toolMeta: chat.toolMeta,
    };

    try {
      window.localStorage.setItem(storageKey(slug), JSON.stringify(payload));

      return { ok: true, dropped };
    } catch (error) {
      if (!isQuotaError(error) || history.length <= 2) {
        return { ok: false, dropped };
      }

      // Shed a quarter of what is left (at least one item) and try again.
      const drop = Math.max(1, Math.floor(history.length / 4));
      history = trimHistory(history, drop);
      dropped += drop;
    }
  }

  return { ok: false, dropped };
}

export function clearChat(slug: string): void {
  try {
    window.localStorage.removeItem(storageKey(slug));
  } catch {
    // Nothing useful to do — the caller has already reset the in-memory state.
  }
}

function isQuotaError(error: unknown): boolean {
  return (
    error instanceof DOMException
    && (error.name === 'QuotaExceededError' || error.name === 'NS_ERROR_DOM_QUOTA_REACHED')
  );
}
