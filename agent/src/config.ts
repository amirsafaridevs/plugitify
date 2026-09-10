/**
 * Runtime configuration, injected by chat.php as a JSON <script> tag.
 *
 * Values come from SettingsService (DB). The API key lands in the browser
 * because the agent talks to the model directly from here.
 */
export interface AgentConfig {
  /** Plugin slug being worked on. The agent's entire writable world. */
  slug: string;
  /** Base URL of the Plugitify API, e.g. "http://site/plugitify/v1". */
  apiBase: string;
  /** WordPress nonce for the tool endpoint. */
  nonce: string;
  /** "openai", or anything else for an OpenAI-compatible provider. */
  provider: string;
  model: string;
  /**
   * Must permit browser (CORS) requests — api.openai.com does not, so this is
   * normally an OpenAI-compatible gateway.
   */
  endpoint: string;
  /** Wire format to speak: 'responses' (default) or 'chat_completions'. */
  apiStyle: string;
  /** Reasoning effort: none | minimal | low | medium | high | xhigh | max. */
  reasoning: string;
  apiKey: string;
  /** UI locale, used to tell the agent which language to answer in. */
  locale: string;
  /** WordPress site home URL — fallback when no preview URL is stored. */
  siteUrl: string;
  /** URL shown in the preview iframe. */
  previewUrl: string;
}

export function loadConfig(): AgentConfig {
  const el = document.getElementById('pi-agent-config');

  if (!el || !el.textContent) {
    throw new Error('Agent configuration block (#pi-agent-config) is missing from the page.');
  }

  const parsed = JSON.parse(el.textContent) as Partial<AgentConfig>;

  const config: AgentConfig = {
    slug: parsed.slug ?? '',
    apiBase: parsed.apiBase ?? '',
    nonce: parsed.nonce ?? '',
    provider: parsed.provider ?? 'openai',
    model: parsed.model ?? '',
    endpoint: parsed.endpoint ?? 'https://api.openai.com/v1',
    apiStyle: parsed.apiStyle ?? 'responses',
    // Reasoning effort is a product policy, not a user-facing setting.
    reasoning: 'high',
    apiKey: parsed.apiKey ?? '',
    locale: parsed.locale ?? 'fa_IR',
    siteUrl: parsed.siteUrl ?? '',
    previewUrl: parsed.previewUrl ?? '',
  };

  if (!config.slug) {
    throw new Error('No plugin slug in the agent configuration.');
  }

  if (!config.siteUrl) {
    config.siteUrl = `${window.location.origin}/`;
  }

  return config;
}
