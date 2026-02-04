/**
 * Plugitify Chat – AI chat logic (wire getAIResponse to your API).
 */

(function () {
  'use strict';

  const messagesEl = document.getElementById('agentify-messages');
  const chatForm = document.getElementById('agentify-chat-form');
  const userInput = document.getElementById('agentify-user-input');
  const btnSend = document.getElementById('agentify-btn-send');
  const btnNewChat = document.getElementById('agentify-btn-new-chat');
  const chatListPlaceholder = document.getElementById('agentify-chat-list-placeholder');
  const chatItems = document.getElementById('agentify-chat-items');

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
      <div class="agentify-message agentify-user" role="listitem">
        <div class="agentify-message-avatar" aria-hidden="true">
          <span class="material-symbols-outlined">person</span>
        </div>
        <div class="agentify-message-bubble">
          <div class="agentify-message-text">${formatMessageContent(text)}</div>
          <div class="agentify-message-time">${time}</div>
        </div>
      </div>
    `;
    messagesEl.insertAdjacentHTML('beforeend', html);
    messagesEl.classList.add('agentify-has-messages');
    scrollToBottom();
  }

  function addThinkingMessage() {
    const html = `
      <div class="agentify-message agentify-assistant agentify-thinking" id="agentify-thinking-msg" role="listitem" aria-busy="true">
        <div class="agentify-message-avatar" aria-hidden="true">
          <span class="material-symbols-outlined">auto_awesome</span>
        </div>
        <div class="agentify-message-bubble">
          <div class="agentify-thinking-inner">
            <span class="agentify-thinking-text">Thinking</span>
            <div class="agentify-thinking-dots" aria-hidden="true">
              <span></span><span></span><span></span>
            </div>
          </div>
        </div>
      </div>
    `;
    messagesEl.insertAdjacentHTML('beforeend', html);
    scrollToBottom();
  }

  function replaceThinkingWithReply(text, tasks, finalText) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) return;

    var time = new Date().toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
    });

    if (tasks && tasks.length > 0) {
      var firstLabel = tasks[0].label;
      var tasksHtml = '<ul class="agentify-task-list" data-task-list>';
      tasks.forEach(function (t, i) {
        tasksHtml +=
          '<li class="agentify-task-item agentify-pending" data-task-index="' + i + '">' +
          '<span class="material-symbols-outlined agentify-task-icon">radio_button_unchecked</span>' +
          '<span class="agentify-task-label">' + escapeHtml(t.label) + '</span></li>';
      });
      tasksHtml += '</ul>';
      var bubble = thinkingEl.querySelector('.agentify-message-bubble');
      if (bubble) {
        bubble.insertAdjacentHTML(
          'beforeend',
          '<div class="agentify-thinking-current-step" data-current-step>' + escapeHtml(firstLabel) + '</div>'
        );
        var body = document.createElement('div');
        body.className = 'agentify-message-body';
        bubble.parentNode.insertBefore(body, bubble);
        body.appendChild(bubble);
        body.insertAdjacentHTML('beforeend', tasksHtml);
      }
      scrollToBottom();
      runTaskExecution(tasks, text, finalText, time);
      return;
    }

    var html =
      '<div class="agentify-message agentify-assistant" role="listitem" data-assistant-message>' +
      '<div class="agentify-message-avatar" aria-hidden="true">' +
      '<span class="material-symbols-outlined">auto_awesome</span>' +
      '</div>' +
      '<div class="agentify-message-bubble">' +
      (text ? '<div class="agentify-message-text">' + formatMessageContent(text) + '</div>' : '') +
      '<div class="agentify-message-time">' + time + '</div>' +
      '</div></div>';
    thinkingEl.outerHTML = html;
    scrollToBottom();
  }

  /**
   * Keeps Thinking visible until all tasks are done. Updates "current step" title under Thinking.
   * When all done, replaces Thinking with the final message.
   */
  function runTaskExecution(tasks, introText, finalText, time) {
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (!thinkingEl) return;
    var list = thinkingEl.querySelector('.agentify-task-list[data-task-list]');
    var currentStepEl = thinkingEl.querySelector('.agentify-thinking-current-step[data-current-step]');
    if (!list || !currentStepEl) return;
    var items = list.querySelectorAll('.agentify-task-item');
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
      el.classList.remove('agentify-pending');
      el.classList.add('agentify-done');
      var icon = el.querySelector('.agentify-task-icon');
      if (icon) icon.textContent = 'check_circle';
      var label = el.querySelector('.agentify-task-label');
      if (label) label.style.textDecoration = 'line-through';
      index++;
      scrollToBottom();
      setTimeout(markNextDone, stepDelay);
    }
    setTimeout(markNextDone, stepDelay);
  }

  function replaceThinkingWithFinalMessage(thinkingEl, introText, tasks, finalText, time) {
    var tasksHtml = '<ul class="agentify-task-list" data-task-list>';
    tasks.forEach(function (t) {
      tasksHtml +=
        '<li class="agentify-task-item agentify-done">' +
        '<span class="material-symbols-outlined agentify-task-icon">check_circle</span>' +
        '<span class="agentify-task-label">' + escapeHtml(t.label) + '</span></li>';
    });
    tasksHtml += '</ul>';
    var finalBlock = finalText ? '<div class="agentify-message-summary agentify-visible">' + formatMessageContent(finalText) + '</div>' : '';
    var html =
      '<div class="agentify-message agentify-assistant" role="listitem" data-assistant-message">' +
      '<div class="agentify-message-avatar" aria-hidden="true">' +
      '<span class="material-symbols-outlined">auto_awesome</span>' +
      '</div>' +
      '<div class="agentify-message-body">' +
      '<div class="agentify-message-bubble">' +
      (introText ? '<div class="agentify-message-text">' + formatMessageContent(introText) + '</div>' : '') +
      '<div class="agentify-message-time">' + time + '</div>' +
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
  function getAIResponse(userText) {
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

  /** Format message content: escape HTML, then newlines + basic markdown (**, *, `, [text](url)) */
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

  var currentRequestCancelled = false;

  function setLoading(loading) {
    userInput.disabled = loading;
    if (loading) {
      btnSend.type = 'button';
      btnSend.setAttribute('aria-label', 'Stop');
      btnSend.innerHTML = '<span class="material-symbols-outlined">stop</span>';
      btnSend.classList.add('agentify-btn-stop');
      btnSend.onclick = handleStopClick;
    } else {
      btnSend.type = 'submit';
      btnSend.setAttribute('aria-label', 'Send message');
      btnSend.innerHTML = '<span class="material-symbols-outlined">send</span>';
      btnSend.classList.remove('agentify-btn-stop');
      btnSend.onclick = null;
    }
  }

  function handleStopClick() {
    currentRequestCancelled = true;
    var thinkingEl = document.getElementById('agentify-thinking-msg');
    if (thinkingEl) thinkingEl.remove();
    setLoading(false);
    userInput.focus();
  }

  function startNewChat() {
    var welcome = document.getElementById('agentify-welcome');
    messagesEl.querySelectorAll('.agentify-message').forEach(function (el) { el.remove(); });
    messagesEl.classList.remove('agentify-has-messages');
    if (welcome) welcome.style.display = '';
    userInput.value = '';
    resizeInput();
    userInput.focus();
  }

  /* Enter = submit, Shift+Enter = new line */
  userInput.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    if (e.shiftKey) {
      var start = userInput.selectionStart;
      var end = userInput.selectionEnd;
      var val = userInput.value;
      userInput.value = val.substring(0, start) + '\n' + val.substring(end);
      userInput.selectionStart = userInput.selectionEnd = start + 1;
      resizeInput();
    } else {
      chatForm.requestSubmit();
    }
  });

  /* Auto-resize textarea, max ~3 lines then scroll */
  var lineHeightPx = 22;
  var maxHeightPx = lineHeightPx * 3;
  function resizeInput() {
    userInput.style.height = 'auto';
    userInput.style.height = Math.min(userInput.scrollHeight, maxHeightPx) + 'px';
  }
  userInput.addEventListener('input', resizeInput);
  resizeInput();

  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = userInput.value.trim();
    if (!text) return;

    currentRequestCancelled = false;
    userInput.value = '';
    resizeInput();
    addUserMessage(text);
    addThinkingMessage();
    setLoading(true);

    getAIResponse(text)
      .then(function (result) {
        if (currentRequestCancelled) return;
        var textOut = typeof result === 'string' ? result : (result && result.text) || '';
        var tasks = typeof result === 'string' ? null : (result && result.tasks) || null;
        var finalText = result && result.finalText ? result.finalText : null;
        replaceThinkingWithReply(textOut, tasks, finalText);
      })
      .catch(function () {
        if (currentRequestCancelled) return;
        replaceThinkingWithReply('Something went wrong. Try again or connect to your API later.', null, null);
      })
      .finally(function () {
        if (!currentRequestCancelled) setLoading(false);
        userInput.focus();
      });
  });

  if (btnNewChat) {
    btnNewChat.addEventListener('click', startNewChat);
  }

  /* ----- Settings modal ----- */
  var btnSettings = document.getElementById('agentify-btn-settings');
  var settingsOverlay = document.getElementById('agentify-settings-overlay');
  var settingsModal = document.getElementById('agentify-settings-modal');
  var settingsModelSelect = document.getElementById('agentify-settings-model');
  var apiKeysSection = document.getElementById('agentify-api-keys-section');
  var settingsSave = document.getElementById('agentify-settings-save');
  var settingsCloseBtn = document.getElementById('agentify-settings-close-btn');
  var settingsCloseIcon = document.getElementById('agentify-settings-close');
  var STORAGE_KEY = 'agentify_settings';

  function getProviderFromModelValue(value) {
    if (!value) return 'deepseek';
    var parts = value.split('|');
    return parts[0] || 'deepseek';
  }

  function openSettings() {
    if (!settingsOverlay) return;
    settingsOverlay.setAttribute('aria-hidden', 'false');
    loadSettingsIntoModal();
    showApiKeyRow(getProviderFromModelValue(settingsModelSelect ? settingsModelSelect.value : null));
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
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      var data = raw ? JSON.parse(raw) : {};
      if (settingsModelSelect && data.model) {
        if (data.model.indexOf('|') >= 0) {
          var opt = settingsModelSelect.querySelector('option[value="' + data.model + '"]');
          settingsModelSelect.value = opt ? data.model : 'deepseek|deepseek-chat';
        } else {
          var firstOpt = settingsModelSelect.querySelector('option[value^="' + data.model + '|"]');
          settingsModelSelect.value = firstOpt ? firstOpt.value : 'deepseek|deepseek-chat';
        }
      } else if (settingsModelSelect) {
        settingsModelSelect.value = 'deepseek|deepseek-chat';
      }
      Object.keys(apiKeyInputIds).forEach(function (key) {
        var input = document.getElementById(apiKeyInputIds[key]);
        if (input && data.apiKeys && data.apiKeys[key] !== undefined) input.value = data.apiKeys[key];
      });
    } catch (e) {}
  }

  function saveSettings() {
    try {
      var modelId = settingsModelSelect ? settingsModelSelect.value : 'deepseek|deepseek-chat';
      var apiKeys = {};
      if (apiKeysSection) {
        apiKeysSection.querySelectorAll('.agentify-settings-api-row').forEach(function (row) {
          var model = row.getAttribute('data-model');
          var input = row.querySelector('input');
          if (model && input) apiKeys[model] = input.value;
        });
      }
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ model: modelId, apiKeys: apiKeys }));
    } catch (e) {}
    closeSettings();
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

  if (btnSend) userInput.focus();
})();
