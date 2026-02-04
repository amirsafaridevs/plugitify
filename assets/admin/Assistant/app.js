/**
 * Plugitify Assistant – Chat list from DB, messages via REST, AI via backend.
 * All operations are AJAX; loading states use skeletons where appropriate.
 */

(function () {
  'use strict';

  const restUrl = typeof agentifyRest !== 'undefined' ? agentifyRest.restUrl : '';
  const restNonce = typeof agentifyRest !== 'undefined' ? agentifyRest.nonce : '';
  const agentifyBaseUrl = typeof agentifyRest !== 'undefined' ? (agentifyRest.agentifyBaseUrl || '') : '';

  const messagesEl = document.getElementById('agentify-messages');
  const chatForm = document.getElementById('agentify-chat-form');
  const userInput = document.getElementById('agentify-user-input');
  const btnSend = document.getElementById('agentify-btn-send');
  const btnStop = document.getElementById('agentify-btn-stop');
  const btnNewChat = document.getElementById('agentify-btn-new-chat');
  const chatListPlaceholder = document.getElementById('agentify-chat-list-placeholder');
  const chatItems = document.getElementById('agentify-chat-items');
  const panelTasksList = document.getElementById('agentify-panel-tasks-list');
  const panelTasksContainer = document.getElementById('agentify-panel-tasks');
  const welcomeEl = document.getElementById('agentify-welcome');

  if (!messagesEl || !chatForm || !userInput) return;

  let currentChatId = null;
  /** Messages for current chat (for Agentify history sync). Format: [{ role, content }] */
  let currentChatMessages = [];
  let agentifyAgent = null;
  /** Current turn events (thinking steps, tool calls) – show last 2 under thinking bubble */
  let recentEvents = [];
  var PROVIDER_URLS = {
    deepseek: 'https://api.deepseek.com/v1/chat/completions',
    chatgpt: 'https://api.openai.com/v1/chat/completions',
    openai: 'https://api.openai.com/v1/chat/completions',
  };

  function api(endpoint, options) {
    var url = restUrl + endpoint;
    var headers = { 'X-WP-Nonce': restNonce };
    if (options && (options.method === 'POST' || options.method === 'PUT' || options.method === 'PATCH')) headers['Content-Type'] = 'application/json';
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

  function formatMessageContent(text) {
    if (!text) return '';
    var s = escapeHtml(text);
    s = s.replace(/\n/g, '<br>');
    s = s.replace(/\*\*([\s\S]+?)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    s = s.replace(/`([^`]+)`/g, '<code class="agentify-inline-code">$1</code>');
    s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function (_, label, url) {
      var safeUrl = url.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
      return '<a href="' + safeUrl + '" target="_blank" rel="noopener noreferrer" class="agentify-msg-link">' + label + '</a>';
    });
    return s;
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
    recentEvents = [];
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

  function pushEvent(label) {
    if (!label || !String(label).trim()) return;
    recentEvents.push(String(label).trim());
    var listEl = document.getElementById('agentify-thinking-events-list');
    var containerEl = document.getElementById('agentify-thinking-events');
    if (!listEl || !containerEl) return;
    var maxShow = 8;
    var toShow = recentEvents.slice(-maxShow);
    listEl.innerHTML = toShow.map(function (ev) {
      return '<li class="agentify-thinking-event-item">' + escapeHtml(ev) + '</li>';
    }).join('');
    containerEl.style.display = toShow.length ? '' : 'none';
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
    streamEl.textContent = (streamEl.textContent || '') + token;
    scrollToBottom();
  }

  function updateThinkingUI(statusOrText) {
    var raw = typeof statusOrText === 'string' ? statusOrText : (statusOrText && statusOrText.currentAction) || 'Thinking';
    pushEvent(raw);
    var text = raw.indexOf('Using tool:') === 0 ? 'Step: ' + raw : raw;
    var el = document.getElementById('agentify-thinking-msg');
    if (!el) return;
    var textEl = el.querySelector('.agentify-thinking-text');
    if (textEl) textEl.textContent = text;
  }

  /** Replace thinking block with final reply (used when no stream occurred, e.g. error before stream). */
  function replaceThinkingWithReply(text, isError) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) return;
    var time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    var dir = getTextDirection(text || '');
    var content = isError
      ? '<div class="agentify-message-error" dir="' + dir + '">' + escapeHtml(text || 'Something went wrong.') + '</div>'
      : (text ? '<div class="agentify-message-text" dir="' + dir + '">' + formatMessageContent(text) + '</div>' : '');
    var html = '<div class="agentify-message agentify-assistant" role="listitem">' +
      '<div class="agentify-message-avatar" aria-hidden="true"><span class="material-symbols-outlined">auto_awesome</span></div>' +
      '<div class="agentify-message-bubble">' + content +
      '<div class="agentify-message-time">' + time + '</div></div></div>';
    thinkingEl.outerHTML = html;
    scrollToBottom();
  }

  /** Morph the thinking block (with streamed content) into the final assistant message. If no thinking block (e.g. after a tool round), append a new assistant message so content is never lost. */
  function morphThinkingToReply(text, isError) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) {
      if (text || isError) {
        addMessageToDOM('assistant', text || (isError ? 'Something went wrong.' : ''), new Date());
        if (messagesEl) messagesEl.classList.add('agentify-has-messages');
        scrollToBottom();
      }
      return;
    }
    if (!text && !isError) {
      return;
    }
    if (!text && isError) {
      text = 'An error occurred.';
    }
    var bubble = thinkingEl.querySelector('.agentify-message-bubble');
    if (!bubble) {
      replaceThinkingWithReply(text, isError);
      return;
    }
    var time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    var dir = getTextDirection(text || '');
    thinkingEl.classList.remove('agentify-thinking');
    thinkingEl.removeAttribute('aria-busy');
    thinkingEl.removeAttribute('id');
    bubble.innerHTML = '';
    if (isError) {
      var errEl = document.createElement('div');
      errEl.className = 'agentify-message-error';
      errEl.setAttribute('dir', dir);
      errEl.textContent = text || 'Something went wrong.';
      bubble.appendChild(errEl);
    } else if (text) {
      var textDiv = document.createElement('div');
      textDiv.className = 'agentify-message-text';
      textDiv.setAttribute('dir', dir);
      textDiv.innerHTML = formatMessageContent(text);
      bubble.appendChild(textDiv);
    }
    var timeEl = document.createElement('div');
    timeEl.className = 'agentify-message-time';
    timeEl.textContent = time;
    bubble.appendChild(timeEl);
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

  function renderPanelTasks(tasks) {
    if (!panelTasksList || !panelTasksContainer) return;
    if (!tasks || tasks.length === 0) {
      panelTasksList.innerHTML = '';
      panelTasksContainer.classList.remove('agentify-panel-tasks-has');
      return;
    }
    panelTasksContainer.classList.add('agentify-panel-tasks-has');
    panelTasksList.innerHTML = tasks.map(function (t) {
      var title = (t.title && t.title.trim()) ? escapeHtml(t.title) : '';
      var status = (t.status && t.status.trim()) ? escapeHtml(t.status) : 'pending';
      return '<li class="agentify-panel-task" data-status="' + status + '">' +
        '<span class="material-symbols-outlined agentify-panel-task-icon" aria-hidden="true">' +
        (status === 'completed' ? 'check_circle' : status === 'cancelled' ? 'cancel' : 'radio_button_unchecked') + '</span>' +
        '<span class="agentify-panel-task-title">' + title + '</span></li>';
    }).join('');
  }

  function loadTasks(chatId) {
    if (!restUrl || !panelTasksList) return;
    if (chatId == null) {
      renderPanelTasks([]);
      return;
    }
    api('/tasks?chat_id=' + chatId)
      .then(function (res) {
        var list = (res.data && res.data.tasks) ? res.data.tasks : [];
        renderPanelTasks(list);
      })
      .catch(function () {
        renderPanelTasks([]);
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
        if (m.role === 'user' || m.role === 'assistant') {
          addMessageToDOM(m.role, m.content || '', m.created_at);
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

  /** Lazy-init Agentify: load module, fetch settings, create agent, add set_chat_title tool. */
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
        var agent = new Agentify({
          provider: provider,
          model: model,
          apiKey: apiKey,
          apiUrl: apiUrl,
          stream: true,
          useHistory: true,
          includeHistory: true,
        });
        agent.setInstruction(settings.system_instruction || '');
        return agent.addTool({
          name: 'set_chat_title',
          description: 'Set the title of the current chat. Use this only when the chat has no custom title yet (title is empty or "new" or "new #id"). Call once per chat with a short, descriptive title summarizing the conversation.',
          instruction: 'Use only when the chat title is empty or "new" or "new #id". Call exactly once per chat with a short title (e.g. one line).',
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
            name: 'create_task',
            description: 'Create a task (todo) for the user. You MUST call this whenever the user asks to remember something, add a todo, or be reminded (e.g. "یادت باشه", "یادداشت کن", "تسک بساز", "یادم بنداز", "add a task", "remind me"). Tasks are saved to the database and shown under the chat.',
            instruction: 'Call create_task whenever the user wants something remembered or a todo. Use a short title and optional description. One task per item; multiple items = multiple calls.',
            parameters: {
              title: { type: 'string', description: 'Short title for the task', required: true },
              description: { type: 'string', description: 'Optional longer description or notes', required: false },
            },
            execute: function (params) {
              if (!restUrl) return Promise.resolve({ success: false, message: 'REST not configured.' });
              var title = (params && params.title) ? String(params.title).trim() : '';
              if (!title) return Promise.resolve({ success: false, message: 'Title is required.' });
              var body = { title: title };
              if (params && params.description && String(params.description).trim()) body.description = String(params.description).trim();
              if (currentChatId != null) body.chat_id = currentChatId;
              return api('/tasks', { method: 'POST', body: JSON.stringify(body) }).then(function (res) {
                if (res.ok && res.data && res.data.success) {
                  if (currentChatId != null) loadTasks(currentChatId);
                  return { success: true, task_id: res.data.task_id, message: res.data.message || 'Task created.' };
                }
                return { success: false, message: (res.data && res.data.message) || 'Failed to create task.' };
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
        var accumulatedAssistantContent = '';
        return agent.chat(text, {
          chatId: String(chatId),
          onThinkingChange: updateThinkingUI,
          onToolCall: function (toolCall) {
            var label = 'Using tool: ' + (toolCall && toolCall.name ? toolCall.name : 'tool');
            pushEvent(label);
            updateThinkingUI(label);
          },
          onToken: function (token) {
            if (typeof token !== 'string') return;
            showStreamAndAppendToken(token);
          },
          onComplete: function (result) {
            var content = (result && result.content) ? result.content : '';
            accumulatedAssistantContent += content;
          },
          onError: function (err) {
            var msg = (err && err.message) ? err.message : 'An error occurred.';
            morphThinkingToReply(msg, true);
          },
        }).then(function (finalResult) {
          var streamEl = document.getElementById('agentify-thinking-stream');
          if (streamEl && streamEl.textContent && streamEl.textContent.trim()) {
            var streamed = streamEl.textContent.trim();
            if (!accumulatedAssistantContent || accumulatedAssistantContent.indexOf(streamed) === -1) {
              accumulatedAssistantContent = (accumulatedAssistantContent || '') + streamed;
            }
          }
          if (finalResult && finalResult.content) {
            var finalContent = String(finalResult.content).trim();
            if (!accumulatedAssistantContent || accumulatedAssistantContent.indexOf(finalContent) === -1) {
              accumulatedAssistantContent = (accumulatedAssistantContent || '') + finalContent;
            }
          }
          var finalContentToSave = accumulatedAssistantContent ? accumulatedAssistantContent.trim() : '';
          var thinkingEl = document.getElementById('agentify-thinking-msg');
          if (thinkingEl) {
            if (finalContentToSave) {
              morphThinkingToReply(finalContentToSave, false);
              currentChatMessages.push({ role: 'assistant', content: finalContentToSave });
              api('/chat/messages', {
                method: 'POST',
                body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: finalContentToSave }),
              }).then(function () { loadChats(); });
            } else {
              thinkingEl.remove();
            }
          } else if (finalContentToSave) {
            addMessageToDOM('assistant', finalContentToSave, new Date());
            currentChatMessages.push({ role: 'assistant', content: finalContentToSave });
            api('/chat/messages', {
              method: 'POST',
              body: JSON.stringify({ chat_id: chatId, role: 'assistant', content: finalContentToSave }),
            }).then(function () { loadChats(); });
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

  // Load chat list on init
  loadChats();

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
