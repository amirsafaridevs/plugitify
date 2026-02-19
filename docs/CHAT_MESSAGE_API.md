# Chat and Message Stream API (for WordPress Plugin)

This document describes how to use the chat API and stream endpoint (`/messages` and `/messages/stream`) for implementation in a WordPress plugin. You provide the server address (base_url) to the plugin; all requests are sent to that base_url.

---

## 1. Base URL

All endpoints live under **`/messages`**. If your server base_url is e.g. `https://api.example.com`:

- **Get token:** `POST {base_url}/messages/token`
- **Send message (single response):** `POST {base_url}/messages`
- **Send message with stream (SSE):** `POST {base_url}/messages/stream`

Example with a base_url you provide to the plugin:

```text
base_url = "https://your-backend.com"
POST https://your-backend.com/messages/token
POST https://your-backend.com/messages
POST https://your-backend.com/messages/stream
```

---

## 2. Getting a Token

Every chat request (both regular and stream) must send a valid **JWT** in the request header. You obtain this JWT from the token endpoint.

### 2.1 Token Request

**Method and path:** `POST {base_url}/messages/token`

**Request body (JSON):**

| Field         | Type   | Required | Description |
|---------------|--------|----------|-------------|
| `site_token`  | string | Yes      | 64-character site API token from the site record (e.g. from the admin panel) |
| `site_url`    | string | Yes      | Full site URL with http or https, **no** trailing slash (e.g. `https://example.com`) |

**Example:**

```json
{
  "site_token": "abc123...64chars",
  "site_url": "https://mysite.com"
}
```

**Success response (200):**

```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

- `access_token`: The JWT to use in subsequent chat requests.
- `expires_in`: Token lifetime in seconds (e.g. 3600 = 1 hour). After expiry, call `/messages/token` again to get a new token.

**Errors:** If the token is invalid, `site_url` does not match the site that owns the token, or the site has expired, the server responds with an appropriate status/body. The plugin should handle these and show a suitable message instead of using a bad token.

### 2.2 Using the Token in Chat Requests

For **all** requests to `POST {base_url}/messages` and `POST {base_url}/messages/stream`, set this header:

```http
Authorization: Bearer <access_token>
```

Example with the token from the previous step:

```http
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

If this header is missing or the token is expired/invalid, the server will reject the request.

---

## 3. Shared Chat Payload (Message Payload)

Both message endpoints (`/messages` and `/messages/stream`) accept the same request body.

**Method:** `POST`  
**Content-Type:** `application/json`

| Field              | Type   | Required | Description |
|--------------------|--------|----------|-------------|
| `site_url`         | string | Yes      | Same full site URL (no trailing slash). Must match the site for which the token was issued. |
| `chat_id`          | string | Yes      | Unique identifier for this chat (e.g. UUID). Used to match stream events to the same conversation. |
| `task_history`     | array  | No       | Optional list of task context. The server passes it to the model as "Task context". Can be an empty array. |
| `message_history`  | array  | Yes      | Chat message history (user and assistant). Format of each item is described below. |
| `tools_api_token`  | string | No       | 64-character Tools API Token from **Plugifity → Licence** (Tools API Token box). When present, the backend sends it in the `Authorization: Bearer` header for every request to plugin **tool** endpoints (Query, File, General). **Security:** The backend uses this value only in-memory during the request and must never store or log it. |

**When to send `tools_api_token`:** If the user can trigger actions that call the WordPress plugin’s tool API (e.g. list plugins, read files, run queries), include the Tools API Token from **Plugifity → Licence** in every `POST /messages` or `POST /messages/stream` body. The backend then adds it to outgoing plugin API requests. The token is **never stored or logged**; it is used only for the duration of that request.

### 3.1 Format of Each Item in `message_history`

Each element is an object with:

| Field            | Type   | Description |
|------------------|--------|-------------|
| `role`           | string | One of: `"user"` or `"human"` for the user, `"assistant"` or `"ai"` for the model. Otherwise treated as user. |
| `content` or `text` | string | Message text. If both are missing, `str(item)` is used. |

**Example `message_history`:**

```json
[
  { "role": "user", "content": "Create a contact page." },
  { "role": "assistant", "content": "Contact page created." },
  { "role": "user", "content": "Change its title to 'Contact Us'." }
]
```

For a new chat, send only the new message(s) in `message_history`. To continue a conversation, send the full history (or at least the last few messages) so the model has context.

---

## 4. Send Message Without Stream: `POST /messages`

- **Path:** `POST {base_url}/messages`
- **Header:** `Authorization: Bearer <access_token>`
- **Body:** The same Message Payload as in section 3: `site_url`, `chat_id`, `task_history`, `message_history`. Optionally include **`tools_api_token`** (64-char from Plugifity → Licence) when the agent may call plugin tool endpoints; it is used in-memory only and never stored or logged.

**Response:** A single JSON response with the standard API structure (e.g. `success`, `message`, `data`). The `data` object looks like:

```json
{
  "status": "ok",
  "chat_id": "...",
  "reply": "Full text of the model's reply"
}
```

On error (e.g. model unavailable), you may get status 503 and an error detail.

