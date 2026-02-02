/**
 * Plugitify Chat – AI chat logic (wire getAIResponse to your API).
 */

(function () {
  'use strict';

  const messagesEl = document.getElementById('messages');
  const chatForm = document.getElementById('chatForm');
  const userInput = document.getElementById('userInput');
  const btnSend = document.getElementById('btnSend');
  const btnNewChat = document.getElementById('btnNewChat');
  const btnSettings = document.getElementById('btnSettings');
  const chatListPlaceholder = document.getElementById('chatListPlaceholder');
  const chatItems = document.getElementById('chatItems');

  if (!messagesEl || !chatForm || !userInput) return;

  function scrollToBottom() {
    function doScroll() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }
    requestAnimationFrame(function () {
      requestAnimationFrame(doScroll);
    });
    setTimeout(doScroll, 50);
  }

  function addUserMessage(text) {
    const time = new Date().toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
    });
    const html = `
      <div class="message user" role="listitem">
        <div class="message-avatar" aria-hidden="true">
          <span class="material-symbols-outlined">person</span>
        </div>
        <div class="message-bubble">
          <div class="message-text">${escapeHtml(text)}</div>
          <div class="message-time">${time}</div>
        </div>
      </div>
    `;
    messagesEl.insertAdjacentHTML('beforeend', html);
    messagesEl.classList.add('has-messages');
    scrollToBottom();
  }

  function addThinkingMessage() {
    const html = `
      <div class="message assistant thinking" id="thinking-msg" role="listitem" aria-busy="true">
        <div class="message-avatar" aria-hidden="true">
          <span class="material-symbols-outlined">auto_awesome</span>
        </div>
        <div class="message-bubble">
          <div class="thinking-inner">
            <span class="thinking-text">Thinking</span>
            <div class="thinking-dots" aria-hidden="true">
              <span></span><span></span><span></span>
            </div>
          </div>
        </div>
      </div>
    `;
    messagesEl.insertAdjacentHTML('beforeend', html);
    scrollToBottom();
  }

  /**
   * Replace thinking block with final assistant message (or error).
   * @param {string} text - Message or error text
   * @param {Array<{label: string}>|null} tasks - Optional task list
   * @param {string|null} finalText - Optional summary after tasks
   * @param {boolean} [isError] - If true, message is shown as error (distinct style)
   */
  function replaceThinkingWithReply(text, tasks, finalText, isError) {
    var thinkingEl = document.getElementById('thinking-msg');
    if (!thinkingEl) return;

    var time = new Date().toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
    });

    if (tasks && tasks.length > 0) {
      var firstLabel = tasks[0].label;
      var tasksHtml = '<ul class="task-list" data-task-list>';
      tasks.forEach(function (t, i) {
        tasksHtml +=
          '<li class="task-item pending" data-task-index="' + i + '">' +
          '<span class="material-symbols-outlined task-icon">radio_button_unchecked</span>' +
          '<span class="task-label">' + escapeHtml(t.label) + '</span></li>';
      });
      tasksHtml += '</ul>';
      var bubble = thinkingEl.querySelector('.message-bubble');
      if (bubble) {
        bubble.insertAdjacentHTML(
          'beforeend',
          '<div class="thinking-current-step" data-current-step>' + escapeHtml(firstLabel) + '</div>'
        );
        var body = document.createElement('div');
        body.className = 'message-body';
        bubble.parentNode.insertBefore(body, bubble);
        body.appendChild(bubble);
        body.insertAdjacentHTML('beforeend', tasksHtml);
      }
      scrollToBottom();
      runTaskExecution(tasks, text, finalText, time);
      return;
    }

    var msgClass = 'message assistant' + (isError ? ' msg-error' : '');
    var icon = isError ? 'error' : 'auto_awesome';
    var bubbleContent;
    if (isError) {
      // Compact error message (single text + hint)
      var errorText = text || 'Something went wrong';
      bubbleContent =
        '<div class="message-error-block" role="alert">' +
        '<div class="message-error-header">' +
        '<span class="material-symbols-outlined message-error-icon">error</span>' +
        '<span class="message-error-title">' + escapeHtml(errorText) + '</span>' +
        '</div>' +
        '<p class="message-error-hint">Check Settings or try again</p>' +
        '</div>' +
        '<div class="message-time">' + time + '</div>';
    } else {
      bubbleContent =
        (text ? '<div class="message-text">' + escapeHtml(text) + '</div>' : '') +
        '<div class="message-time">' + time + '</div>';
    }
    var html =
      '<div class="' + msgClass + '" role="listitem" data-assistant-message' + (isError ? ' data-error-message' : '') + '>' +
      '<div class="message-avatar">' +
      '<span class="material-symbols-outlined">' + icon + '</span>' +
      '</div>' +
      '<div class="message-bubble">' + bubbleContent + '</div></div>';
    thinkingEl.outerHTML = html;
    scrollToBottom();
  }

  /**
   * Keeps Thinking visible until all tasks are done. Updates "current step" title under Thinking.
   * When all done, replaces Thinking with the final message.
   */
  function runTaskExecution(tasks, introText, finalText, time) {
    var thinkingEl = document.getElementById('thinking-msg');
    if (!thinkingEl) return;
    var list = thinkingEl.querySelector('.task-list[data-task-list]');
    var currentStepEl = thinkingEl.querySelector('.thinking-current-step[data-current-step]');
    if (!list || !currentStepEl) return;
    var items = list.querySelectorAll('.task-item');
    var index = 0;
    var stepDelay = 700;

    function markNextDone() {
      if (index >= items.length) {
        replaceThinkingWithFinalMessage(thinkingEl, introText, tasks, finalText, time);
        scrollToBottom();
        return;
      }
      currentStepEl.textContent = tasks[index].label;
      var el = items[index];
      el.classList.remove('pending');
      el.classList.add('done');
      var icon = el.querySelector('.task-icon');
      if (icon) icon.textContent = 'check_circle';
      var label = el.querySelector('.task-label');
      if (label) label.style.textDecoration = 'line-through';
      index++;
      scrollToBottom();
      setTimeout(markNextDone, stepDelay);
    }
    setTimeout(markNextDone, stepDelay);
  }

  function replaceThinkingWithFinalMessage(thinkingEl, introText, tasks, finalText, time) {
    var tasksHtml = '<ul class="task-list" data-task-list>';
    tasks.forEach(function (t) {
      tasksHtml +=
        '<li class="task-item done">' +
        '<span class="material-symbols-outlined task-icon">check_circle</span>' +
        '<span class="task-label">' + escapeHtml(t.label) + '</span></li>';
    });
    tasksHtml += '</ul>';
    var finalBlock = finalText ? '<div class="message-summary visible">' + escapeHtml(finalText) + '</div>' : '';
    var html =
      '<div class="message assistant" role="listitem" data-assistant-message">' +
      '<div class="message-avatar" aria-hidden="true">' +
      '<span class="material-symbols-outlined">auto_awesome</span>' +
      '</div>' +
      '<div class="message-body">' +
      '<div class="message-bubble">' +
      (introText ? '<div class="message-text">' + escapeHtml(introText) + '</div>' : '') +
      '<div class="message-time">' + time + '</div>' +
      '</div>' +
      tasksHtml +
      finalBlock +
      '</div></div>';
    thinkingEl.outerHTML = html;
    scrollToBottom();
  }

  /**
   * Mock AI response. Replace with your API / LLM call.
   * The model returns a plan (tasks it will do); then you run them and call
   * onTaskDone(index) / stream completion. Here we simulate: return plan, then
   * runTaskExecution marks each task done in sequence.
   * @param {string} userText
   * @returns {Promise<string|{text: string, tasks?: Array<{label: string}>, finalText?: string}>}
   */
  var currentChatId = null;

  /**
   * Send chat message with streaming (SSE).
   * Calls onChunk(text) for each chunk, onChatId(id) when chat_id arrives, onDone() when complete.
   * Throws on error.
   */
  function sendChatMessageStream(messageText, onChunk, onChatId, onDone, onError) {
    var restUrl = typeof plugitifyChat !== 'undefined' ? (plugitifyChat.restUrl || '') : '';
    var restNonce = typeof plugitifyChat !== 'undefined' ? (plugitifyChat.nonce || '') : '';
    if (!restUrl || !restNonce) {
      onError(new Error('API not available'));
      return;
    }
    var payload = { message: messageText };
    if (currentChatId !== null) payload.chat_id = currentChatId;

    fetch(restUrl + '/chat/stream', {
      method: 'POST',
      headers: {
        'X-WP-Nonce': restNonce,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Stream request failed');
        }
        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = '';

        var streamComplete = false;
        function processChunk() {
          reader.read().then(function (result) {
            if (result.done) {
              // Stream ended
              if (!streamComplete && onDone) {
                onDone();
              }
              return;
            }

            buffer += decoder.decode(result.value, { stream: true });
            var lines = buffer.split('\n');
            buffer = lines.pop(); // Keep incomplete line in buffer

            var currentEvent = null;
            lines.forEach(function (line) {
              if (line.startsWith('event: ')) {
                currentEvent = line.substring(7).trim();
              } else if (line.startsWith('data: ')) {
                var dataStr = line.substring(6);
                try {
                  var data = JSON.parse(dataStr);
                  if (currentEvent === 'chat_id' && data.chat_id) {
                    console.log('[SSE] Received chat_id:', data.chat_id);
                    currentChatId = data.chat_id;
                    if (onChatId) onChatId(data.chat_id);
                  } else if (currentEvent === 'chunk' && data.text) {
                    console.log('[SSE] Received chunk:', data.text);
                    if (onChunk) onChunk(data.text);
                  } else if (currentEvent === 'done') {
                    console.log('[SSE] Stream done');
                    streamComplete = true;
                    if (onDone) onDone();
                    return;
                  } else if (currentEvent === 'error') {
                    console.error('[SSE] Stream error:', data);
                    streamComplete = true;
                    var err = new Error(data.message || 'Stream error');
                    if (onError) onError(err);
                    return;
                  }
                } catch (e) {
                  console.error('Failed to parse SSE data:', dataStr, e);
                }
                currentEvent = null;
              }
            });

            processChunk();
          }).catch(function (err) {
            if (onError) onError(err);
          });
        }

        processChunk();
      })
      .catch(function (err) {
        if (onError) onError(err);
      });
  }

  function sendChatMessage(messageText) {
    var restUrl = typeof plugitifyChat !== 'undefined' ? (plugitifyChat.restUrl || '') : '';
    var restNonce = typeof plugitifyChat !== 'undefined' ? (plugitifyChat.nonce || '') : '';
    if (!restUrl || !restNonce) {
      return Promise.reject(new Error('API not available'));
    }
    var payload = { message: messageText };
    if (currentChatId !== null) payload.chat_id = currentChatId;
    return fetch(restUrl + '/chat', {
      method: 'POST',
      headers: {
        'X-WP-Nonce': restNonce,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    })
      .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
      .then(function (result) {
        if (!result.ok && result.data && result.data.error && result.data.error.message) {
          var err = new Error(result.data.error.message);
          err.code = result.data.error.code;
          throw err;
        }
        if (!result.ok) throw new Error('Request failed');
        return result.data;
      });
  }

  function getAIResponse_REMOVED(userText) {
    return new Promise(function (resolve) {
      var delay = 600 + Math.random() * 400;
      setTimeout(function () {
        var roll = Math.random();
        if (roll < 0.5) {
          resolve({
            text: "I'll do the following:",
            tasks: [
              { label: 'Reading your request' },
              { label: 'Searching the codebase' },
              { label: 'Editing files' },
              { label: 'Running checks' },
            ],
            finalText: 'All set. You can wire this to your real LLM and task execution.',
          });
        } else if (roll < 0.8) {
          resolve({
            text: 'Working on it:',
            tasks: [
              { label: 'Analyzing the question' },
              { label: 'Fetching context' },
              { label: 'Generating response' },
            ],
            finalText: 'Done.',
          });
        } else {
          resolve(
            'No tasks this time — here’s a direct reply. When your LLM returns a task plan, the UI will show tasks and complete them one by one.'
          );
        }
      }, delay);
    });
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  var currentRequestCancelled = false;

  function setLoading(loading) {
    userInput.disabled = loading;
    if (loading) {
      btnSend.type = 'button';
      btnSend.setAttribute('aria-label', 'Stop');
      btnSend.innerHTML = '<span class="material-symbols-outlined">stop</span>';
      btnSend.classList.add('btn-stop');
      btnSend.onclick = handleStopClick;
    } else {
      btnSend.type = 'submit';
      btnSend.setAttribute('aria-label', 'Send message');
      btnSend.innerHTML = '<span class="material-symbols-outlined">send</span>';
      btnSend.classList.remove('btn-stop');
      btnSend.onclick = null;
    }
  }

  function handleStopClick() {
    currentRequestCancelled = true;
    var thinkingEl = document.getElementById('thinking-msg');
    if (thinkingEl) thinkingEl.remove();
    setLoading(false);
    userInput.focus();
  }

  function startNewChat() {
    currentChatId = null;
    var welcome = document.getElementById('welcome');
    messagesEl.querySelectorAll('.message').forEach(function (el) { el.remove(); });
    messagesEl.classList.remove('has-messages');
    if (welcome) welcome.style.display = '';
    userInput.value = '';
    userInput.focus();
  }

  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = userInput.value.trim();
    if (!text) return;

    currentRequestCancelled = false;
    userInput.value = '';
    addUserMessage(text);
    
    // Add streaming message (starts empty)
    var time = new Date().toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
    });
    var streamingMsgId = 'streaming-msg-' + Date.now();
    var html =
      '<div class="message assistant streaming" id="' + streamingMsgId + '" role="listitem">' +
      '<div class="message-avatar" aria-hidden="true">' +
      '<span class="material-symbols-outlined">auto_awesome</span>' +
      '</div>' +
      '<div class="message-body">' +
      '<div class="message-bubble">' +
      '<div class="message-text" id="' + streamingMsgId + '-text"></div>' +
      '<div class="message-time">' + time + '</div>' +
      '</div>' +
      '</div></div>';
    messagesEl.insertAdjacentHTML('beforeend', html);
    messagesEl.classList.add('has-messages');
    scrollToBottom();

    setLoading(true);
    var streamingTextEl = document.getElementById(streamingMsgId + '-text');
    var fullText = '';

    sendChatMessageStream(
      text,
      function onChunk(chunk) {
        if (currentRequestCancelled) return;
        fullText += chunk;
        if (streamingTextEl) {
          streamingTextEl.textContent = fullText;
          scrollToBottom();
        }
      },
      function onChatId(chatId) {
        if (currentRequestCancelled) return;
        currentChatId = chatId;
        // Add new chat to sidebar list
        if (chatItems && chatListPlaceholder) {
          chatListPlaceholder.style.display = 'none';
          var chatHtml = '<li class="chat-item active" data-chat-id="' + chatId + '">' +
            '<div class="chat-item-content">' +
            '<div class="chat-title">New Chat</div>' +
            '<div class="chat-preview"></div>' +
            '</div></li>';
          chatItems.insertAdjacentHTML('afterbegin', chatHtml);
        }
      },
      function onDone() {
        if (currentRequestCancelled) return;
        var streamingMsg = document.getElementById(streamingMsgId);
        if (streamingMsg) {
          streamingMsg.classList.remove('streaming');
        }
        setLoading(false);
        userInput.focus();
      },
      function onError(err) {
        if (currentRequestCancelled) return;
        var msg = (err && err.message) ? err.message : 'Something went wrong. Try again or check Settings.';
        var streamingMsg = document.getElementById(streamingMsgId);
        if (streamingMsg) {
          streamingMsg.outerHTML =
            '<div class="message assistant msg-error" role="listitem" data-assistant-message="" data-error-message="">' +
            '<div class="message-avatar" aria-hidden="true">' +
            '<span class="material-symbols-outlined">error</span>' +
            '</div>' +
            '<div class="message-bubble">' +
            '<div class="error-header">' +
            '<span class="material-symbols-outlined">error</span>' +
            '<span class="error-title">Something went wrong</span>' +
            '</div>' +
            '<div class="error-body">' + escapeHtml(msg) + '</div>' +
            '<div class="message-time">' + time + '</div>' +
            '</div></div>';
        }
        setLoading(false);
        userInput.focus();
      }
    );
  });

  if (btnNewChat) {
    btnNewChat.addEventListener('click', startNewChat);
  }

  /* ----- Settings modal (stored in WordPress options, per-provider API keys) ----- */
  var settingsOverlay = document.getElementById('settingsOverlay');
  var settingsModal = document.getElementById('settingsModal');
  var settingsModelSelect = document.getElementById('settingsModel');
  var apiKeysSection = document.getElementById('apiKeysSection');
  var settingsSave = document.getElementById('settingsSave');
  var settingsCloseBtn = document.getElementById('settingsCloseBtn');
  var settingsCloseIcon = document.getElementById('settingsClose');
  var settingsStatus = document.getElementById('settingsStatus');
  var settingsStatusIcon = document.getElementById('settingsStatusIcon');
  var settingsMessage = document.getElementById('settingsMessage');
  var settingsRetry = document.getElementById('settingsRetry');
  var settingsLoading = document.getElementById('settingsLoading');
  var settingsAllowDbWrites = document.getElementById('settingsAllowDbWrites');
  var restUrl = typeof plugitifyChat !== 'undefined' ? (plugitifyChat.restUrl || '') : '';
  var restNonce = typeof plugitifyChat !== 'undefined' ? (plugitifyChat.nonce || '') : '';
  var apiKeyInputIds = { deepseek: 'apiKeyDeepSeek', chatgpt: 'apiKeyChatGPT', gemini: 'apiKeyGemini', claude: 'apiKeyClaude' };

  function getProviderFromModelValue(value) {
    if (!value) return 'deepseek';
    var parts = value.split('|');
    return parts[0] || 'deepseek';
  }

  function setSettingsLoading(show) {
    if (!settingsLoading) return;
    settingsLoading.hidden = !show;
    if (settingsSave) settingsSave.disabled = show;
  }

  function showSettingsMessage(text, isError) {
    if (!settingsStatus || !settingsMessage) return;
    if (!text) {
      settingsStatus.hidden = true;
      settingsStatus.classList.remove('success', 'error');
      if (settingsRetry) settingsRetry.hidden = true;
      return;
    }
    settingsStatus.hidden = false;
    settingsStatus.classList.remove('success', 'error');
    settingsStatus.classList.add(isError ? 'error' : 'success');
    if (settingsStatusIcon) {
      var iconName = isError ? 'error' : 'check_circle';
      settingsStatusIcon.className = 'settings-status-icon';
      settingsStatusIcon.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + iconName + '</span>';
    }
    settingsMessage.textContent = text;
    if (settingsRetry) {
      settingsRetry.hidden = isError ? false : true;
      settingsRetry.style.display = isError ? '' : 'none';
    }
  }

  function openSettings() {
    if (!settingsOverlay) return;
    settingsOverlay.setAttribute('aria-hidden', 'false');
    showSettingsMessage('');
    if (!restUrl || !restNonce) {
      applySettingsToForm({ model: 'deepseek|deepseek-chat', apiKeys: {}, allowDbWrites: false });
      showApiKeyRow('deepseek');
      return;
    }
    setSettingsLoading(true);
    fetch(restUrl + '/settings', {
      method: 'GET',
      headers: {
        'X-WP-Nonce': restNonce,
        'Content-Type': 'application/json',
      },
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function (data) {
        applySettingsToForm(data);
        showApiKeyRow(getProviderFromModelValue(settingsModelSelect ? settingsModelSelect.value : null));
      })
      .catch(function () {
        applySettingsToForm({ model: 'deepseek|deepseek-chat', apiKeys: {}, allowDbWrites: false });
        showApiKeyRow('deepseek');
        showSettingsMessage('Could not load settings.', true);
      })
      .finally(function () {
        setSettingsLoading(false);
      });
  }

  function closeSettings() {
    if (!settingsOverlay) return;
    settingsOverlay.setAttribute('aria-hidden', 'true');
    showSettingsMessage('');
  }

  function showApiKeyRow(providerId) {
    if (!apiKeysSection) return;
    apiKeysSection.querySelectorAll('.settings-api-row').forEach(function (row) {
      row.classList.toggle('visible', row.getAttribute('data-model') === providerId);
    });
  }

  function applySettingsToForm(data) {
    if (!data) return;
    if (settingsModelSelect && data.model) {
      var opt = settingsModelSelect.querySelector('option[value="' + data.model + '"]');
      if (opt) {
        settingsModelSelect.value = data.model;
      } else {
        var firstOpt = settingsModelSelect.querySelector('option[value^="' + (data.model.split('|')[0] || '') + '|"]');
        settingsModelSelect.value = firstOpt ? firstOpt.value : 'deepseek|deepseek-chat';
      }
    } else if (settingsModelSelect) {
      settingsModelSelect.value = 'deepseek|deepseek-chat';
    }
    if (data.apiKeys) {
      Object.keys(apiKeyInputIds).forEach(function (key) {
        var input = document.getElementById(apiKeyInputIds[key]);
        if (input && data.apiKeys[key] !== undefined) input.value = data.apiKeys[key];
      });
    }
    if (settingsAllowDbWrites) settingsAllowDbWrites.checked = !!data.allowDbWrites;
  }

  function getSettingsFromForm() {
    var modelId = settingsModelSelect ? settingsModelSelect.value : 'deepseek|deepseek-chat';
    var apiKeys = { deepseek: '', chatgpt: '', gemini: '', claude: '' };
    Object.keys(apiKeyInputIds).forEach(function (key) {
      var input = document.getElementById(apiKeyInputIds[key]);
      if (input) apiKeys[key] = input.value ? input.value.trim() : '';
    });
    var allowDbWrites = settingsAllowDbWrites ? settingsAllowDbWrites.checked : false;
    return { model: modelId, apiKeys: apiKeys, allowDbWrites: allowDbWrites };
  }

  function saveSettings() {
    if (!restUrl || !restNonce) {
      showSettingsMessage('Settings API not available.', true);
      return;
    }
    var payload = getSettingsFromForm();
    setSettingsLoading(true);
    showSettingsMessage('');
    fetch(restUrl + '/settings', {
      method: 'POST',
      headers: {
        'X-WP-Nonce': restNonce,
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(payload),
    })
      .then(function (res) {
        if (!res.ok) return Promise.reject(res);
        return res.json();
      })
      .then(function () {
        showSettingsMessage('Saved successfully.', false);
      })
      .catch(function (res) {
        var msg = 'Could not save settings. ';
        if (res && typeof res.status === 'number') {
          if (res.status === 403) msg += 'Permission denied.';
          else if (res.status === 404) msg += 'Endpoint not found.';
          else msg += 'Try again.';
        } else {
          msg += 'Check your connection and try again.';
        }
        showSettingsMessage(msg, true);
      })
      .finally(function () {
        setSettingsLoading(false);
      });
  }

  (function loadSettingsOnPageLoad() {
    if (!restUrl || !restNonce) return;
    fetch(restUrl + '/settings', {
      method: 'GET',
      headers: { 'X-WP-Nonce': restNonce },
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (data) applySettingsToForm(data);
      })
      .catch(function () {});
  })();

  if (btnSettings) btnSettings.addEventListener('click', openSettings);
  if (settingsCloseIcon) settingsCloseIcon.addEventListener('click', closeSettings);
  if (settingsCloseBtn) settingsCloseBtn.addEventListener('click', closeSettings);
  if (settingsSave) settingsSave.addEventListener('click', saveSettings);
  if (settingsRetry) settingsRetry.addEventListener('click', saveSettings);
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

  if (btnSend) userInput.focus();
})();
