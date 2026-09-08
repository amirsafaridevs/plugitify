# Plugitify agent

The coding agent that runs on the chat page. It is built on the
[OpenAI Agents SDK](https://openai.github.io/openai-agents-js/) and runs **entirely in the
admin's browser** — the agent loop, the tool dispatch, and the conversation state all live in
this tab. WordPress is only involved for the filesystem tools.

```
browser tab (chat page)
  ├── Agent loop + UI  ──── POST {endpoint}/responses ────> the model
  ├── preview tools ─────── same-origin iframe, no server round trip
  └── file tools ────────── POST /plugitify/v1/agent/{slug}/tool/{tool} ──> AgentTools (PHP)
```

## Build

```bash
cd agent
npm install
npm run build      # -> ../src/muPlugin/view/assets/js/agent.bundle.js
npm run dev        # same, rebuilds on save, with sourcemaps
npm run typecheck
```

The bundle is committed, so a plain checkout of the plugin works without Node. Re-run
`npm run build` after changing anything in `src/`.

## Configuration

Everything comes from constants in `plugitify.php`. Override them in `wp-config.php` so the
values — the API key in particular — never sit in a tracked file.

| Constant | Default | Notes |
| --- | --- | --- |
| `PLUGITIFY_AI_PROVIDER` | `openai` | Informational label. |
| `PLUGITIFY_AI_MODEL` | `gpt-5.6-luna` | Any model the endpoint serves. |
| `PLUGITIFY_AI_ENDPOINT` | `https://api.openai.com/v1` | **Must allow browser CORS — see below.** |
| `PLUGITIFY_AI_API_STYLE` | `responses` | `responses` or `chat_completions`. |
| `PLUGITIFY_AI_REASONING_EFFORT` | `medium` | `none`…`max`. Forced to `none` on `chat_completions`. |
| `PLUGITIFY_AI_API_KEY` | *(empty)* | Sent to the browser. See the security note. |

### The endpoint must allow CORS

The agent calls the model from the page, so the endpoint has to answer cross-origin requests.

**`api.openai.com` does not.** It answers `GET /v1/models` from a browser, but it rejects the
CORS preflight for an authenticated `POST`, which the SDK surfaces as a bare `Connection error.`
Point `PLUGITIFY_AI_ENDPOINT` at an OpenAI-compatible gateway (LiteLLM, OpenRouter, or your own)
that sets `Access-Control-Allow-Origin` and forwards upstream.

### Why `responses` is the default

Reasoning models reject function tools on `/v1/chat/completions`:

```
400 Function tools with reasoning_effort are not supported for gpt-5.6-luna in
/v1/chat/completions. To use function tools, use /v1/responses or set reasoning_effort to 'none'.
```

This agent is built around tool use, so it defaults to `/v1/responses`, which supports tools and
reasoning together and is the only way to get the "در حال فکر کردن" summaries in the transcript.
If your gateway only speaks Chat Completions, set `PLUGITIFY_AI_API_STYLE` to `chat_completions`
— `buildAgent()` then forces reasoning effort to `none` so requests still succeed, at the cost of
no visible thinking.

## Conversation state

There is one conversation per plugin, kept in `localStorage` under
`plugitify:chat:v2:{slug}`.

What is stored is the **history array itself** — the exact `AgentInputItem[]` sent to the model —
not rendered HTML. The transcript is redrawn from that same array on load (`restore.ts`), so the
picture on screen and the state the model sees cannot drift apart. A small `toolMeta` sidecar,
keyed by the model's own call id, carries the cosmetic extras (result counts) that the history
does not contain.

Three invariants make this safe:

- **Orphan pruning.** Stopping mid-run can leave a `function_call` whose result never arrived;
  a provider rejects that outright on the next request. `sanitizeHistory()` drops unmatched calls
  and results before every send and every save.
- **Quota shedding.** Tool results contain whole files, and a long session can pass the ~5 MB
  localStorage budget. `saveChat()` sheds the oldest quarter of the conversation and retries
  rather than losing the save entirely, telling the user when it does.
- **Stop is a save point.** Cancelling keeps whatever tool work already completed, so the next
  message resumes instead of redoing it.

"چت جدید" clears both the transcript and the stored entry.

## Stopping, retries, and errors

The send button becomes a stop button while a run is in flight. Abort propagates to the model
request *and* to any tool request already on the wire (`runControl.ts` — the SDK does not hand
tools the caller's signal, so it is threaded through a module-level holder). Aborting usually ends
the stream cleanly rather than throwing, so the cancellation is reported from the success path as
well as the catch.

A failed run is retried up to **3 times** (1s then 3s backoff), resuming from the salvaged history
so completed tool work is not repeated. Only faults that can plausibly succeed on a retry qualify —
network, timeout, 429, 5xx, malformed output. Auth failures, a missing model, and bad-request
errors are reported immediately, because retrying them only wastes time and money.
`errors.ts` owns that classification and produces one calm Persian sentence per failure, with the
raw provider text tucked into a disclosure.

## Security

The API key is embedded in the chat page and is therefore visible to anyone who can open it.
That is inherent to running the agent client-side. Two consequences worth being deliberate about:

- The chat route is gated on `manage_options`, so only administrators ever receive the key. Do
  not loosen that.
- Keep the key out of version control. Define `PLUGITIFY_AI_API_KEY` in `wp-config.php`, not in
  `plugitify.php`, and rotate any key that has been committed.

Tool calls are separately protected: the route requires `manage_options` *and* a nonce
(`X-Plugitify-Nonce`), because a cookie-authenticated endpoint that writes files would otherwise
be CSRF-able.

## Source layout

| File | Role |
| --- | --- |
| `main.ts` | Entry point: input, the run loop, retries, stop, persistence. |
| `agent.ts` | Provider wiring (`setDefaultOpenAIClient`, API style, reasoning) and the Agent. |
| `prompt.ts` | The system prompt: workspace boundary, workflow, WordPress rules. |
| `tools.ts` | Assembles the toolset: server-side tools plus the preview tools. |
| `browserTools.ts` | The seven `browser_*` tools that drive the preview pane. |
| `preview.ts` | Iframe control: navigation, same-origin access, JS error capture. |
| `toolKit.ts` | `defineTool()` — bus reporting and turning throws into tool results. |
| `api.ts` | The backend fetch client. Tool failures come back as text, never as throws. |
| `storage.ts` | localStorage persistence with quota shedding. |
| `history.ts` | Orphan pruning and trimming, so saved state stays valid. |
| `restore.ts` | Redraws a saved conversation from the history array. |
| `errors.ts` | Classifies failures: retryable or not, and what the user reads. |
| `runControl.ts` | Carries the active abort signal to tool implementations. |
| `ui.ts` | The transcript: messages, reasoning, live tool cards, notices. |
| `markdown.ts` | Small HTML-escaping Markdown renderer. |
| `bus.ts` | Tool start/end events, so the UI can draw calls with args and meta. |
| `config.ts` | Parses the `#pi-agent-config` JSON block written by `chat.php`. |

## Tools

Fifteen server-side tools run in PHP (`AgentTools`), scoped to the plugin directory:
`workspace_info`, `list_files`, `read_file`, `search_files`, `glob_files`, `write_file`,
`edit_file`, `delete_file`, `create_directory`, `delete_directory`, `move_path`, `php_lint`,
`plugin_control`, `debug_control`, `read_debug_log`.

Seven more run in this tab and drive the preview iframe. They need no server round trip, because
the preview is same-origin with the chat page:

| Tool | Does |
| --- | --- |
| `browser_navigate` | Point the preview at a URL and wait for load. `"reload"` re-fetches the current page. |
| `browser_read_page` | Read rendered text or raw HTML, optionally scoped to a selector. |
| `browser_query` | Describe elements matching a selector: tag, id, classes, box, visibility, text. |
| `browser_click` | Click by selector or by `x`/`y`, then report whether the page navigated. |
| `browser_fill` | Set an input/textarea/select value and fire `input`/`change`. |
| `browser_assets` | List external JS/CSS and re-request each to report its real HTTP status. |
| `browser_console` | Read JS errors, unhandled rejections, failed subresources, and console output. |

`preview.ts` instruments each document as soon as it starts parsing — not on `load` — so errors
thrown during page startup are captured rather than missed. If the user points the address bar at
another origin, the tools report that the page is unreadable instead of failing opaquely.

## The sandbox

Every file tool is scoped to one plugin directory by `PluginWorkspace` (PHP). Paths are rejected
lexically (`..`, absolute paths, drive letters, null bytes) and then physically — the nearest
existing ancestor is `realpath()`d and must still be inside the workspace, which is what stops a
symlink from escaping. Plugitify's own directory can never be a workspace.
