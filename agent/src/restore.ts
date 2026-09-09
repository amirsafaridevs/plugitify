import type { AgentInputItem } from '@openai/agents';
import type { ToolMetaEntry } from './storage';
import type { Transcript } from './ui';

/**
 * Redraws a saved conversation into the transcript.
 *
 * Everything comes from the history array itself — user turns, assistant
 * messages, reasoning, tool calls and their results are all in there, because
 * that is exactly what the model is sent. `toolMeta` only restores the
 * cosmetics (timing, result counts) and its absence degrades gracefully.
 */
export function replayHistory(
  transcript: Transcript,
  history: AgentInputItem[],
  toolMeta: Record<string, ToolMetaEntry>,
): void {
  for (const raw of history) {
    const item = raw as Record<string, unknown>;
    const type = typeof item.type === 'string' ? item.type : '';
    const role = typeof item.role === 'string' ? item.role : '';

    if (role === 'user') {
      const text = extractText(item.content);
      if (text) {
        transcript.addUserMessage(text);
      }
      continue;
    }

    if (role === 'assistant') {
      const text = extractText(item.content);
      if (text) {
        transcript.finalizeAssistantMessage(text);
      }
      continue;
    }

    if (type === 'reasoning') {
      transcript.addReasoning(extractText(item.rawContent) || extractText(item.content));
      continue;
    }

    if (type === 'function_call') {
      const callId = String(item.callId ?? '');
      transcript.startTool({
        id: callId,
        callId,
        tool: String(item.name ?? 'tool'),
        args: parseArguments(item.arguments),
      });
      continue;
    }

    if (type === 'function_call_result') {
      const callId = String(item.callId ?? '');
      const output = extractOutput(item.output);
      const saved = toolMeta[callId];

      transcript.endTool({
        id: callId,
        callId,
        tool: String(item.name ?? 'tool'),
        args: {},
        // Tool failures come back as normal results whose text starts with
        // "Error", so status alone can't tell us; trust the sidecar when it is
        // there and fall back to that convention when it is not.
        result: {
          ok: saved ? saved.ok : !/^Error(\s*\[[^\]]+\])?:/.test(output),
          output,
          meta: saved?.meta ?? {},
        },
        durationMs: saved?.durationMs ?? 0,
      });
    }
  }

  // Replay appends without animation, so land the user at the newest turn.
  transcript.settleOpenSteps();
  transcript.scrollToEnd();
}

/**
 * Message content is an array of parts whose text field varies by kind
 * (input_text for user, output_text for assistant, reasoning_text for
 * reasoning). A plain string is also legal.
 */
function extractText(content: unknown): string {
  if (typeof content === 'string') {
    return content;
  }

  if (!Array.isArray(content)) {
    return '';
  }

  return content
    .map((part) => {
      const entry = part as { text?: unknown; refusal?: unknown };

      if (typeof entry?.text === 'string') {
        return entry.text;
      }

      return typeof entry?.refusal === 'string' ? entry.refusal : '';
    })
    .join('')
    .trim();
}

function extractOutput(output: unknown): string {
  if (typeof output === 'string') {
    return output;
  }

  const entry = output as { text?: unknown; type?: unknown };

  if (typeof entry?.text === 'string') {
    return entry.text;
  }

  try {
    return JSON.stringify(output) ?? '';
  } catch {
    return '';
  }
}

function parseArguments(value: unknown): Record<string, unknown> {
  if (typeof value !== 'string') {
    return (value as Record<string, unknown>) ?? {};
  }

  try {
    const parsed = JSON.parse(value);

    return parsed && typeof parsed === 'object' ? (parsed as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}
