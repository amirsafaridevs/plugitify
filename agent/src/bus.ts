import type { ToolResult } from './api';

/**
 * Tool activity is reported straight from the tool wrapper rather than read off
 * the run stream, because only here do we have the call arguments, the
 * structured `meta`, and the elapsed time all at once.
 */
export interface ToolStartEvent {
  id: string;
  tool: string;
  args: Record<string, unknown>;
  /**
   * The model's own call id. Unlike `id` (a per-page counter) this also appears
   * in the saved history, which is how a restored transcript reattaches timing
   * and counts to the right tool card.
   */
  callId?: string;
}

export interface ToolEndEvent extends ToolStartEvent {
  result: ToolResult;
  durationMs: number;
}

type Handlers = {
  'tool:start': (event: ToolStartEvent) => void;
  'tool:end': (event: ToolEndEvent) => void;
};

const listeners: { [K in keyof Handlers]: Handlers[K][] } = {
  'tool:start': [],
  'tool:end': [],
};

export function on<K extends keyof Handlers>(event: K, handler: Handlers[K]): void {
  listeners[event].push(handler);
}

export function emit<K extends keyof Handlers>(event: K, payload: Parameters<Handlers[K]>[0]): void {
  for (const handler of listeners[event]) {
    (handler as (p: unknown) => void)(payload);
  }
}
