/**
 * Plugitify Assistant – Chat list from DB, messages via REST, AI via backend.
 * All operations are AJAX; loading states use skeletons where appropriate.
 */

(function () {
  'use strict';

  const restUrl = typeof agentifyRest !== 'undefined' ? agentifyRest.restUrl : '';
  const restNonce = typeof agentifyRest !== 'undefined' ? agentifyRest.nonce : '';

  const messagesEl = document.getElementById('agentify-messages');
  const chatForm = document.getElementById('agentify-chat-form');
  const userInput = document.getElementById('agentify-user-input');
  const btnSend = document.getElementById('agentify-btn-send');
  const btnNewChat = document.getElementById('agentify-btn-new-chat');
  const chatListPlaceholder = document.getElementById('agentify-chat-list-placeholder');
  const chatItems = document.getElementById('agentify-chat-items');
  const welcomeEl = document.getElementById('agentify-welcome');

  if (!messagesEl || !chatForm || !userInput) return;

  let currentChatId = null;

  function api(endpoint, options) {
    var url = restUrl + endpoint;
    var headers = { 'X-WP-Nonce': restNonce };
    if (options && (options.method === 'POST' || options.method === 'PUT')) headers['Content-Type'] = 'application/json';
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
    var html = '<div class="agentify-message agentify-assistant agentify-thinking" id="agentify-thinking-msg" role="listitem" aria-busy="true">' +
      '<div class="agentify-message-avatar" aria-hidden="true"><span class="material-symbols-outlined">auto_awesome</span></div>' +
      '<div class="agentify-message-bubble">' +
      '<div class="agentify-thinking-inner">' +
      '<span class="agentify-thinking-text">Thinking</span>' +
      '<div class="agentify-thinking-dots" aria-hidden="true"><span></span><span></span><span></span></div></div></div></div>';
    messagesEl.insertAdjacentHTML('beforeend', html);
    scrollToBottom();
  }

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

  function setSendLoading(loading) {
    if (!btnSend || !userInput) return;
    userInput.disabled = !!loading;
    if (loading) {
      btnSend.disabled = true;
      btnSend.classList.add('agentify-loading');
    } else {
      btnSend.disabled = false;
      btnSend.classList.remove('agentify-loading');
    }
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
    api('/chats/' + chatId + '/messages')
      .then(function (res) {
        var list = (res.data && res.data.messages) ? res.data.messages : [];
        renderMessages(list);
      })
      .catch(function () {
        renderMessages([]);
      })
      .finally(function () {
        loadChats();
      });
  }

  function startNewChat() {
    currentChatId = null;
    clearMessagesPanel();
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
            loadChats();
          }
          resolve(id);
        })
        .catch(function () {
          resolve(null);
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
    addThinkingMessage();
    setSendLoading(true);

    ensureCurrentChat().then(function (chatId) {
      if (chatId == null) {
        replaceThinkingWithReply('Could not create or use a chat. Try again.', true);
        setSendLoading(false);
        var t = document.getElementById('agentify-thinking-msg');
        if (t) t.remove();
        return;
      }
      return api('/chat', {
        method: 'POST',
        body: JSON.stringify({ chat_id: chatId, content: text }),
      });
    })
      .then(function (res) {
        if (!res) return;
        if (res.ok && res.data && res.data.content != null) {
          replaceThinkingWithReply(res.data.content, false);
          loadChats();
        } else {
          replaceThinkingWithReply((res.data && res.data.message) ? res.data.message : 'Request failed.', true);
        }
      })
      .catch(function () {
        replaceThinkingWithReply('Network or server error. Try again.', true);
      })
      .finally(function () {
        setSendLoading(false);
        if (userInput) userInput.focus();
      });
  });

  if (btnNewChat) btnNewChat.addEventListener('click', startNewChat);

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
      })
      .catch(function () {
        if (settingsModelSelect) settingsModelSelect.value = 'deepseek|deepseek-chat';
        showApiKeyRow('deepseek');
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
    fetch(restUrl + '/settings', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
      body: JSON.stringify({ model: modelId, api_keys: apiKeys }),
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
