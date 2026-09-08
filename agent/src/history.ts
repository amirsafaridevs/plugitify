import type { AgentInputItem } from '@openai/agents';

/**
 * Helpers for keeping the conversation state valid.
 *
 * The history is the real thing we send to the model, so it has to stay
 * internally consistent: every function_call needs its matching result, and
 * every result needs its call. Stopping mid-run breaks that invariant, and a
 * provider will reject the next request outright if we send it back as-is.
 */

interface CallLike {
  type?: string;
  callId?: string;
}

/**
 * Drop tool calls whose result never arrived, and results whose call is gone.
 *
 * Both happen for real: the first when the user stops the run (or the
 * connection dies) between a call and its result, the second after trimming
 * old items off the front to fit in storage.
 */
export function sanitizeHistory(items: AgentInputItem[]): AgentInputItem[] {
  const answered = new Set<string>();
  const requested = new Set<string>();

  for (const item of items as CallLike[]) {
    if (item.type === 'function_call_result' && item.callId) {
      answered.add(item.callId);
    }
    if (item.type === 'function_call' && item.callId) {
      requested.add(item.callId);
    }
  }

  return items.filter((item) => {
    const entry = item as CallLike;

    if (entry.type === 'function_call' && entry.callId) {
      return answered.has(entry.callId);
    }

    if (entry.type === 'function_call_result' && entry.callId) {
      return requested.has(entry.callId);
    }

    return true;
  });
}

/**
 * Drop the oldest items to get under a storage limit, then re-sanitize so the
 * trim cannot leave an orphaned pair behind.
 */
export function trimHistory(items: AgentInputItem[], dropCount: number): AgentInputItem[] {
  if (dropCount <= 0) {
    return items;
  }

  return sanitizeHistory(items.slice(Math.min(dropCount, items.length)));
}

/** Cheap way to spot an empty conversation without inspecting item shapes. */
export function isEmptyHistory(items: AgentInputItem[] | null | undefined): boolean {
  return !items || items.length === 0;
}
