import { Agent, setDefaultOpenAIClient, setOpenAIAPI, setTracingDisabled } from '@openai/agents';
import { OpenAI } from 'openai';
import type { AgentConfig } from './config';
import type { Preview } from './preview';
import { buildInstructions } from './prompt';
import { buildTools } from './tools';

/** Give up on a single model request after this long and let the retry loop decide. */
const REQUEST_TIMEOUT_MS = 120000;

/**
 * Point the SDK at whichever provider the site is configured for.
 *
 * Values come from Plugitify → Settings (DB), injected into the page as
 * #pi-agent-config — swapping provider/model/key is a settings change, not a
 * code change.
 */
export function configureProvider(config: AgentConfig): void {
  if (!config.apiKey) {
    throw new Error('کلید API تنظیم نشده. از پلاگیتی → تنظیمات یک کلید وارد کنید.');
  }

  const client = new OpenAI({
    apiKey: config.apiKey,
    baseURL: config.endpoint,
    // The agent runs client-side by design; without this the SDK refuses to
    // start in a browser at all.
    dangerouslyAllowBrowser: true,
    timeout: REQUEST_TIMEOUT_MS,
    // Retries are handled one level up, around the whole run, where we can tell
    // the user what is happening and keep the tool work already done. Leaving
    // the client's own retries on would multiply the two.
    maxRetries: 0,
  });

  setDefaultOpenAIClient(client);

  // Tracing would POST every run to OpenAI's ingest endpoint. Wrong for a
  // self-hosted tool, and it fails outright against a non-OpenAI provider.
  setTracingDisabled(true);

  // Gateways almost universally implement Chat Completions and not the
  // Responses API, so the wire format is an explicit setting rather than
  // something inferred from the provider name.
  setOpenAIAPI(config.apiStyle === 'responses' ? 'responses' : 'chat_completions');
}

export function buildAgent(config: AgentConfig, preview: Preview): Agent {
  const chatCompletions = config.apiStyle !== 'responses';

  // A reasoning model will reject function tools on /v1/chat/completions with
  // a 400 unless reasoning is off. Tools are the entire point of this agent, so
  // when we're on that endpoint reasoning loses — better a working agent that
  // doesn't show its thinking than one that 400s on every message.
  const effort = chatCompletions ? 'none' : config.reasoning;

  return new Agent({
    name: 'Plugitify',
    instructions: buildInstructions(config),
    model: config.model,
    tools: buildTools(config, preview),
    modelSettings: {
      // Building a plugin is a long chain of read → edit → verify. Letting the
      // model fire several reads at once makes exploration much faster.
      parallelToolCalls: true,
      reasoning: {
        effort: effort as 'none' | 'minimal' | 'low' | 'medium' | 'high' | 'xhigh' | 'max',
        // Ask for the summary that the UI renders as "در حال فکر کردن". Only
        // the Responses API returns one.
        ...(chatCompletions ? {} : { summary: 'auto' as const }),
      },
    },
  });
}
