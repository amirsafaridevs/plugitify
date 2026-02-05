/**
 * Plugitify Assistant – Chat list from DB, messages via REST, AI via backend.
 * All operations are AJAX; loading states use skeletons where appropriate.
 */

(function () {
  'use strict';

  const restUrl = typeof agentifyRest !== 'undefined' ? agentifyRest.restUrl : '';
  const restNonce = typeof agentifyRest !== 'undefined' ? agentifyRest.nonce : '';
  const agentifyBaseUrl = typeof agentifyRest !== 'undefined' ? (agentifyRest.agentifyBaseUrl || '') : '';
  const siteUrl = typeof agentifyRest !== 'undefined' ? (agentifyRest.siteUrl || '') : '';
  const adminUrl = typeof agentifyRest !== 'undefined' ? (agentifyRest.adminUrl || '') : '';

  const messagesEl = document.getElementById('agentify-messages');
  const chatForm = document.getElementById('agentify-chat-form');
  const userInput = document.getElementById('agentify-user-input');
  const btnSend = document.getElementById('agentify-btn-send');
  const btnStop = document.getElementById('agentify-btn-stop');
  const btnNewChat = document.getElementById('agentify-btn-new-chat');
  const chatListPlaceholder = document.getElementById('agentify-chat-list-placeholder');
  const chatItems = document.getElementById('agentify-chat-items');
  /** Last tasks list for current chat (so we can re-render under message after renderMessages). */
  var currentTasksList = [];
  const welcomeEl = document.getElementById('agentify-welcome');

  if (!messagesEl || !chatForm || !userInput) return;

  let currentChatId = null;
  /** Messages for current chat (for Agentify history sync). Format: [{ role, content }] */
  let currentChatMessages = [];
  let agentifyAgent = null;
  /** Last user message text, for Try again after error */
  let lastUserMessageForRetry = null;
  /** Current turn events (thinking steps, tool calls) – show last 8 under thinking bubble */
  let recentEvents = [];
  /** Raw stream content for current thinking reply (so we can render HTML and still have raw for save). */
  let currentStreamRawContent = '';
  /** Event type → human-readable description (README event types table) */
  var EVENT_TYPE_DESCRIPTIONS = {
    user_message_sent: 'User sends a message',
    assistant_message_started: 'Assistant starts responding',
    assistant_message_completed: 'Assistant completes response',
    assistant_token_received: 'Token received (streaming)',
    tool_call_initiated: 'Tool execution starts',
    tool_call_completed: 'Tool execution succeeds',
    tool_call_failed: 'Tool execution fails',
    thinking_started: 'Thinking mode starts',
    api_request_sent: 'API request sent',
    api_response_received: 'API response received',
    api_request_failed: 'API request fails',
    error_occurred: 'Error occurs',
    stream_started: 'Streaming starts'
  };
  var PROVIDER_URLS = {
    deepseek: 'https://api.deepseek.com/v1/chat/completions',
    chatgpt: 'https://api.openai.com/v1/chat/completions',
    openai: 'https://api.openai.com/v1/chat/completions',
  };

  function api(endpoint, options) {
    var url = restUrl + endpoint;
    var headers = { 'X-WP-Nonce': restNonce };
    if (options && (options.method === 'POST' || options.method === 'PUT' || options.method === 'PATCH') && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }
    if (options && options.headers) for (var k in options.headers) headers[k] = options.headers[k];
    return fetch(url, {
      credentials: 'same-origin',
      method: (options && options.method) || 'GET',
      body: options && options.body,
      headers: headers,
    })
      .then(function (res) {
        return res.json().then(function (data) { return { ok: res.ok, status: res.status, data: data }; }).catch(function () { return { ok: res.ok, status: res.status, data: {} }; });
      });
  }

  function scrollToBottom() {
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
      });
    });
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  /** Detect RTL (e.g. Persian, Arabic, Hebrew) vs LTR from first strong character. */
  function getTextDirection(text) {
    if (!text || !String(text).trim()) return 'ltr';
    var str = String(text);
    var rtlRange = /[\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
    for (var i = 0; i < str.length; i++) {
      var c = str[i];
      var code = c.charCodeAt(0);
      if (/\s/.test(c)) continue;
      if (rtlRange.test(c)) return 'rtl';
      if ((code >= 0x0041 && code <= 0x005A) || (code >= 0x0061 && code <= 0x007A) || (code >= 0x00C0 && code <= 0x024F) || (code >= 0x0400 && code <= 0x04FF)) return 'ltr';
    }
    return 'ltr';
  }

  /** Find matching closing </div> from start of an opening <div> (handles nesting). */
  function findMatchingClosingDiv(html, startIndex) {
    var i = startIndex;
    var depth = 1;
    var len = html.length;
    while (i < len) {
      var open = html.indexOf('<div', i);
      var close = html.indexOf('</div>', i);
      if (close === -1) return -1;
      if (open !== -1 && open < close) {
        depth++;
        i = open + 4;
        continue;
      }
      depth--;
      if (depth === 0) return close;
      i = close + 6;
    }
    return -1;
  }

  /** Split text into segments; extract known tool HTML blocks (table, button, mc) so they can be rendered as HTML. */
  function splitKnownHtmlBlocks(str) {
    var parts = [];
    var s = String(str);
    var safeStarts = [
      'agentify-data-table-wrap',
      'agentify-msg-button-wrap',
      'agentify-mc-block'
    ];
    var i = 0;
    while (i < s.length) {
      var found = -1;
      var which = -1;
      for (var w = 0; w < safeStarts.length; w++) {
        var needle = 'class="' + safeStarts[w] + '"';
        var idx = s.indexOf(needle, i);
        if (idx !== -1 && (found === -1 || idx < found)) {
          found = idx;
          which = w;
        }
        var needle2 = "class='" + safeStarts[w] + "'";
        idx = s.indexOf(needle2, i);
        if (idx !== -1 && (found === -1 || idx < found)) {
          found = idx;
          which = w;
        }
      }
      if (found === -1) {
        parts.push({ type: 'text', value: s.substring(i) });
        break;
      }
      var openTagStart = s.lastIndexOf('<div', found);
      if (openTagStart === -1 || openTagStart < i) {
        parts.push({ type: 'text', value: s.substring(i, found) });
        i = found;
        continue;
      }
      if (openTagStart > i) parts.push({ type: 'text', value: s.substring(i, openTagStart) });
      var endClose = findMatchingClosingDiv(s, openTagStart);
      if (endClose === -1) {
        /* Incomplete block (e.g. during streaming): render as HTML so table/button appear progressively */
        parts.push({ type: 'html', value: s.substring(openTagStart) });
        break;
      }
      parts.push({ type: 'html', value: s.substring(openTagStart, endClose + 6) });
      i = endClose + 6;
    }
    return parts;
  }

  /** Format text for display; supports comment blocks and raw tool HTML (table, button, mc). */
  function formatMessageContent(text) {
    if (!text) return '';
    var blockStart = '<!-- agentify-block -->';
    var blockEnd = '<!-- /agentify-block -->';
    var parts = [];
    var remaining = String(text);
    var idx;
    while ((idx = remaining.indexOf(blockStart)) !== -1) {
      parts.push({ type: 'text', value: remaining.substring(0, idx) });
      remaining = remaining.substring(idx + blockStart.length);
      var endIdx = remaining.indexOf(blockEnd);
      if (endIdx === -1) {
        parts.push({ type: 'text', value: remaining });
        remaining = '';
        break;
      }
      parts.push({ type: 'html', value: remaining.substring(0, endIdx).trim() });
      remaining = remaining.substring(endIdx + blockEnd.length);
    }
    if (remaining) parts.push({ type: 'text', value: remaining });
    var out = '';
    for (var i = 0; i < parts.length; i++) {
      if (parts[i].type === 'html') {
        out += parts[i].value;
      } else {
        var subParts = splitKnownHtmlBlocks(parts[i].value);
        for (var j = 0; j < subParts.length; j++) {
          if (subParts[j].type === 'html') {
            out += subParts[j].value;
          } else {
            var s = escapeHtml(subParts[j].value);
            s = s.replace(/\n/g, '<br>');
            s = s.replace(/\*\*([\s\S]+?)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
            s = s.replace(/`([^`]+)`/g, '<code class="agentify-inline-code">$1</code>');
            s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (_, label, url) {
              var safeUrl = url.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
              return '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer" class="agentify-msg-link">' + label + '</a>';
            });
            out += s;
          }
        }
      }
    }
    return out;
  }

  function formatMessageTime(createdAt) {
    if (!createdAt) return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    var d = new Date(createdAt);
    if (isNaN(d.getTime())) return createdAt;
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  }

  function addMessageToDOM(role, content, time) {
    var timeStr = formatMessageTime(time);
    var dir = getTextDirection(content);
    if (role === 'user') {
      var html = '<div class="agentify-message agentify-user" role="listitem">' +
        '<div class="agentify-message-avatar" aria-hidden="true"><span class="material-symbols-outlined">person</span></div>' +
        '<div class="agentify-message-bubble">' +
        '<div class="agentify-message-text" dir="' + dir + '">' + formatMessageContent(content) + '</div>' +
        '<div class="agentify-message-time">' + escapeHtml(timeStr) + '</div></div></div>';
      messagesEl.insertAdjacentHTML('beforeend', html);
    } else {
      var html = '<div class="agentify-message agentify-assistant" role="listitem">' +
        '<div class="agentify-message-avatar" aria-hidden="true"><span class="material-symbols-outlined">auto_awesome</span></div>' +
        '<div class="agentify-message-bubble">' +
        '<div class="agentify-message-text" dir="' + dir + '">' + formatMessageContent(content) + '</div>' +
        '<div class="agentify-message-time">' + timeStr + '</div></div></div>';
      messagesEl.insertAdjacentHTML('beforeend', html);
    }
    messagesEl.classList.add('agentify-has-messages');
    scrollToBottom();
  }

  function addThinkingMessage() {
    clearAllEvents();
    currentStreamRawContent = '';
    var html = '<div class="agentify-message agentify-assistant agentify-thinking" id="agentify-thinking-msg" role="listitem" aria-busy="true">' +
      '<div class="agentify-message-avatar" aria-hidden="true"><span class="material-symbols-outlined">auto_awesome</span></div>' +
      '<div class="agentify-message-bubble">' +
      '<div class="agentify-thinking-inner" id="agentify-thinking-inner">' +
      '<span class="agentify-thinking-text">Thinking</span>' +
      '<div class="agentify-thinking-dots" aria-hidden="true"><span></span><span></span><span></span></div></div>' +
      '<div class="agentify-thinking-stream" id="agentify-thinking-stream" aria-live="polite" style="display:none;"></div>' +
      '<div class="agentify-thinking-events" id="agentify-thinking-events" aria-live="polite"><ul class="agentify-thinking-events-list" id="agentify-thinking-events-list"></ul></div></div></div>';
    messagesEl.insertAdjacentHTML('beforeend', html);
    scrollToBottom();
  }

  /** Show event in thinking panel: use description for known types, otherwise show label as-is */
  function getEventDisplayLabel(labelOrType) {
    if (!labelOrType) return '';
    var s = String(labelOrType).trim();
    return EVENT_TYPE_DESCRIPTIONS[s] || s;
  }

  function pushEvent(labelOrType) {
    if (!labelOrType || !String(labelOrType).trim()) return;
    var label = getEventDisplayLabel(labelOrType);
    if (recentEvents.length && recentEvents[recentEvents.length - 1] === label) return;
    recentEvents.push(label);
    renderThinkingEvents();
  }

  function renderThinkingEvents() {
    var listEl = document.getElementById('agentify-thinking-events-list');
    var containerEl = document.getElementById('agentify-thinking-events');
    if (!listEl || !containerEl) return;
    var maxShow = 1; // Only show the last event
    var toShow = recentEvents.slice(-maxShow);
    listEl.innerHTML = toShow.map(function (ev) {
      return '<li class="agentify-thinking-event-item">' + escapeHtml(ev) + '</li>';
    }).join('') +
      '<li class="agentify-thinking-event-item agentify-thinking-event-loading" aria-hidden="true">' +
      '<span class="agentify-thinking-event-skeleton"></span></li>';
    containerEl.style.display = toShow.length ? '' : 'none';
  }

  /** Clear all events: reset array and hide container. */
  function clearAllEvents() {
    recentEvents = [];
    var listEl = document.getElementById('agentify-thinking-events-list');
    var containerEl = document.getElementById('agentify-thinking-events');
    if (listEl) listEl.innerHTML = '';
    if (containerEl) containerEl.style.display = 'none';
  }

  function showStreamAndAppendToken(token) {
    var streamEl = document.getElementById('agentify-thinking-stream');
    var innerEl = document.getElementById('agentify-thinking-inner');
    if (!streamEl) return;
    if (streamEl.style.display === 'none') {
      streamEl.style.display = '';
      if (innerEl) innerEl.style.display = 'none';
      streamEl.setAttribute('dir', getTextDirection(token || ''));
    }
    currentStreamRawContent = (currentStreamRawContent || '') + (typeof token === 'string' ? token : '');
    streamEl.innerHTML = formatMessageContent(currentStreamRawContent);
    scrollToBottom();
  }

  /** Get raw stream text (for final save/morph). Prefers currentStreamRawContent so HTML is preserved. */
  function getStreamRawContent() {
    if (currentStreamRawContent != null && String(currentStreamRawContent).trim()) return currentStreamRawContent.trim();
    var streamEl = document.getElementById('agentify-thinking-stream');
    return (streamEl && streamEl.textContent && streamEl.textContent.trim()) ? streamEl.textContent.trim() : '';
  }

  function updateThinkingUI(statusOrText) {
    var raw = typeof statusOrText === 'string' ? statusOrText : (statusOrText && statusOrText.currentAction) || 'Thinking';
    pushEvent(raw);
    var text = raw.indexOf('Using tool:') === 0 ? 'Step: ' + raw : raw;
    var el = document.getElementById('agentify-thinking-msg');
    if (!el) {
      // If thinking element doesn't exist, try to find it in any assistant message
      var allMessages = messagesEl ? messagesEl.querySelectorAll('.agentify-message.agentify-assistant') : [];
      for (var i = allMessages.length - 1; i >= 0; i--) {
        var msg = allMessages[i];
        var bubble = msg.querySelector('.agentify-message-bubble');
        if (bubble) {
          var existingTextEl = bubble.querySelector('.agentify-thinking-text');
          if (existingTextEl) {
            existingTextEl.textContent = text || 'Thinking';
            return;
          }
        }
      }
      return;
    }
    var bubble = el.querySelector('.agentify-message-bubble');
    if (!bubble) return;
    var textEl = bubble.querySelector('.agentify-thinking-text');
    if (textEl) {
      textEl.textContent = text || 'Thinking';
    } else {
      // Create thinking-text if it doesn't exist (shouldn't happen, but safety check)
      textEl = document.createElement('span');
      textEl.className = 'agentify-thinking-text';
      textEl.textContent = text || 'Thinking';
      bubble.insertBefore(textEl, bubble.firstChild);
    }
  }

  /** Show error strip below thinking content (small div + Try again). Does not replace thinking with a full message. */
  function showThinkingError(msg) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) return;
    var bubble = thinkingEl.querySelector('.agentify-message-bubble');
    if (!bubble) return;
    var existing = bubble.querySelector('.agentify-thinking-error-wrap');
    if (existing) existing.remove();
    var wrap = document.createElement('div');
    wrap.className = 'agentify-thinking-error-wrap';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'agentify-try-again-btn';
    btn.textContent = 'Try again';
    var span = document.createElement('span');
    span.className = 'agentify-thinking-error-text';
    span.textContent = msg || 'An error occurred.';
    wrap.appendChild(btn);
    wrap.appendChild(span);
    bubble.appendChild(wrap);
    scrollToBottom();
  }

  /** Split assistant content: "…text…I encountered an error: …" → { messageContent, errorMessage }. */
  function parseAssistantContentWithError(content) {
    if (!content || typeof content !== 'string') return { messageContent: '', errorMessage: null };
    var prefix = 'I encountered an error:';
    var idx = content.indexOf(prefix);
    if (idx === -1) return { messageContent: content.trim(), errorMessage: null };
    var messageContent = content.substring(0, idx).trim();
    var errorMessage = content.substring(idx + prefix.length).trim();
    return { messageContent: messageContent, errorMessage: errorMessage || 'An error occurred.' };
  }

  /** Build error box HTML (small box under message + Try again). */
  function buildMessageErrorBoxHtml(errorMsg) {
    return '<div class="agentify-message-error-wrap">' +
      '<button type="button" class="agentify-try-again-btn">Try again</button>' +
      '<span class="agentify-message-error-text">' + escapeHtml(errorMsg || 'An error occurred.') + '</span></div>';
  }

  /** Replace thinking block with final reply (used when no stream occurred, e.g. error before stream). */
  function replaceThinkingWithReply(text, isError) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) return;
    var bubble = thinkingEl.querySelector('.agentify-message-bubble');
    if (!bubble) return;
    
    // Preserve agentify-thinking-text - get current text or default to "Thinking"
    var existingThinkingText = bubble.querySelector('.agentify-thinking-text');
    var thinkingTextValue = existingThinkingText ? existingThinkingText.textContent.trim() : 'Thinking';
    
    var time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    var parsed = parseAssistantContentWithError(text || '');
    var errorMsg = parsed.errorMessage || (isError ? (text || 'Something went wrong.') : null);
    var mainContent = parsed.messageContent || (!isError && text ? text : '');
    
    // Remove thinking inner, stream, and events, but keep structure
    var innerEl = bubble.querySelector('.agentify-thinking-inner');
    var streamEl = bubble.querySelector('.agentify-thinking-stream');
    var eventsEl = bubble.querySelector('.agentify-thinking-events');
    var errorWrapEl = bubble.querySelector('.agentify-thinking-error-wrap');
    if (innerEl) innerEl.remove();
    if (streamEl) streamEl.remove();
    if (eventsEl) eventsEl.remove();
    if (errorWrapEl) errorWrapEl.remove();
    
    // Update element classes
    thinkingEl.classList.remove('agentify-thinking');
    thinkingEl.removeAttribute('aria-busy');
    thinkingEl.removeAttribute('id');
    
    // Create and add thinking-text at the top
    var thinkingTextEl = document.createElement('span');
    thinkingTextEl.className = 'agentify-thinking-text';
    thinkingTextEl.textContent = thinkingTextValue || 'Thinking';
    bubble.insertBefore(thinkingTextEl, bubble.firstChild);
    
    if (mainContent) {
      var textDiv = document.createElement('div');
      textDiv.className = 'agentify-message-text';
      textDiv.setAttribute('dir', getTextDirection(mainContent));
      textDiv.innerHTML = formatMessageContent(mainContent);
      bubble.appendChild(textDiv);
    }
    var timeEl = document.createElement('div');
    timeEl.className = 'agentify-message-time';
    timeEl.textContent = time;
    bubble.appendChild(timeEl);
    if (errorMsg) {
      var wrap = document.createElement('div');
      wrap.className = 'agentify-message-error-wrap';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'agentify-try-again-btn';
      btn.textContent = 'Try again';
      var span = document.createElement('span');
      span.className = 'agentify-message-error-text';
      span.textContent = errorMsg;
      wrap.appendChild(btn);
      wrap.appendChild(span);
      bubble.appendChild(wrap);
    }
    clearAllEvents();
    scrollToBottom();
  }

  /** Morph the thinking block (with streamed content) into the final assistant message. Errors go in a small box below with Try again. */
  function morphThinkingToReply(text, isError) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) {
      if (text || isError) {
        var parsed = parseAssistantContentWithError(text || '');
        var errMsg = parsed.errorMessage || (isError ? 'Something went wrong.' : null);
        var main = parsed.messageContent || (isError ? '' : (text || ''));
        addMessageToDOM('assistant', main, new Date());
        if (errMsg && messagesEl && messagesEl.lastElementChild) {
          var bubble = messagesEl.lastElementChild.querySelector('.agentify-message-bubble');
          if (bubble) bubble.insertAdjacentHTML('beforeend', buildMessageErrorBoxHtml(errMsg));
        }
        if (messagesEl) messagesEl.classList.add('agentify-has-messages');
        scrollToBottom();
      }
      return;
    }
    if (!text && !isError) return;
    if (!text && isError) text = 'An error occurred.';
    var bubble = thinkingEl.querySelector('.agentify-message-bubble');
    if (!bubble) {
      replaceThinkingWithReply(text, isError);
      return;
    }
    var parsed = parseAssistantContentWithError(text);
    var errorMsg = parsed.errorMessage || (isError ? (text || 'An error occurred.') : null);
    var mainContent = parsed.messageContent;

    var time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    thinkingEl.classList.remove('agentify-thinking');
    thinkingEl.removeAttribute('aria-busy');
    thinkingEl.removeAttribute('id');
    
    // Preserve agentify-thinking-text - get current text or default to "Thinking"
    var existingThinkingText = bubble.querySelector('.agentify-thinking-text');
    var thinkingTextValue = existingThinkingText ? existingThinkingText.textContent.trim() : 'Thinking';
    
    // Remove thinking inner, stream, and events, but keep structure
    var innerEl = bubble.querySelector('.agentify-thinking-inner');
    var streamEl = bubble.querySelector('.agentify-thinking-stream');
    var eventsEl = bubble.querySelector('.agentify-thinking-events');
    var errorWrapEl = bubble.querySelector('.agentify-thinking-error-wrap');
    if (innerEl) innerEl.remove();
    if (streamEl) streamEl.remove();
    if (eventsEl) eventsEl.remove();
    if (errorWrapEl) errorWrapEl.remove();
    
    // Create and add thinking-text at the top
    var thinkingTextEl = document.createElement('span');
    thinkingTextEl.className = 'agentify-thinking-text';
    thinkingTextEl.textContent = thinkingTextValue || 'Thinking';
    bubble.insertBefore(thinkingTextEl, bubble.firstChild);
    
    if (mainContent) {
      var textDiv = document.createElement('div');
      textDiv.className = 'agentify-message-text';
      textDiv.setAttribute('dir', getTextDirection(mainContent));
      textDiv.innerHTML = formatMessageContent(mainContent);
      bubble.appendChild(textDiv);
    }
    var timeEl = document.createElement('div');
    timeEl.className = 'agentify-message-time';
    timeEl.textContent = time;
    bubble.appendChild(timeEl);
    if (errorMsg) {
      var wrap = document.createElement('div');
      wrap.className = 'agentify-message-error-wrap';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'agentify-try-again-btn';
      btn.textContent = 'Try again';
      var span = document.createElement('span');
      span.className = 'agentify-message-error-text';
      span.textContent = errorMsg;
      wrap.appendChild(btn);
      wrap.appendChild(span);
      bubble.appendChild(wrap);
    }
    clearAllEvents();
    scrollToBottom();
  }

  function setSendLoading(loading) {
    if (!btnSend || !userInput) return;
    userInput.disabled = !!loading;
    if (loading) {
      btnSend.disabled = true;
      btnSend.classList.add('agentify-loading');
      btnSend.setAttribute('hidden', '');
      if (btnStop) {
        btnStop.removeAttribute('hidden');
        btnStop.disabled = false;
      }
    } else {
      btnSend.disabled = false;
      btnSend.classList.remove('agentify-loading');
      btnSend.removeAttribute('hidden');
      if (btnStop) {
        btnStop.setAttribute('hidden', '');
        btnStop.disabled = true;
      }
    }
  }

  function stopGeneration() {
    if (agentifyAgent && agentifyAgent.streamHandler && typeof agentifyAgent.streamHandler.stopStream === 'function') {
      agentifyAgent.streamHandler.stopStream();
    }
    setSendLoading(false);
    if (userInput) userInput.focus();
  }

  function setPlaceholderVisible(visible) {
    if (!chatListPlaceholder || !chatItems) return;
    if (visible) {
      chatListPlaceholder.removeAttribute('hidden');
      chatListPlaceholder.classList.remove('agentify-hidden');
      chatItems.setAttribute('hidden', '');
      chatItems.classList.add('agentify-hidden');
    } else {
      chatListPlaceholder.setAttribute('hidden', '');
      chatListPlaceholder.classList.add('agentify-hidden');
      chatItems.removeAttribute('hidden');
      chatItems.classList.remove('agentify-hidden');
    }
  }

  function showChatListSkeleton() {
    if (!chatItems || !chatListPlaceholder) return;
    setPlaceholderVisible(false);
    chatItems.className = 'agentify-chat-items agentify-chat-list-skeleton';
    chatItems.innerHTML =
      '<li><span class="agentify-skeleton agentify-skeleton-line"></span></li>' +
      '<li><span class="agentify-skeleton agentify-skeleton-line"></span></li>' +
      '<li><span class="agentify-skeleton agentify-skeleton-line"></span></li>';
  }

  function renderChatList(chats) {
    if (!chatItems || !chatListPlaceholder) return;
    chatItems.className = 'agentify-chat-items';
    if (!chats || chats.length === 0) {
      chatItems.innerHTML = '';
      setPlaceholderVisible(true);
      return;
    }
    setPlaceholderVisible(false);
    chatItems.innerHTML = chats.map(function (c) {
      var title = c.title && c.title.trim() ? escapeHtml(c.title) : 'Chat #' + c.id;
      var active = currentChatId === c.id ? ' agentify-active' : '';
      return '<li><div class="agentify-chat-item-wrap">' +
        '<button type="button" class="agentify-chat-item' + active + '" data-chat-id="' + c.id + '" aria-label="' + escapeHtml(title) + '">' +
        '<span class="material-symbols-outlined">forum</span><span class="agentify-chat-item-title">' + title + '</span></button>' +
        '<button type="button" class="agentify-chat-item-delete" data-chat-id="' + c.id + '" aria-label="Delete chat">' +
        '<span class="material-symbols-outlined">close</span></button></div></li>';
    }).join('');

    chatItems.querySelectorAll('.agentify-chat-item').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-chat-id'), 10);
        if (!isNaN(id)) selectChat(id);
      });
    });
    chatItems.querySelectorAll('.agentify-chat-item-delete').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = parseInt(btn.getAttribute('data-chat-id'), 10);
        if (isNaN(id) || !restUrl) return;
        if (currentChatId === id) startNewChat();
        api('/chats/' + id, { method: 'DELETE' })
          .then(function (res) {
            if (res.ok) loadChats();
          });
      });
    });
  }

  function loadChats() {
    if (!restUrl) {
      renderChatList([]);
      return;
    }
    showChatListSkeleton();
    api('/chats')
      .then(function (res) {
        var list = (res.data && res.data.chats) ? res.data.chats : [];
        renderChatList(list);
      })
      .catch(function () {
        renderChatList([]);
      });
  }

  /** Render tasks under the last assistant message (outside bubble). No label, no "no tasks" text. Loading skeleton when loading. */
  function renderTasksUnderMessage(tasks, isLoading) {
    if (!messagesEl) return;
    var list = Array.isArray(tasks) ? tasks : [];
    var existing = messagesEl.querySelector('.agentify-message-tasks');
    if (existing) existing.remove();
    if (isLoading) {
      var lastAssistant = messagesEl.querySelector('.agentify-message.agentify-assistant:not(.agentify-thinking)');
      if (!lastAssistant) lastAssistant = messagesEl.querySelector('.agentify-message.agentify-assistant');
      if (lastAssistant) {
        var wrap = document.createElement('div');
        wrap.className = 'agentify-message-tasks agentify-message-tasks-loading';
        wrap.setAttribute('aria-hidden', 'true');
        wrap.innerHTML = '<ul class="agentify-message-tasks-list">' +
          '<li class="agentify-message-task-skeleton"><span class="agentify-task-skeleton-line"></span></li>' +
          '<li class="agentify-message-task-skeleton"><span class="agentify-task-skeleton-line"></span></li>' +
          '</ul>';
        lastAssistant.appendChild(wrap);
      }
      return;
    }
    if (list.length === 0) return;
    var lastAssistant = messagesEl.querySelector('.agentify-message.agentify-assistant:not(.agentify-thinking)');
    if (!lastAssistant) lastAssistant = messagesEl.querySelector('.agentify-message.agentify-assistant');
    if (!lastAssistant) return;
    var lastFour = list.slice(-4);
    var wrap = document.createElement('div');
    wrap.className = 'agentify-message-tasks';
    var title = function (t) {
      var s = (t && (t.title != null || t.name != null)) ? String(t.title != null ? t.title : t.name).trim() : '';
      return s || '\u2014';
    };
    wrap.innerHTML = '<ul class="agentify-message-tasks-list">' + lastFour.map(function (t) {
      var taskTitle = escapeHtml(title(t));
      var status = (t.status && String(t.status).trim()) ? escapeHtml(String(t.status).trim()) : 'pending';
      return '<li class="agentify-message-task" data-status="' + status + '">' +
        '<span class="material-symbols-outlined agentify-message-task-icon" aria-hidden="true">' +
        (status === 'completed' ? 'check_circle' : status === 'cancelled' ? 'cancel' : 'radio_button_unchecked') + '</span>' +
        '<span class="agentify-message-task-title">' + taskTitle + '</span></li>';
    }).join('') + '</ul>';
    lastAssistant.appendChild(wrap);
  }

  function loadTasks(chatId) {
    if (chatId == null) {
      currentTasksList = [];
      renderTasksUnderMessage([], false);
      return;
    }
    renderTasksUnderMessage([], true);
    function applyList(apiTasks) {
      var list = Array.isArray(apiTasks) ? apiTasks : [];
      if (agentifyAgent && list.length === 0) {
        try {
          var fromAgent = agentifyAgent.getTasks({ chat_id: String(chatId) });
          if (fromAgent && fromAgent.length > 0) {
            var normalized = fromAgent.map(function (t) {
              return {
                id: t.id,
                title: (t && t.title != null) ? String(t.title) : (t && t.name != null ? String(t.name) : ''),
                status: (t && t.status) ? t.status : 'pending',
                description: t && t.description ? t.description : null,
              };
            }).filter(function (t) {
              var titleStr = (t.title || '').trim();
              return titleStr.indexOf('chat:') !== 0 && titleStr.indexOf('tool_followup:') !== 0;
            });
            list = normalized.slice(-4);
          }
        } catch (e) {}
      }
      currentTasksList = list;
      renderTasksUnderMessage(list, false);
    }
    if (!restUrl) {
      applyList([]);
      return;
    }
    api('/tasks?chat_id=' + chatId)
      .then(function (res) {
        var list = (res.data && res.data.tasks) ? res.data.tasks : [];
        applyList(list);
      })
      .catch(function () {
        currentTasksList = [];
        applyList([]);
      });
  }

  function showMessagesLoadingSkeleton() {
    if (!welcomeEl) welcomeEl.style.display = 'none';
    messagesEl.querySelectorAll('.agentify-message').forEach(function (el) { el.remove(); });
    messagesEl.classList.remove('agentify-has-messages');
    var wrap = document.createElement('div');
    wrap.className = 'agentify-messages-loading';
    wrap.id = 'agentify-messages-loading';
    wrap.innerHTML = '<div class="agentify-msg-skeleton">' +
      '<div class="agentify-msg-skeleton-avatar"></div>' +
      '<div class="agentify-msg-skeleton-bubble"></div></div>' +
      '<div class="agentify-msg-skeleton agentify-msg-skeleton-right">' +
      '<div class="agentify-msg-skeleton-avatar"></div>' +
      '<div class="agentify-msg-skeleton-bubble"></div></div>' +
      '<div class="agentify-msg-skeleton">' +
      '<div class="agentify-msg-skeleton-avatar"></div>' +
      '<div class="agentify-msg-skeleton-bubble"></div></div>';
    messagesEl.appendChild(wrap);
  }

  function clearMessagesPanel() {
    var loading = document.getElementById('agentify-messages-loading');
    if (loading) loading.remove();
    messagesEl.querySelectorAll('.agentify-message').forEach(function (el) { el.remove(); });
    messagesEl.classList.remove('agentify-has-messages');
    if (welcomeEl) welcomeEl.style.display = '';
  }

  function renderMessages(messages) {
    var loading = document.getElementById('agentify-messages-loading');
    if (loading) loading.remove();
    messagesEl.querySelectorAll('.agentify-message').forEach(function (el) { el.remove(); });
    if (welcomeEl) welcomeEl.style.display = 'none';
    if (!messages || messages.length === 0) {
    } else {
      messages.forEach(function (m) {
        if (m.role === 'user') {
          addMessageToDOM('user', m.content || '', m.created_at);
        } else if (m.role === 'assistant') {
          var parsed = parseAssistantContentWithError(m.content || '');
          var displayContent = parsed.errorMessage ? parsed.messageContent : (m.content || '');
          addMessageToDOM('assistant', displayContent, m.created_at);
          if (parsed.errorMessage && messagesEl && messagesEl.lastElementChild) {
            var bubble = messagesEl.lastElementChild.querySelector('.agentify-message-bubble');
            if (bubble) bubble.insertAdjacentHTML('beforeend', buildMessageErrorBoxHtml(parsed.errorMessage));
          }
        }
      });
    }
    messagesEl.classList.add('agentify-has-messages');
    scrollToBottom();
  }

  function selectChat(chatId) {
    currentChatId = chatId;
    showMessagesLoadingSkeleton();
    loadTasks(chatId);
    api('/chats/' + chatId + '/messages')
      .then(function (res) {
        var list = (res.data && res.data.messages) ? res.data.messages : [];
        currentChatMessages = list.map(function (m) { return { role: m.role, content: m.content || '' }; });
        renderMessages(list);
        if (agentifyAgent && currentChatMessages.length) {
          agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
        }
        renderTasksUnderMessage(currentTasksList, false);
      })
      .catch(function () {
        currentChatMessages = [];
        renderMessages([]);
      })
      .finally(function () {
        loadChats();
      });
  }

  function startNewChat() {
    currentChatId = null;
    currentChatMessages = [];
    clearMessagesPanel();
    loadTasks(null);
    userInput.value = '';
    resizeTextarea();
    if (chatItems) {
      chatItems.querySelectorAll('.agentify-chat-item').forEach(function (b) { b.classList.remove('agentify-active'); });
    }
    userInput.focus();
  }

  function ensureCurrentChat() {
    return new Promise(function (resolve) {
      if (currentChatId !== null) {
        resolve(currentChatId);
        return;
      }
      if (!restUrl) {
        resolve(null);
        return;
      }
      api('/chats', { method: 'POST', body: JSON.stringify({}) })
        .then(function (res) {
          var id = res.data && res.data.chat_id != null ? res.data.chat_id : null;
          if (id != null) {
            currentChatId = id;
            currentChatMessages = [];
            loadChats();
          }
          resolve(id);
        })
        .catch(function () {
          resolve(null);
        });
    });
  }

  /** Lazy-init Agentify: load module, fetch settings, create agent, add tools. Agent is exposed on window.agent. */
  function getAgent() {
    if (agentifyAgent) return Promise.resolve(agentifyAgent);
    if (!agentifyBaseUrl || !restUrl) return Promise.reject(new Error('Agentify or REST not configured'));
    var base = agentifyBaseUrl.replace(/\/$/, '') + '/';
    return import(base + 'agentify/index.js').then(function (mod) {
      var Agentify = mod.Agentify;
      return api('/settings').then(function (res) {
        var settings = res.data || {};
        var modelKey = settings.model || 'deepseek|deepseek-chat';
        var parts = modelKey.split('|');
        var providerKey = (parts[0] || 'deepseek').toLowerCase();
        var provider = providerKey === 'chatgpt' ? 'openai' : providerKey;
        var model = parts[1] || 'deepseek-chat';
        var apiKey = (settings.api_keys && settings.api_keys[providerKey]) ? settings.api_keys[providerKey] : '';
        if (!apiKey) throw new Error('API key not set for this model. Use Settings.');
        var apiUrl = PROVIDER_URLS[providerKey] || PROVIDER_URLS[provider] || PROVIDER_URLS.deepseek;
        var streamEnabled = typeof window.ReadableStream !== 'undefined';
        if (!streamEnabled) {
          try { if (window.console && window.console.warn) window.console.warn('Agentify: ReadableStream not supported, streaming disabled.'); } catch (_) {}
        }
        var agent = new Agentify({
          provider: provider,
          model: model,
          apiKey: apiKey,
          apiUrl: apiUrl,
          stream: streamEnabled,
          useHistory: true,
          includeHistory: true,
        });
        var baseInstruction = settings.system_instruction || '';
        var titleInstruction = '\n\nIMPORTANT: For every new chat conversation, you MUST call the set_chat_title tool exactly once to set a descriptive title for the chat. This helps users organize and find their conversations. Call set_chat_title early in the conversation, ideally after understanding the user\'s main request or topic.';
        var taskInstruction = '\n\nTASKS: When the user asks you to do something (e.g. check plugins, run a query, create or change something), you MUST create a task for it using the create_task tool. Work through tasks one by one: use get_tasks to see the list, do each task, then mark it completed with complete_task and continue. Always create a task for the work the user wants done, update progress by completing tasks as you finish them, and use the task tools (create_task, get_tasks, complete_task) consistently.';
        agent.setInstruction(baseInstruction + titleInstruction + taskInstruction);
        agent.onThinkingChange(function (status) {
          if (status && status.currentAction) pushEvent(status.currentAction);
          updateThinkingUI(status);
        });
        window.agent = agent;
        return agent.addTool({
          name: 'set_chat_title',
          description: 'Set the title of the current chat. You MUST call this tool exactly once for every new chat conversation to give it a descriptive title. Use this when the chat has no custom title yet (title is empty or "new" or "new #id"). Create a short, descriptive title that summarizes the main topic or purpose of the conversation.',
          instruction: 'You MUST call this tool exactly once for every new chat. Use when the chat title is empty or "new" or "new #id". Call early in the conversation after understanding the user\'s main request. Create a short, descriptive title (one line, 3-8 words) that captures the essence of the conversation.',
          parameters: {
            title: { type: 'string', description: 'Short descriptive title for the chat', required: true },
          },
          execute: function (params) {
            if (!currentChatId || !restUrl) return Promise.resolve({ success: false, error: 'No chat' });
            return api('/chats/' + currentChatId, {
              method: 'PATCH',
              body: JSON.stringify({ title: (params && params.title) ? String(params.title).trim() : '' }),
            }).then(function (res) {
              if (res.ok && res.data) return res.data;
              return { success: false, message: (res.data && res.data.message) || 'Failed' };
            });
          },
        }).then(function () {
          return agent.addTool({
            name: 'query_database',
            description: 'Run a read-only SQL query (SELECT only) against the WordPress database. Use for reading data from wp_posts, wp_users, wp_options, etc. Table names usually have a prefix (e.g. wp_). Single statement only.',
            instruction: 'Use only for SELECT queries. Run one statement at a time. Present the result to the user clearly.',
            parameters: {
              query: { type: 'string', description: 'The SELECT SQL query to run', required: true },
            },
            execute: function (params) {
              if (!restUrl) return Promise.resolve({ success: false, message: 'REST not configured.' });
              var q = (params && params.query) ? String(params.query).trim() : '';
              if (!q) return Promise.resolve({ success: false, message: 'Query is required.' });
              return api('/tools/db-query', { method: 'POST', body: JSON.stringify({ query: q }) }).then(function (res) {
                if (res.ok && res.data && res.data.success) {
                  return { success: true, data: res.data.data || [], count: res.data.count != null ? res.data.count : 0 };
                }
                return { success: false, message: (res.data && res.data.message) || 'Query failed.' };
              });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'execute_database',
            description: 'Run a write SQL query (INSERT, UPDATE, DELETE, REPLACE) on the WordPress database. Only works if the admin has enabled "Allow database changes" in Assistant Settings. If that setting is disabled, tell the user they must enable it in Settings to allow database changes.',
            instruction: 'Use only for INSERT, UPDATE, DELETE, or REPLACE. One statement at a time. If the tool returns that database changes are disabled, tell the user to enable "Allow database changes" in the Assistant Settings.',
            parameters: {
              query: { type: 'string', description: 'The INSERT/UPDATE/DELETE/REPLACE SQL query', required: true },
            },
            execute: function (params) {
              if (!restUrl) return Promise.resolve({ success: false, message: 'REST not configured.' });
              var q = (params && params.query) ? String(params.query).trim() : '';
              if (!q) return Promise.resolve({ success: false, message: 'Query is required.' });
              return api('/tools/db-execute', { method: 'POST', body: JSON.stringify({ query: q }) }).then(function (res) {
                if (res.ok && res.data && res.data.success) {
                  return { success: true, affected_rows: res.data.affected_rows != null ? res.data.affected_rows : 0 };
                }
                var msg = (res.data && res.data.message) ? res.data.message : 'Execution failed.';
                if (res.data && res.data.code === 'db_write_disabled') {
                  return { success: false, db_write_disabled: true, message: msg };
                }
                return { success: false, message: msg };
              });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'get_plugins',
            description: 'Get the list of plugins installed on the WordPress site. Returns each plugin with its name, status (active or inactive), directory (plugin slug/folder), and version. Use when the user asks for installed plugins, plugin list, or which plugins are active.',
            instruction: 'Call with no parameters. Use the result to show the user the list of plugins and their status.',
            parameters: {},
            execute: function () {
              if (!restUrl) return Promise.resolve({ success: false, message: 'REST not configured.' });
              return api('/tools/plugins').then(function (res) {
                if (res.ok && res.data && res.data.success) {
                  return { success: true, plugins: res.data.plugins || [], count: res.data.count != null ? res.data.count : 0 };
                }
                return { success: false, message: (res.data && res.data.message) || 'Failed to get plugins.' };
              });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'create_task',
            description: 'Create a task (todo) for the user. You MUST call this whenever the user asks to remember something, add a todo, or for any work item (e.g. "یادت باشه", "تسک بساز", "add a task"). Tasks are displayed to the user under your message. You MUST always provide a clear, short title (required): the title is what the user sees in the task list. Without a title the task appears empty. Use a descriptive title for each task (e.g. "Get plugin list", "Run database query", "Set chat title").',
            instruction: 'Always call create_task with a clear, short title. The title is required and is shown to the user—never leave it empty. Use one task per work item. Example: create_task({ title: "Fetch user list from database", description: "optional details" }).',
            parameters: {
              title: { type: 'string', description: 'Required. Short, clear title shown to the user in the task list (e.g. "Get plugin list", "Run query"). Never omit.', required: true },
              description: { type: 'string', description: 'Optional longer description or notes', required: false },
            },
            execute: function (params) {
              try {
                var title = (params && params.title) ? String(params.title).trim() : '';
                if (!title) return Promise.resolve({ success: false, message: 'Title is required.' });
                var taskData = { title: title, status: 'pending' };
                if (params && params.description && String(params.description).trim()) taskData.description = String(params.description).trim();
                if (currentChatId != null) taskData.chat_id = String(currentChatId);
                var task = agent.createTask(taskData);
                if (restUrl && currentChatId != null) {
                  api('/tasks', { method: 'POST', body: JSON.stringify({ title: task.title, description: task.description, chat_id: currentChatId }) })
                    .then(function () { loadTasks(currentChatId); })
                    .catch(function () {});
                }
                return Promise.resolve({ success: true, task_id: task.id, title: task.title, message: 'Task created.' });
              } catch (err) {
                return Promise.resolve({ success: false, message: err.message || 'Failed to create task.' });
              }
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'get_tasks',
            description: 'Get list of tasks for the current chat. Use this when user asks about tasks, todos, or what needs to be done.',
            instruction: 'Use this tool to show the user their tasks. Returns tasks for the current chat.',
            parameters: {
              status: { type: 'string', description: 'Filter by status: "pending" or "completed". Leave empty to get all tasks.', required: false },
            },
            execute: function (params) {
              try {
                var filter = {};
                if (currentChatId != null) filter.chat_id = String(currentChatId);
                if (params && params.status && (params.status === 'pending' || params.status === 'completed')) {
                  filter.status = params.status;
                }
                var tasks = agent.getTasks(filter);
                return Promise.resolve({ success: true, tasks: tasks, count: tasks.length });
              } catch (err) {
                return Promise.resolve({ success: false, message: err.message || 'Failed to get tasks.' });
              }
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'complete_task',
            description: 'Mark a task as completed. Use this when the user says a task is done, finished, or completed.',
            instruction: 'Use this tool to mark a task as completed. You need the task ID from get_tasks.',
            parameters: {
              task_id: { type: 'string', description: 'The ID of the task to mark as completed', required: true },
            },
            execute: function (params) {
              try {
                var taskId = (params && params.task_id) ? String(params.task_id).trim() : '';
                if (!taskId) return Promise.resolve({ success: false, message: 'Task ID is required.' });
                agent.taskManager.updateTaskStatus(taskId, 'completed');
                if (restUrl && currentChatId != null) {
                  api('/tasks/' + taskId, { method: 'PATCH', body: JSON.stringify({ status: 'completed' }) })
                    .then(function () { loadTasks(currentChatId); })
                    .catch(function () {});
                }
                return Promise.resolve({ success: true, task_id: taskId, message: 'Task marked as completed.' });
              } catch (err) {
                return Promise.resolve({ success: false, message: err.message || 'Failed to complete task.' });
              }
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'render_data_table',
            description: 'Build an HTML table from headers and row data. Use when you have tabular data to show. No backend call. Return the HTML wrapped in <!-- agentify-block --> ... <!-- /agentify-block --> in your response so the UI renders it.',
            instruction: 'Call with headers array and rows (array of arrays). Include the returned HTML in your reply inside <!-- agentify-block --> ... <!-- /agentify-block -->.',
            parameters: {
              headers: { type: 'array', description: 'Array of column header strings', required: true },
              rows: { type: 'array', description: 'Array of rows; each row is an array of cell values (strings or numbers)', required: true },
            },
            execute: function (params) {
              var headers = params && Array.isArray(params.headers) ? params.headers : [];
              var rows = params && Array.isArray(params.rows) ? params.rows : [];
              var esc = function (v) {
                if (v == null) return '';
                var div = document.createElement('div');
                div.textContent = String(v);
                return div.innerHTML;
              };
              var ths = headers.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('');
              var trs = rows.map(function (row) {
                var cells = (Array.isArray(row) ? row : []).map(function (c) { return '<td>' + esc(c) + '</td>'; }).join('');
                return '<tr>' + cells + '</tr>';
              }).join('');
              var html = '<div class="agentify-data-table-wrap"><table class="agentify-data-table"><thead><tr>' + ths + '</tr></thead><tbody>' + trs + '</tbody></table></div>';
              return Promise.resolve({ success: true, html: html });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'multiple_choice_question',
            description: 'Show a 4-option multiple choice question with a confirm button. When the user clicks confirm, their answer is sent to you with conversation history. Use for quizzes, choices, or when you need the user to pick one option. No backend.',
            instruction: 'Call with question and options (array of 4 option strings). Return the html in your response inside <!-- agentify-block --> ... <!-- /agentify-block -->. After user confirms, you will receive their choice as a new message.',
            parameters: {
              question: { type: 'string', description: 'The question text', required: true },
              options: { type: 'array', description: 'Exactly 4 option strings (e.g. ["A) ...", "B) ...", "C) ...", "D) ..."]', required: true },
            },
            execute: function (params) {
              var question = (params && params.question) ? String(params.question).trim() : '';
              var opts = (params && params.options && Array.isArray(params.options)) ? params.options.slice(0, 4) : [];
              var esc = function (v) {
                if (v == null) return '';
                var div = document.createElement('div');
                div.textContent = String(v);
                return div.innerHTML;
              };
              var name = 'agentify-mc-' + Date.now();
              var radios = opts.map(function (opt, i) {
                return '<label class="agentify-mc-option"><input type="radio" name="' + name + '" value="' + esc(opt) + '" data-option-text="' + esc(opt) + '"> <span>' + esc(opt) + '</span></label>';
              }).join('');
              var html = '<div class="agentify-mc-block"><p class="agentify-mc-question">' + esc(question) + '</p><div class="agentify-mc-options">' + radios + '</div><button type="button" class="agentify-mc-confirm agentify-btn">Confirm</button></div>';
              return Promise.resolve({ success: true, html: html });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'render_button',
            description: 'Create a link button to show in the chat. Input: URL and button title. No backend. Return HTML to include in your response inside <!-- agentify-block --> ... <!-- /agentify-block -->.',
            instruction: 'Call with url and title. Include the returned HTML in your reply inside <!-- agentify-block --> ... <!-- /agentify-block -->.',
            parameters: {
              url: { type: 'string', description: 'The link URL', required: true },
              title: { type: 'string', description: 'Button label text', required: true },
            },
            execute: function (params) {
              var url = (params && params.url) ? String(params.url).trim() : '#';
              var title = (params && params.title) ? String(params.title).trim() : 'Link';
              var div = document.createElement('div');
              div.textContent = url;
              var safeUrl = div.innerHTML;
              div.textContent = title;
              var safeTitle = div.innerHTML;
              var html = '<div class="agentify-msg-button-wrap"><a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer" class="agentify-msg-button">' + safeTitle + '</a></div>';
              return Promise.resolve({ success: true, html: html });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'build_site_link',
            description: 'Build a full URL for this WordPress site. Use when user needs a link to the front or admin. No backend. Input: type (front or admin) and path (e.g. "wp-admin/post.php?post=123" or "my-page/"). Returns the full URL.',
            instruction: 'Call with type "front" or "admin" and path. Returns full URL. Use this to give users correct links.',
            parameters: {
              type: { type: 'string', description: 'Either "front" or "admin"', required: true },
              path: { type: 'string', description: 'Path part (e.g. "wp-admin/post.php?post=123" or "contact/"). For front use path like "my-page/".', required: true },
            },
            execute: function (params) {
              var type = (params && params.type) ? String(params.type).trim().toLowerCase() : 'front';
              var path = (params && params.path) ? String(params.path).trim() : '';
              var base = type === 'admin' ? (adminUrl || '') : (siteUrl || '');
              if (!base) return Promise.resolve({ success: false, message: 'Site URL not configured.', url: '' });
              if (path) base = base.replace(/\/?$/, '') + '/' + path.replace(/^\//, '');
              var url = base.replace(/\s/g, '');
              return Promise.resolve({ success: true, url: url });
            },
          });
        }).then(function () {
          return agent.addTool({
            name: 'create_zip_and_upload',
            description: 'Create a ZIP file from a list of files (name + content) and upload it to the WordPress media library. Returns the download URL. Use when the user wants to export data as a ZIP, bundle files for download, or save multiple files as one downloadable archive.',
            instruction: 'Call with files: array of { name: string, content: string }. Optionally filename: string for the ZIP (e.g. "export.zip"). The ZIP is built in the browser, uploaded to WordPress media, and you get back the download URL to give to the user.',
            parameters: {
              files: { type: 'array', description: 'Array of objects with name (file path/name inside ZIP) and content (string). At least one file required.', required: true },
              filename: { type: 'string', description: 'Optional. Name for the ZIP file (e.g. "export.zip", "backup.zip").', required: false },
            },
            execute: function (params) {
              if (!window.JSZip) return Promise.resolve({ success: false, message: 'JSZip not loaded. Cannot create ZIP.' });
              if (!restUrl) return Promise.resolve({ success: false, message: 'REST not configured.' });
              var list = params && params.files && Array.isArray(params.files) ? params.files : [];
              if (list.length === 0) return Promise.resolve({ success: false, message: 'At least one file (name + content) is required.' });
              var zip = new window.JSZip();
              for (var i = 0; i < list.length; i++) {
                var f = list[i];
                var name = (f && f.name != null) ? String(f.name).trim() : '';
                if (!name) continue;
                var content = (f && f.content != null) ? f.content : '';
                zip.file(name, content);
              }
              var zipFilename = (params && params.filename && String(params.filename).trim()) ? String(params.filename).trim() : 'archive.zip';
              if (!/\.zip$/i.test(zipFilename)) zipFilename = zipFilename + '.zip';
              return zip.generateAsync({ type: 'blob' }).then(function (blob) {
                var formData = new FormData();
                formData.append('file', blob, zipFilename);
                return api('/tools/upload-zip', { method: 'POST', body: formData });
              }).then(function (res) {
                if (res.ok && res.data && res.data.success && res.data.url) {
                  return { success: true, url: res.data.url, id: res.data.id, message: res.data.message || 'ZIP uploaded.' };
                }
                return { success: false, message: (res.data && res.data.message) || 'Upload failed.', url: '' };
              }).catch(function (err) {
                return { success: false, message: (err && err.message) || 'Failed to create or upload ZIP.', url: '' };
              });
            },
          });
        }).then(function () {
          agentifyAgent = agent;
          return agent;
        });
      });
    });
  }

  /* Enter = send, Shift+Enter = new line */
  userInput.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    if (e.shiftKey) {
      /* Shift+Enter: insert new line, allow textarea to grow up to 3 lines then scroll */
      e.preventDefault();
      var start = userInput.selectionStart;
      var end = userInput.selectionEnd;
      var val = userInput.value;
      userInput.value = val.substring(0, start) + '\n' + val.substring(end);
      userInput.selectionStart = userInput.selectionEnd = start + 1;
      resizeTextarea();
      return;
    }
    /* Enter: send message */
    e.preventDefault();
    chatForm.requestSubmit();
  });

  var lineHeightPx = 22;
  var textareaMaxLines = 3;
  var textareaMaxHeightPx = lineHeightPx * textareaMaxLines;

  function resizeTextarea() {
    userInput.style.height = 'auto';
    var h = userInput.scrollHeight;
    userInput.style.overflowY = h > textareaMaxHeightPx ? 'auto' : 'hidden';
    userInput.style.height = Math.max(lineHeightPx, Math.min(h, textareaMaxHeightPx)) + 'px';
  }

  userInput.addEventListener('input', resizeTextarea);
  userInput.addEventListener('focus', resizeTextarea);

  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = userInput.value.trim();
    if (!text) return;
    if (!restUrl) {
      addMessageToDOM('user', text);
      replaceThinkingWithReply('Configure REST URL and API keys in Settings.', true);
      return;
    }
    userInput.value = '';
    resizeTextarea();
    var oldThinkingEl = document.getElementById('agentify-thinking-msg');
    if (oldThinkingEl) oldThinkingEl.remove();
    clearAllEvents();
    addMessageToDOM('user', text);
    currentChatMessages.push({ role: 'user', content: text });
    addThinkingMessage();
    setSendLoading(true);

    ensureCurrentChat()
      .then(function (chatId) {
        if (chatId == null) {
          replaceThinkingWithReply('Could not create or use a chat. Try again.', true);
          setSendLoading(false);
          return Promise.reject();
        }
        return getAgent().then(function (agent) {
          var chatIdStr = String(chatId);
          agent.setChatId(chatIdStr);
          agent.chatHistoryManager.updateChatHistory(chatIdStr, currentChatMessages);
          return api('/chat/messages', {
            method: 'POST',
            body: JSON.stringify({ chat_id: chatId, role: 'user', content: text }),
          }).then(function () { return { agent: agent, chatId: chatId }; });
        });
      })
      .then(function (ctx) {
        if (!ctx) return;
        var agent = ctx.agent;
        var chatId = ctx.chatId;
        lastUserMessageForRetry = text;
        var accumulatedAssistantContent = '';
        return agent.chat(text, {
          chatId: String(chatId),
          onToolCall: function (toolCall) {
            pushEvent('tool_call_initiated');
            var label = 'Using tool: ' + (toolCall && toolCall.name ? toolCall.name : 'tool');
            pushEvent(label);
          },
          onThinking: function (thought) {
            if (thought && String(thought).trim()) pushEvent('thinking_started');
          },
          onToken: function (token) {
            if (typeof token !== 'string') return;
            showStreamAndAppendToken(token);
          },
          onComplete: function (result) {
            var content = (result && result.content) ? result.content : '';
            accumulatedAssistantContent += content;
            var streamed = getStreamRawContent();
            if (streamed && (!accumulatedAssistantContent || accumulatedAssistantContent.indexOf(streamed) === -1)) {
              accumulatedAssistantContent = (accumulatedAssistantContent || '') + streamed;
            }
            var hasToolCalls = (result && result.toolCalls && result.toolCalls.length > 0) || 
                              (result && result.toolResults && result.toolResults.length > 0);
            var finishReason = result && result.finishReason;
            var isToolFollowUp = finishReason === 'tool_calls' || hasToolCalls;
            if (!isToolFollowUp) {
              var finalContentToSave = accumulatedAssistantContent ? accumulatedAssistantContent.trim() : '';
              var thinkingEl = document.getElementById('agentify-thinking-msg');
              if (thinkingEl && finalContentToSave) {
                var parsed = parseAssistantContentWithError(finalContentToSave);
                var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContentToSave;
                morphThinkingToReply(finalContentToSave, false);
                if (contentToSave) {
                  currentChatMessages.push({ role: 'assistant', content: contentToSave });
                  if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                  api('/chat/messages', {
                    method: 'POST',
                    body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }),
                  }).then(function () { loadChats(); });
                }
              } else if (thinkingEl && !finalContentToSave) {
                thinkingEl.remove();
              }
            }
            if (currentChatId != null) loadTasks(currentChatId);
          },
          onError: function (err) {
            if (err && typeof err.toConsole === 'function') {
              try { console.error(err.toConsole()); } catch (_) {}
            }
            var msg = (err && err.message) ? err.message : 'An error occurred.';
            showThinkingError(msg);
            setSendLoading(false);
          },
        }).then(function (finalResult) {
          var thinkingEl = document.getElementById('agentify-thinking-msg');
          if (thinkingEl) {
            var finalContent = getStreamRawContent() || (finalResult && finalResult.content ? String(finalResult.content).trim() : '');
            var hasToolCalls = (finalResult && finalResult.toolCalls && finalResult.toolCalls.length > 0) || 
                              (finalResult && finalResult.toolResults && finalResult.toolResults.length > 0);
            var finishReason = finalResult && finalResult.finishReason;
            var isToolFollowUp = finishReason === 'tool_calls' || hasToolCalls;
            if (!isToolFollowUp && finalContent) {
              var parsed = parseAssistantContentWithError(finalContent);
              var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContent;
              morphThinkingToReply(finalContent, false);
              if (contentToSave) {
                currentChatMessages.push({ role: 'assistant', content: contentToSave });
                if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                api('/chat/messages', {
                  method: 'POST',
                  body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }),
                }).then(function () { loadChats(); });
              }
            }
          } else {
            /* thinkingEl is null: usually already morphed in onComplete (id was removed). Do not add a second message. Only add/save if the last message is not an assistant (edge case). */
            var lastMsg = messagesEl && messagesEl.lastElementChild;
            var lastIsAssistant = lastMsg && lastMsg.classList.contains('agentify-assistant') && !lastMsg.classList.contains('agentify-thinking');
            if (lastIsAssistant) return;
            var finalContent = getStreamRawContent() || (finalResult && finalResult.content ? String(finalResult.content).trim() : '');
            if (finalContent) {
              var parsed = parseAssistantContentWithError(finalContent);
              var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContent;
              var displayContent = parsed.errorMessage ? parsed.messageContent : finalContent;
              addMessageToDOM('assistant', displayContent, new Date());
              if (parsed.errorMessage && messagesEl && messagesEl.lastElementChild) {
                var bubble = messagesEl.lastElementChild.querySelector('.agentify-message-bubble');
                if (bubble) bubble.insertAdjacentHTML('beforeend', buildMessageErrorBoxHtml(parsed.errorMessage));
              }
              if (contentToSave) {
                currentChatMessages.push({ role: 'assistant', content: contentToSave });
                if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                api('/chat/messages', {
                  method: 'POST',
                  body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }),
                }).then(function () { loadChats(); });
              }
            }
          }
        });
      })
      .catch(function (err) {
        if (err && err.message) replaceThinkingWithReply(err.message, true);
      })
      .finally(function () {
        setSendLoading(false);
        if (userInput) userInput.focus();
      });
  });

  if (btnNewChat) btnNewChat.addEventListener('click', startNewChat);
  if (btnStop) btnStop.addEventListener('click', stopGeneration);

  messagesEl.addEventListener('click', function (e) {
    var mcBtn = e.target && e.target.closest && e.target.closest('.agentify-mc-confirm');
    if (mcBtn) {
      e.preventDefault();
      var block = mcBtn.closest('.agentify-mc-block');
      if (!block) return;
      var checked = block.querySelector('input[type="radio"]:checked');
      if (!checked) return;
      var text = (checked.value || checked.getAttribute('data-option-text') || '').trim();
      if (!text) return;
      if (!restUrl) {
        addMessageToDOM('user', text);
        replaceThinkingWithReply('Configure REST URL and API keys in Settings.', true);
        return;
      }
      var oldThinkingEl = document.getElementById('agentify-thinking-msg');
      if (oldThinkingEl) oldThinkingEl.remove();
      clearAllEvents();
      addMessageToDOM('user', text);
      currentChatMessages.push({ role: 'user', content: text });
      addThinkingMessage();
      setSendLoading(true);
      lastUserMessageForRetry = text;
      var accumulatedAssistantContent = '';
      ensureCurrentChat()
        .then(function (chatId) {
          if (chatId == null) {
            replaceThinkingWithReply('Could not create or use a chat. Try again.', true);
            setSendLoading(false);
            return Promise.reject();
          }
          return getAgent().then(function (agent) {
            var chatIdStr = String(chatId);
            agent.setChatId(chatIdStr);
            agent.chatHistoryManager.updateChatHistory(chatIdStr, currentChatMessages);
            return api('/chat/messages', {
              method: 'POST',
              body: JSON.stringify({ chat_id: chatId, role: 'user', content: text }),
            }).then(function () { return { agent: agent, chatId: chatId }; });
          });
        })
        .then(function (ctx) {
          if (!ctx) return;
          var agent = ctx.agent;
          var chatId = ctx.chatId;
          return agent.chat(text, {
            chatId: String(chatId),
            onToolCall: function (toolCall) {
              pushEvent('tool_call_initiated');
              pushEvent('Using tool: ' + (toolCall && toolCall.name ? toolCall.name : 'tool'));
            },
            onThinking: function (thought) {
              if (thought && String(thought).trim()) pushEvent('thinking_started');
            },
            onToken: function (token) {
              if (typeof token !== 'string') return;
              showStreamAndAppendToken(token);
            },
            onComplete: function (result) {
              var content = (result && result.content) ? result.content : '';
              accumulatedAssistantContent += content;
              var streamed = getStreamRawContent();
              if (streamed && (!accumulatedAssistantContent || accumulatedAssistantContent.indexOf(streamed) === -1)) {
                accumulatedAssistantContent = (accumulatedAssistantContent || '') + streamed;
              }
              var hasToolCalls = (result && result.toolCalls && result.toolCalls.length > 0) || (result && result.toolResults && result.toolResults.length > 0);
              var finishReason = result && result.finishReason;
              var isToolFollowUp = finishReason === 'tool_calls' || hasToolCalls;
              if (!isToolFollowUp) {
                var finalContentToSave = accumulatedAssistantContent ? accumulatedAssistantContent.trim() : '';
                var thinkingEl = document.getElementById('agentify-thinking-msg');
                if (thinkingEl && finalContentToSave) {
                  var parsed = parseAssistantContentWithError(finalContentToSave);
                  var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContentToSave;
                  morphThinkingToReply(finalContentToSave, false);
                  if (contentToSave) {
                    currentChatMessages.push({ role: 'assistant', content: contentToSave });
                    if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                    api('/chat/messages', { method: 'POST', body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }) }).then(function () { loadChats(); });
                  }
                } else if (thinkingEl && !finalContentToSave) {
                  thinkingEl.remove();
                }
              }
              if (currentChatId != null) loadTasks(currentChatId);
            },
            onError: function (err) {
              if (err && typeof err.toConsole === 'function') { try { console.error(err.toConsole()); } catch (_) {} }
              showThinkingError((err && err.message) ? err.message : 'An error occurred.');
              setSendLoading(false);
            },
          }).then(function (finalResult) {
            var thinkingEl = document.getElementById('agentify-thinking-msg');
            if (thinkingEl) {
              var finalContent = getStreamRawContent() || (finalResult && finalResult.content ? String(finalResult.content).trim() : '');
              var hasToolCalls = (finalResult && finalResult.toolCalls && finalResult.toolCalls.length > 0) || (finalResult && finalResult.toolResults && finalResult.toolResults.length > 0);
              var isToolFollowUp = (finalResult && finalResult.finishReason === 'tool_calls') || hasToolCalls;
              if (!isToolFollowUp && finalContent) {
                var parsed = parseAssistantContentWithError(finalContent);
                var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContent;
                morphThinkingToReply(finalContent, false);
                if (contentToSave) {
                  currentChatMessages.push({ role: 'assistant', content: contentToSave });
                  if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                  api('/chat/messages', { method: 'POST', body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }) }).then(function () { loadChats(); });
                }
              }
            } else {
              var lastMsg = messagesEl && messagesEl.lastElementChild;
              var lastIsAssistant = lastMsg && lastMsg.classList.contains('agentify-assistant') && !lastMsg.classList.contains('agentify-thinking');
              if (lastIsAssistant) return;
              var finalContent = getStreamRawContent() || (finalResult && finalResult.content ? String(finalResult.content).trim() : '');
              if (finalContent) {
                var parsed = parseAssistantContentWithError(finalContent);
                var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContent;
                addMessageToDOM('assistant', contentToSave, new Date());
                if (parsed.errorMessage && messagesEl && messagesEl.lastElementChild) {
                  var bubble = messagesEl.lastElementChild.querySelector('.agentify-message-bubble');
                  if (bubble) bubble.insertAdjacentHTML('beforeend', buildMessageErrorBoxHtml(parsed.errorMessage));
                }
                if (contentToSave) {
                  currentChatMessages.push({ role: 'assistant', content: contentToSave });
                  if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                  api('/chat/messages', { method: 'POST', body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }) }).then(function () { loadChats(); });
                }
              }
            }
          });
        })
        .catch(function () {})
        .finally(function () {
          setSendLoading(false);
          if (userInput) userInput.focus();
        });
      return;
    }

    var tryBtn = e.target && e.target.closest && e.target.closest('.agentify-try-again-btn');
    if (!tryBtn || !lastUserMessageForRetry || currentChatId == null) return;
    e.preventDefault();
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (thinkingEl) thinkingEl.remove();
    clearAllEvents();
    var errorWrap = tryBtn.closest('.agentify-message-error-wrap');
    var shouldRemoveMessage = false;
    if (errorWrap) {
      var messageEl = errorWrap.closest('.agentify-message');
      if (messageEl && messageEl.classList.contains('agentify-assistant')) {
        var bubble = messageEl.querySelector('.agentify-message-bubble');
        if (bubble) {
          var errorBox = bubble.querySelector('.agentify-message-error-wrap');
          if (errorBox) errorBox.remove();
          var messageText = bubble.querySelector('.agentify-message-text');
          if (messageText && !messageText.textContent.trim()) {
            messageEl.remove();
            shouldRemoveMessage = true;
          }
        }
      }
    }
    if (shouldRemoveMessage && currentChatMessages.length > 0 && currentChatMessages[currentChatMessages.length - 1].role === 'assistant') {
      currentChatMessages.pop();
      if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(currentChatId), currentChatMessages);
    }
    addThinkingMessage();
    setSendLoading(true);
    var chatId = currentChatId;
    var text = lastUserMessageForRetry;
    var accumulatedAssistantContent = '';
    getAgent()
      .then(function (agent) {
        agentifyAgent = agent;
        agent.setChatId(String(chatId));
        agent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
        return agent.chat(text, {
          chatId: String(chatId),
          onToolCall: function (toolCall) {
            pushEvent('tool_call_initiated');
            pushEvent('Using tool: ' + (toolCall && toolCall.name ? toolCall.name : 'tool'));
          },
          onThinking: function (thought) {
            if (thought && String(thought).trim()) pushEvent('thinking_started');
          },
          onToken: function (token) {
            if (typeof token !== 'string') return;
            showStreamAndAppendToken(token);
          },
          onComplete: function (result) {
            var content = (result && result.content) ? result.content : '';
            accumulatedAssistantContent += content;
            var streamed = getStreamRawContent();
            if (streamed && (!accumulatedAssistantContent || accumulatedAssistantContent.indexOf(streamed) === -1)) {
              accumulatedAssistantContent = (accumulatedAssistantContent || '') + streamed;
            }
            var hasToolCalls = (result && result.toolCalls && result.toolCalls.length > 0) || 
                              (result && result.toolResults && result.toolResults.length > 0);
            var finishReason = result && result.finishReason;
            var isToolFollowUp = finishReason === 'tool_calls' || hasToolCalls;
            if (!isToolFollowUp) {
              var finalContentToSave = accumulatedAssistantContent ? accumulatedAssistantContent.trim() : '';
              var thinkingEl = document.getElementById('agentify-thinking-msg');
              if (thinkingEl && finalContentToSave) {
                var parsed = parseAssistantContentWithError(finalContentToSave);
                var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContentToSave;
                morphThinkingToReply(finalContentToSave, false);
                if (contentToSave) {
                  currentChatMessages.push({ role: 'assistant', content: contentToSave });
                  if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
                  api('/chat/messages', {
                    method: 'POST',
                    body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }),
                  }).then(function () { loadChats(); });
                }
              } else if (thinkingEl && !finalContentToSave) {
                thinkingEl.remove();
              }
            }
            if (currentChatId != null) loadTasks(currentChatId);
          },
          onError: function (err) {
            if (err && typeof err.toConsole === 'function') {
              try { console.error(err.toConsole()); } catch (_) {}
            }
            var msg = (err && err.message) ? err.message : 'An error occurred.';
            showThinkingError(msg);
            setSendLoading(false);
          },
        });
      })
      .then(function (finalResult) {
        var thinkingEl = document.getElementById('agentify-thinking-msg');
        if (thinkingEl) {
          var finalContent = getStreamRawContent() || (finalResult && finalResult.content ? String(finalResult.content).trim() : '');
          var hasToolCalls = (finalResult && finalResult.toolCalls && finalResult.toolCalls.length > 0) ||
                            (finalResult && finalResult.toolResults && finalResult.toolResults.length > 0);
          var finishReason = finalResult && finalResult.finishReason;
          var isToolFollowUp = finishReason === 'tool_calls' || hasToolCalls;
          if (!isToolFollowUp && finalContent) {
            var parsed = parseAssistantContentWithError(finalContent);
            var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContent;
            morphThinkingToReply(finalContent, false);
            if (contentToSave) {
              currentChatMessages.push({ role: 'assistant', content: contentToSave });
              if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
              api('/chat/messages', {
                method: 'POST',
                body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }),
              }).then(function () { loadChats(); });
            }
          }
        } else {
          var lastMsg = messagesEl && messagesEl.lastElementChild;
          var lastIsAssistant = lastMsg && lastMsg.classList.contains('agentify-assistant') && !lastMsg.classList.contains('agentify-thinking');
          if (lastIsAssistant) return;
          var finalContent = getStreamRawContent() || (finalResult && finalResult.content ? String(finalResult.content).trim() : '');
          if (finalContent) {
            var parsed = parseAssistantContentWithError(finalContent);
            var contentToSave = parsed.errorMessage ? parsed.messageContent : finalContent;
            var displayContent = parsed.errorMessage ? parsed.messageContent : finalContent;
            addMessageToDOM('assistant', displayContent, new Date());
            if (parsed.errorMessage && messagesEl && messagesEl.lastElementChild) {
              var bubble = messagesEl.lastElementChild.querySelector('.agentify-message-bubble');
              if (bubble) bubble.insertAdjacentHTML('beforeend', buildMessageErrorBoxHtml(parsed.errorMessage));
            }
            if (contentToSave) {
              currentChatMessages.push({ role: 'assistant', content: contentToSave });
              if (agentifyAgent) agentifyAgent.chatHistoryManager.updateChatHistory(String(chatId), currentChatMessages);
              api('/chat/messages', {
                method: 'POST',
                body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: contentToSave }),
              }).then(function () { loadChats(); });
            }
          }
        }
      })
      .catch(function (err) {
        if (err && err.message) showThinkingError(err.message);
      })
      .finally(function () {
        setSendLoading(false);
        if (userInput) userInput.focus();
      });
  });

  // Load chat list on init
  loadChats();

  // // Debug: every 3s log agent state (only when agent is ready)
  // setInterval(function () {
  //   getAgent()
  //     .then(function (agent) {
  //       var tasks = agent.getTasks();
  //       var events = agent.getEvents();
  //       var timeline = currentChatId != null ? agent.getChatTimeline(String(currentChatId)) : [];
  //       var thinkingStatus = agent.getThinkingStatus();
  //       var history = agent.getHistory();
  //       var errorLog = agent.getErrorLog();
  //       var storageInfo = agent.getStorageInfo();
  //       var status = agent.getStatus();
  //       var chatHistory = currentChatId != null ? agent.getChatHistory(String(currentChatId)) : null;
  //       console.log('Interval: ' + new Date().toISOString() + ' | currentChatId: ' + currentChatId);
  //       console.log('Tasks:', tasks);
  //       console.log('Events:', events);
  //       console.log('Timeline:', timeline);
  //       console.log('ThinkingStatus:', thinkingStatus);
  //       console.log('History (session):', history);
  //       console.log('ChatHistory (current):', chatHistory);
  //       console.log('ErrorLog:', errorLog);
  //       console.log('StorageInfo:', storageInfo);
  //       console.log('Status:', status);
  //       console.log('--------------------------------------------------------------------------------------');
  //     })
  //     .catch(function () {
  //       console.log('Interval: agent not ready yet');
  //     });
  // }, 3000);

  // ----- Settings modal (unchanged) -----
  var btnSettings = document.getElementById('agentify-btn-settings');
  var settingsOverlay = document.getElementById('agentify-settings-overlay');
  var settingsModal = document.getElementById('agentify-settings-modal');
  var settingsModelSelect = document.getElementById('agentify-settings-model');
  var apiKeysSection = document.getElementById('agentify-api-keys-section');
  var settingsSave = document.getElementById('agentify-settings-save');
  var settingsCloseBtn = document.getElementById('agentify-settings-close-btn');
  var settingsCloseIcon = document.getElementById('agentify-settings-close');
  var settingsMessage = document.getElementById('agentify-settings-message');
  var saveSkeleton = document.getElementById('agentify-save-skeleton');
  var settingsAllowDbWrite = document.getElementById('agentify-settings-allow-db-write');

  function getProviderFromModelValue(value) {
    if (!value) return 'deepseek';
    var parts = value.split('|');
    return parts[0] || 'deepseek';
  }

  function setSaveLoading(loading) {
    if (!settingsSave) return;
    if (loading) {
      settingsSave.classList.add('agentify-loading');
      settingsSave.disabled = true;
    } else {
      settingsSave.classList.remove('agentify-loading');
      settingsSave.disabled = false;
    }
  }

  function showSettingsMessage(text, type) {
    if (!settingsMessage) return;
    settingsMessage.textContent = text || '';
    settingsMessage.className = 'agentify-settings-message agentify-visible';
    if (type === 'success') settingsMessage.classList.add('agentify-success');
    else if (type === 'error') settingsMessage.classList.add('agentify-error');
    else settingsMessage.classList.remove('agentify-success', 'agentify-error');
  }

  function clearSettingsMessage() {
    if (!settingsMessage) return;
    settingsMessage.textContent = '';
    settingsMessage.className = 'agentify-settings-message';
  }

  function openSettings() {
    if (!settingsOverlay) return;
    settingsOverlay.setAttribute('aria-hidden', 'false');
    clearSettingsMessage();
    setSaveLoading(true);
    loadSettingsIntoModal();
  }

  function closeSettings() {
    if (!settingsOverlay) return;
    settingsOverlay.setAttribute('aria-hidden', 'true');
  }

  function showApiKeyRow(modelId) {
    if (!apiKeysSection) return;
    apiKeysSection.querySelectorAll('.agentify-settings-api-row').forEach(function (row) {
      row.classList.toggle('agentify-visible', row.getAttribute('data-model') === modelId);
    });
  }

  var apiKeyInputIds = { deepseek: 'agentify-api-key-deepseek', chatgpt: 'agentify-api-key-chatgpt', gemini: 'agentify-api-key-gemini', claude: 'agentify-api-key-claude' };

  function loadSettingsIntoModal() {
    if (!restUrl) {
      setSaveLoading(false);
      if (settingsModelSelect) settingsModelSelect.value = 'deepseek|deepseek-chat';
      showApiKeyRow('deepseek');
      return;
    }
    fetch(restUrl + '/settings', {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': restNonce },
    })
      .then(function (res) {
        if (!res.ok) throw new Error('Load failed');
        return res.json();
      })
      .then(function (data) {
        if (settingsModelSelect && data.model) {
          var opt = settingsModelSelect.querySelector('option[value="' + data.model + '"]');
          settingsModelSelect.value = opt ? data.model : 'deepseek|deepseek-chat';
        } else if (settingsModelSelect) {
          settingsModelSelect.value = 'deepseek|deepseek-chat';
        }
        showApiKeyRow(getProviderFromModelValue(settingsModelSelect ? settingsModelSelect.value : null));
        if (data.api_keys) {
          Object.keys(apiKeyInputIds).forEach(function (key) {
            var input = document.getElementById(apiKeyInputIds[key]);
            if (input && data.api_keys[key] !== undefined) input.value = data.api_keys[key] || '';
          });
        }
        if (settingsAllowDbWrite) settingsAllowDbWrite.checked = !!data.allow_db_write;
      })
      .catch(function () {
        if (settingsModelSelect) settingsModelSelect.value = 'deepseek|deepseek-chat';
        showApiKeyRow('deepseek');
        if (settingsAllowDbWrite) settingsAllowDbWrite.checked = false;
      })
      .finally(function () { setSaveLoading(false); });
  }

  function saveSettings() {
    if (!restUrl) {
      showSettingsMessage('REST URL not available.', 'error');
      return;
    }
    clearSettingsMessage();
    setSaveLoading(true);
    var modelId = settingsModelSelect ? settingsModelSelect.value : 'deepseek|deepseek-chat';
    var apiKeys = {};
    if (apiKeysSection) {
      apiKeysSection.querySelectorAll('.agentify-settings-api-row').forEach(function (row) {
        var model = row.getAttribute('data-model');
        var input = row.querySelector('input');
        if (model && input) apiKeys[model] = input.value || '';
      });
    }
    var allowDbWrite = settingsAllowDbWrite ? settingsAllowDbWrite.checked : false;
    fetch(restUrl + '/settings', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
      body: JSON.stringify({ model: modelId, api_keys: apiKeys, allow_db_write: allowDbWrite }),
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok) throw new Error(data.message || res.statusText || 'Save failed');
          return data;
        });
      })
      .then(function (data) {
        showSettingsMessage(data.message || 'Settings saved.', data.success !== false ? 'success' : 'error');
      })
      .catch(function (err) {
        showSettingsMessage(err.message || 'Failed to save settings.', 'error');
      })
      .finally(function () { setSaveLoading(false); });
  }

  if (btnSettings) btnSettings.addEventListener('click', openSettings);
  if (settingsCloseIcon) settingsCloseIcon.addEventListener('click', closeSettings);
  if (settingsCloseBtn) settingsCloseBtn.addEventListener('click', closeSettings);
  if (settingsSave) settingsSave.addEventListener('click', saveSettings);
  if (settingsOverlay) {
    settingsOverlay.addEventListener('click', function (e) {
      if (e.target === settingsOverlay) closeSettings();
    });
  }
  if (settingsModelSelect) {
    settingsModelSelect.addEventListener('change', function () {
      showApiKeyRow(getProviderFromModelValue(settingsModelSelect.value));
    });
  }

  if (userInput) userInput.focus();
})();
