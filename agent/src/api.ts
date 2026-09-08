import type { AgentConfig } from './config';
import { runSignal } from './runControl';

export interface ToolResult {
  ok: boolean;
  output: string;
  meta: Record<string, unknown>;
}

interface Envelope {
  success: boolean;
  data: { output?: string; meta?: Record<string, unknown> } | null;
  error: { code: string; message: string } | null;
}

/**
 * Calls one backend tool endpoint.
 *
 * A failed tool call is NOT thrown. The model has to see the failure as a tool
 * result so it can correct itself — "old_string was not found, read the file
 * again" is useful input, whereas a thrown error would abort the whole run and
 * strand the user. Only the caller's own bugs surface as exceptions.
 */
export async function callTool(
  config: AgentConfig,
  tool: string,
  args: Record<string, unknown>,
): Promise<ToolResult> {
  const url = `${config.apiBase}/agent/${encodeURIComponent(config.slug)}/tool/${encodeURIComponent(tool)}`;

  let response: Response;

  try {
    response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      // Wired to the run's signal so pressing stop also cancels a tool request
      // that is still on the wire.
      signal: runSignal(),
      headers: {
        'Content-Type': 'application/json',
        'X-Plugitify-Nonce': config.nonce,
      },
      body: JSON.stringify(args ?? {}),
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      return { ok: false, output: 'Error: cancelled by the user.', meta: { aborted: true } };
    }

    const message = error instanceof Error ? error.message : String(error);

    return { ok: false, output: `Error: could not reach the Plugitify server (${message}).`, meta: {} };
  }

  let envelope: Envelope;

  try {
    envelope = (await response.json()) as Envelope;
  } catch {
    return {
      ok: false,
      output: `Error: the server returned a non-JSON response (HTTP ${response.status}). `
        + 'This usually means a PHP fatal error — check the debug log.',
      meta: {},
    };
  }

  if (!envelope.success || !envelope.data) {
    const code = envelope.error?.code ?? 'unknown_error';
    const message = envelope.error?.message ?? `HTTP ${response.status}`;

    return { ok: false, output: `Error [${code}]: ${message}`, meta: {} };
  }

  return {
    ok: true,
    output: envelope.data.output ?? '',
    meta: envelope.data.meta ?? {},
  };
}