---

## 5. Send Message With Stream: `POST /messages/stream`

Use this endpoint for live chat, incremental response text, and “thinking” status.

- **Path:** `POST {base_url}/messages/stream`
- **Header:** `Authorization: Bearer <access_token>`
- **Body:** Same Message Payload as in section 3 (`site_url`, `chat_id`, `task_history`, `message_history`, and optionally **`tools_api_token`** for plugin tool calls; the token is used in-memory only and never stored or logged).

**Response type:** `text/event-stream` (SSE). The response is a sequence of events; each event is a line starting with `data:` containing a JSON object.

### 5.1 SSE Event Format

Each chunk in the stream looks like:

```text
data: {"type": "...", ...}

```

The content after `data:` is a JSON object with at least a **`type`** field. Depending on `type`, fields such as `content` or `chat_id` may also be present.

### 5.2 Event Types

| Type (`type`)      | Main fields  | Description |
|--------------------|--------------|-------------|
| `start`            | `chat_id`    | Stream started; sent once at the beginning. |
| `thinking`         | `content`    | Model is processing. Usually has a default message like "Waiting for model response..." or server-defined text. Use it to show a spinner or “Thinking…” in the UI. |
| `reasoning_chunk`  | `content`    | A piece of the model’s reasoning (if the model sends reasoning). You can show it in a separate “reasoning” area or ignore it. |
| `chunk`            | `content`    | A piece of the **final** reply to the user. Append these in order to build the full reply and display it in the chat. |
| `event`            | `content`    | Generic event (e.g. “Using tool: X”, “Tool Y returned”, “Error in tool Z”). Use for logging or status in the UI. |
| `error`            | `content`    | Error. After this, the stream ends; no further useful events. |
| `done`             | `chat_id`    | Stream finished successfully. Close the connection and update the chat. |

Typical order of events:

1. One `start`
2. Zero or more `thinking` (before each response/tool phase)
3. Zero or more `reasoning_chunk` (if the model supports it)
4. Zero or more `chunk` (reply text)
5. While the model uses tools, you may see `thinking` again, then `event`, then more `chunk`
6. Finally either one `done` (success) or one `error` (failure)

### 5.3 Raw Stream Event Example

```text
data: {"type": "start", "chat_id": "uuid-123"}

data: {"type": "thinking", "content": "Waiting for model response..."}

data: {"type": "reasoning_chunk", "content": "First I need to find the page..."}

data: {"type": "chunk", "content": "The page"}

data: {"type": "chunk", "content": " has been created."}

data: {"type": "event", "content": "Using tool: create_page"}

data: {"type": "chunk", "content": "\n\nThe contact page was created successfully."}

data: {"type": "done", "chat_id": "uuid-123"}
```

### 5.4 Client-Side Implementation (WordPress Plugin)

1. **Get token:** Once (or after `expires_in` has passed), call `POST {base_url}/messages/token` with `site_token` and `site_url`, then store `access_token`.
2. **Send message with stream:** Send `POST {base_url}/messages/stream` with the same chat body (`site_url`, `chat_id`, `message_history`, and optionally **`tools_api_token`** from Plugifity → Licence when the agent may call plugin tools) and header `Authorization: Bearer <access_token>`.
3. **Read the stream:** Consume the response as SSE. In JavaScript, `EventSource` is for GET; for POST you typically use `fetch` with `ReadableStream` or an SSE library. Parse each `data:` line and, based on `type`:
   - `start` → Store `chat_id` and prepare the UI.
   - `thinking` → Show “Thinking…” (e.g. spinner or the `content` text).
   - `reasoning_chunk` → Optionally show in a separate reasoning section.
   - `chunk` → Append `content` to the current reply and update the chat area (e.g. `chat-main`).
   - `event` → Use for logging or a status bar (e.g. “Using tool…”).
   - `error` → Show the error message and treat the stream as ended.
   - `done` → Close the stream, save the final reply to history, and return to normal chat state.

4. **base_url:** You provide the server URL (e.g. `https://your-backend.com`) to the plugin; all requests go to that base_url.

---

## 6. Flow Summary

1. Configure **base_url** in the plugin.
2. Call `POST {base_url}/messages/token` with **site_token** and **site_url** to get **access_token**.
3. For each user message, call `POST {base_url}/messages/stream` (or `POST {base_url}/messages`) with:
   - Header: **`Authorization: Bearer <access_token>`**
   - Body: **site_url**, **chat_id**, **message_history** (and **task_history** if needed).
   - If the agent may call plugin tool endpoints (Query, File, General), also send **`tools_api_token`** in the body (64-char from **Plugifity → Licence**). The backend uses it only in-memory and never stores or logs it.
4. Read the stream line by line and update the UI according to each event **type** (`thinking`, `chunk`, `event`, `error`, `done`).
5. After **done** or **error**, the stream is over; if the token has expired, repeat step 2.

With this, the WordPress plugin can implement chat against this backend, and any developer or model reading this document knows how to use `/messages/token` and `/messages/stream` (and `/messages` if needed), where to get the token, when to send **tools_api_token** for plugin tools, and how to handle all event types, including `thinking`.
