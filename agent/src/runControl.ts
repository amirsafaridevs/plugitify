/**
 * The abort signal for the run currently in flight.
 *
 * The SDK hands tool implementations a RunContext, not the caller's signal, so
 * this is how a tool's own fetch learns that the user pressed stop. Without it
 * a tool call already on the wire would keep going after the run was torn down,
 * and the stop button would only look like it worked.
 */
let current: AbortSignal | null = null;

export function setRunSignal(signal: AbortSignal | null): void {
  current = signal;
}

export function runSignal(): AbortSignal | undefined {
  return current ?? undefined;
}

export function isAborted(): boolean {
  return current?.aborted ?? false;
}
