import { tool } from '@openai/agents';
import { z } from 'zod';
import type { ToolResult } from './api';
import * as bus from './bus';

let callCounter = 0;

/** Shape of the third argument the SDK hands `execute`. */
interface CallDetails {
  toolCall?: { callId?: string };
}

/**
 * Wraps one implementation as an SDK function tool.
 *
 * Announces the call on the bus so the UI can draw it live, and converts a
 * thrown error into a normal tool result: the model needs to *see* failures to
 * correct itself, whereas a throw would abort the run and strand the user.
 */
export function defineTool(
  name: string,
  description: string,
  // The SDK's parameter type is a narrow union of strict/non-strict Zod shapes
  // that a generic can't satisfy; no tool here uses the inferred argument
  // types, so the cast stays contained.
  parameters: z.ZodObject<z.ZodRawShape>,
  run: (args: Record<string, unknown>) => Promise<ToolResult>,
) {
  return tool({
    name,
    description,
    parameters: parameters as never,
    async execute(args, _context, details) {
      const payload = (args ?? {}) as Record<string, unknown>;
      // Prefer the model's call id: it survives into the saved history, so a
      // restored transcript can reattach this card's timing and counts.
      const callId = (details as CallDetails | undefined)?.toolCall?.callId;
      const id = callId ?? `call-${++callCounter}`;
      const startedAt = performance.now();

      bus.emit('tool:start', { id, callId, tool: name, args: payload });

      let result: ToolResult;

      try {
        result = await run(payload);
      } catch (error) {
        result = {
          ok: false,
          output: `Error: ${error instanceof Error ? error.message : String(error)}`,
          meta: {},
        };
      }

      bus.emit('tool:end', {
        id,
        callId,
        tool: name,
        args: payload,
        result,
        durationMs: Math.round(performance.now() - startedAt),
      });

      return result.output;
    },
  });
}

export function truncate(text: string, max: number): string {
  return text.length <= max
    ? text
    : `${text.slice(0, max)}\n\n[truncated at ${max} characters — narrow the selector or lower max_chars to see the rest]`;
}
