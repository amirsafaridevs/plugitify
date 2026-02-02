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
    const dir = detectTextDirection(text);
    const html = `
      <div class="message user" role="listitem" dir="${dir}">
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
          <div class="thinking-current-step" id="thinking-current-step" data-current-step></div>
        </div>
      </div>
    `;
    messagesEl.insertAdjacentHTML('beforeend', html);
    scrollToBottom();
  }

  function updateThinkingStep(stepText) {
    console.log('[updateThinkingStep] Called with:', stepText);
    var stepEl = document.getElementById('thinking-current-step');
    console.log('[updateThinkingStep] Element found:', !!stepEl);
    if (stepEl && stepText) {
      stepEl.textContent = stepText;
      stepEl.style.display = 'block';
      console.log('[updateThinkingStep] Updated step text to:', stepText);
    }
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
   * Calls onChunk(text) for each chunk, onChatId(id) when chat_id arrives, 
   * onTask(task) for tasks, onDone() when complete.
   * Throws on error.
   */
  function sendChatMessageStream(messageText, onChunk, onChatId, onTask, onDone, onError) {
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
                    console.log('[SSE] ✅ Received chat_id:', data.chat_id);
                    currentChatId = data.chat_id;
                    if (onChatId) onChatId(data.chat_id);
                  } else if (currentEvent === 'step' && data.step) {
                    console.log('[SSE] 🔄 Current step:', data.step);
                    updateThinkingStep(data.step);
                  } else if (currentEvent === 'task' && data.task) {
                    console.log('[SSE] 📋 Task event:', JSON.stringify(data.task));
                    if (onTask) onTask(data.task);
                  } else if (currentEvent === 'chunk' && data.text) {
                    console.log('[SSE] 📝 Received chunk (length=' + data.text.length + '):', data.text.substring(0, 50));
                    if (onChunk) onChunk(data.text);
                  } else if (currentEvent === 'done') {
                    console.log('[SSE] ✅ Stream done');
                    streamComplete = true;
                    if (onDone) onDone();
                    return;
                  } else if (currentEvent === 'error') {
                    console.error('[SSE] ❌ Stream error:', data);
                    streamComplete = true;
                    var err = new Error(data.message || 'Stream error');
                    if (onError) onError(err);
                    return;
                  } else {
                    console.warn('[SSE] ⚠️ Unknown event:', currentEvent, 'data:', dataStr);
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

  /**
   * Simple markdown to HTML converter
   */
  function markdownToHtml(text) {
    if (!text) return '';
    
    // Escape HTML first
    var html = text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
    
    // Code blocks (```code```)
    html = html.replace(/```([^`]+)```/g, '<pre><code>$1</code></pre>');
    
    // Inline code (`code`)
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Bold (**text** or __text__)
    html = html.replace(/\*\*([^\*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    
    // Italic (*text* or _text_) - but not if already in bold
    html = html.replace(/([^*])\*([^\*]+)\*([^*])/g, '$1<em>$2</em>$3');
    html = html.replace(/([^_])_([^_]+)_([^_])/g, '$1<em>$2</em>$3');
    
    // Headers (### Header)
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
    
    // Lists (line by line processing)
    var lines = html.split('\n');
    var inList = false;
    var listType = '';
    var result = [];
    for (var i = 0; i < lines.length; i++) {
      var line = lines[i].trim();
      
      // Unordered list
      if (/^[\-\*] (.+)$/.test(line)) {
        if (!inList || listType !== 'ul') {
          if (inList) result.push(listType === 'ol' ? '</ol>' : '</ul>');
          result.push('<ul>');
          inList = true;
          listType = 'ul';
        }
        result.push(line.replace(/^[\-\*] (.+)$/, '<li>$1</li>'));
      }
      // Ordered list
      else if (/^\d+\. (.+)$/.test(line)) {
        if (!inList || listType !== 'ol') {
          if (inList) result.push(listType === 'ol' ? '</ol>' : '</ul>');
          result.push('<ol>');
          inList = true;
          listType = 'ol';
        }
        result.push(line.replace(/^\d+\. (.+)$/, '<li>$1</li>'));
      }
      // Not a list item
      else {
        if (inList) {
          result.push(listType === 'ol' ? '</ol>' : '</ul>');
          inList = false;
          listType = '';
        }
        result.push(lines[i]); // Keep original spacing
      }
    }
    if (inList) {
      result.push(listType === 'ol' ? '</ol>' : '</ul>');
    }
    html = result.join('\n');
    
    // Links [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    
    return html;
  }

  /**
   * Detect text direction (RTL for Arabic/Persian/Hebrew, LTR otherwise)
   */
  function detectTextDirection(text) {
    if (!text) return 'ltr';
    
    // RTL Unicode ranges: Arabic (U+0600-U+06FF), Hebrew (U+0590-U+05FF), Persian extensions
    var rtlChars = /[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF\u0590-\u05FF]/;
    
    // Check first 100 chars for RTL characters
    var sample = text.substring(0, 100);
    return rtlChars.test(sample) ? 'rtl' : 'ltr';
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
    
    // Remove active state from all chat items
    if (chatItems) {
      chatItems.querySelectorAll('.chat-item').forEach(function (el) {
        el.classList.remove('active');
      });
    }
  }

  chatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = userInput.value.trim();
    if (!text) return;

    currentRequestCancelled = false;
    userInput.value = '';
    addUserMessage(text);
    addThinkingMessage();
    setLoading(true);
    
    var fullText = '';
    var hasStartedStreaming = false;
    var tasks = [];
    var currentTaskIndex = -1;

    sendChatMessageStream(
      text,
      function onChunk(chunk) {
        if (currentRequestCancelled) return;
        
        // Replace thinking with streaming message on first chunk
        if (!hasStartedStreaming) {
          hasStartedStreaming = true;
          var thinkingEl = document.getElementById('thinking-msg');
          if (thinkingEl) {
            console.log('[onChunk] First chunk - converting thinking to streaming');
            
            // Save task list before replacing thinking
            var existingTaskList = thinkingEl.querySelector('.task-list');
            console.log('[onChunk] Existing task list:', existingTaskList);
            
            var time = new Date().toLocaleTimeString('en-US', {
              hour: '2-digit',
              minute: '2-digit',
            });
            var streamingMsgId = 'streaming-msg-' + Date.now();
            
            // Remove thinking classes, keep element
            thinkingEl.id = streamingMsgId;
            thinkingEl.classList.remove('thinking');
            thinkingEl.classList.add('streaming');
            thinkingEl.removeAttribute('aria-busy');
            
            // Update bubble content but keep task list
            var messageBubble = thinkingEl.querySelector('.message-bubble');
            if (messageBubble) {
              // Remove thinking-inner and thinking-current-step
              var thinkingInner = messageBubble.querySelector('.thinking-inner');
              if (thinkingInner) thinkingInner.remove();
              var thinkingStep = messageBubble.querySelector('.thinking-current-step');
              if (thinkingStep) thinkingStep.remove();
              
              // Add message-text and time (task-list stays)
              var newContent = '<div class="message-text" id="' + streamingMsgId + '-text"></div>' +
                '<div class="message-time">' + time + '</div>';
              messageBubble.insertAdjacentHTML('afterbegin', newContent);
            }
            
            window.currentStreamingMsgId = streamingMsgId;
            console.log('[onChunk] Converted to streaming, task list preserved');
          }
        }
        
        fullText += chunk;
        var streamingTextEl = document.getElementById(window.currentStreamingMsgId + '-text');
        if (streamingTextEl) {
          // Render markdown and detect direction
          streamingTextEl.innerHTML = markdownToHtml(fullText);
          var streamingMsg = document.getElementById(window.currentStreamingMsgId);
          if (streamingMsg) {
            streamingMsg.setAttribute('dir', detectTextDirection(fullText));
          }
          scrollToBottom();
        }
      },
      function onChatId(chatId) {
        if (currentRequestCancelled) return;
        currentChatId = chatId;
        
        // Add new chat to sidebar list if not already present
        if (chatItems && chatListPlaceholder) {
          // Check if chat already exists
          var existingChat = chatItems.querySelector('.chat-item[data-chat-id="' + chatId + '"]');
          if (!existingChat) {
            chatListPlaceholder.style.display = 'none';
            chatItems.hidden = false;
            
            var chatHtml = '<li class="chat-item active" data-chat-id="' + chatId + '">' +
              '<div class="chat-item-content">' +
              '<div class="chat-title">New Chat</div>' +
              '<div class="chat-preview"></div>' +
              '</div></li>';
            chatItems.insertAdjacentHTML('afterbegin', chatHtml);
            
            // Add click handler for the new chat
            var newChatItem = chatItems.querySelector('.chat-item[data-chat-id="' + chatId + '"]');
            if (newChatItem) {
              newChatItem.addEventListener('click', function () {
                loadChatMessages(chatId);
                chatItems.querySelectorAll('.chat-item').forEach(function (el) {
                  el.classList.remove('active');
                });
                newChatItem.classList.add('active');
              });
            }
          } else {
            // If chat exists, just make it active
            chatItems.querySelectorAll('.chat-item').forEach(function (el) {
              el.classList.remove('active');
            });
            existingChat.classList.add('active');
          }
        }
      },
      function onTask(task) {
        if (currentRequestCancelled) return;
        console.log('[Task]', task);
        
        // If this is a new task list, create it in thinking message
        if (task.action === 'list' && task.tasks) {
          console.log('[Task] Creating task list with', task.tasks.length, 'tasks');
          tasks = task.tasks;
          currentTaskIndex = -1;
          
          // Add task list to thinking message
          var thinkingEl = document.getElementById('thinking-msg');
          console.log('[Task] thinking-msg element:', thinkingEl);
          if (thinkingEl) {
            var messageBody = thinkingEl.querySelector('.message-bubble');
            console.log('[Task] message-bubble element:', messageBody);
            if (messageBody) {
              // Remove old task list if exists
              var oldList = messageBody.querySelector('.task-list');
              if (oldList) {
                console.log('[Task] Removing old task list');
                oldList.remove();
              }
              
              // Create new task list (all pending)
              var tasksHtml = '<ul class="task-list" data-task-list>';
              tasks.forEach(function (t, idx) {
                tasksHtml +=
                  '<li class="task-item" data-task-index="' + idx + '">' +
                  '<span class="material-symbols-outlined task-icon">radio_button_unchecked</span>' +
                  '<span class="task-label">' + escapeHtml(t.label) + '</span></li>';
              });
              tasksHtml += '</ul>';
              console.log('[Task] Inserting task list HTML:', tasksHtml.substring(0, 100) + '...');
              messageBody.insertAdjacentHTML('beforeend', tasksHtml);
              console.log('[Task] Task list inserted successfully');
            } else {
              console.warn('[Task] message-bubble not found inside thinking-msg!');
            }
          } else {
            console.warn('[Task] thinking-msg element not found!');
          }
        }
        // If task starts, update current step and mark as running
        else if (task.action === 'start' && task.index !== undefined) {
          currentTaskIndex = task.index;
          if (tasks[currentTaskIndex]) {
            updateThinkingStep(tasks[currentTaskIndex].label);
            
            // Update task item to running (change icon to pending)
            var taskItem = document.querySelector('.task-item[data-task-index="' + task.index + '"]');
            if (taskItem) {
              var icon = taskItem.querySelector('.task-icon');
              if (icon) icon.textContent = 'pending';
              taskItem.classList.add('running');
            }
          }
        }
        // If task completes, mark it as done
        else if (task.action === 'done' && task.index !== undefined) {
          var taskItem = document.querySelector('.task-item[data-task-index="' + task.index + '"]');
          if (taskItem) {
            var icon = taskItem.querySelector('.task-icon');
            if (icon) icon.textContent = 'check_circle';
            taskItem.classList.remove('running');
            taskItem.classList.add('done');
          }
        }
      },
      function onDone() {
        if (currentRequestCancelled) return;
        
        // Check if we have a streaming message or still have thinking
        var streamingMsg = document.getElementById(window.currentStreamingMsgId);
        var thinkingEl = document.getElementById('thinking-msg');
        
        // If streaming message exists, finalize it
        if (streamingMsg) {
          streamingMsg.classList.remove('streaming');
          
          // Move tasks from thinking to final message if needed
          if (thinkingEl && tasks.length > 0) {
            var thinkingTaskList = thinkingEl.querySelector('.task-list');
            if (thinkingTaskList) {
              var messageBody = streamingMsg.querySelector('.message-body');
              if (messageBody && !messageBody.querySelector('.task-list')) {
                // Clone and append task list
                var clonedList = thinkingTaskList.cloneNode(true);
                messageBody.appendChild(clonedList);
              }
            }
          }
        }
        
        // Remove thinking message
        if (thinkingEl) {
          thinkingEl.remove();
        }
        
        setLoading(false);
        userInput.focus();
      },
      function onError(err) {
        if (currentRequestCancelled) return;
        var msg = (err && err.message) ? err.message : 'Something went wrong. Try again or check Settings.';
        replaceThinkingWithReply(msg, null, null, true);
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

  /* ----- Load existing chats on page load ----- */
  function loadChats() {
    if (!restUrl || !restNonce) return;
    
    fetch(restUrl + '/chats', {
      method: 'GET',
      headers: { 'X-WP-Nonce': restNonce },
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (data && data.chats && data.chats.length > 0) {
          renderChatList(data.chats);
        }
      })
      .catch(function (err) {
        console.error('Failed to load chats:', err);
      });
  }

  function renderChatList(chats) {
    if (!chatItems || !chatListPlaceholder) return;
    
    chatListPlaceholder.style.display = 'none';
    chatItems.hidden = false;
    chatItems.innerHTML = '';
    
    chats.forEach(function (chat) {
      var chatHtml = '<li class="chat-item" data-chat-id="' + chat.id + '">' +
        '<div class="chat-item-content">' +
        '<div class="chat-title">' + escapeHtml(chat.title || 'New Chat') + '</div>' +
        '<div class="chat-preview"></div>' +
        '</div></li>';
      chatItems.insertAdjacentHTML('beforeend', chatHtml);
    });
    
    // Add click handlers
    chatItems.querySelectorAll('.chat-item').forEach(function (item) {
      item.addEventListener('click', function () {
        var chatId = parseInt(item.getAttribute('data-chat-id'), 10);
        loadChatMessages(chatId);
        
        // Update active state
        chatItems.querySelectorAll('.chat-item').forEach(function (el) {
          el.classList.remove('active');
        });
        item.classList.add('active');
      });
    });
  }

  function loadChatMessages(chatId) {
    if (!restUrl || !restNonce) return;
    
    currentChatId = chatId;
    
    // Clear current messages
    messagesEl.querySelectorAll('.message').forEach(function (el) { el.remove(); });
    messagesEl.classList.remove('has-messages');
    var welcome = document.getElementById('welcome');
    if (welcome) welcome.style.display = 'none';
    
    // Show loading state
    messagesEl.innerHTML = '<div class="loading-messages"><span class="material-symbols-outlined">progress_activity</span><span>Loading...</span></div>';
    
    fetch(restUrl + '/chat/' + chatId + '/messages', {
      method: 'GET',
      headers: { 'X-WP-Nonce': restNonce },
    })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(res); })
      .then(function (data) {
        // Remove loading
        var loading = messagesEl.querySelector('.loading-messages');
        if (loading) loading.remove();
        
        if (data && data.messages && data.messages.length > 0) {
          messagesEl.classList.add('has-messages');
          data.messages.forEach(function (msg) {
            renderMessage(msg);
          });
          scrollToBottom();
        }
      })
      .catch(function (err) {
        console.error('Failed to load chat messages:', err);
        var loading = messagesEl.querySelector('.loading-messages');
        if (loading) loading.remove();
      });
  }

  function renderMessage(msg) {
    var time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString('en-US', {
      hour: '2-digit',
      minute: '2-digit',
    }) : '';
    
    if (msg.role === 'user') {
      var dir = detectTextDirection(msg.content);
      var html = '<div class="message user" role="listitem" dir="' + dir + '">' +
        '<div class="message-avatar" aria-hidden="true">' +
        '<span class="material-symbols-outlined">person</span>' +
        '</div>' +
        '<div class="message-bubble">' +
        '<div class="message-text">' + escapeHtml(msg.content) + '</div>' +
        '<div class="message-time">' + time + '</div>' +
        '</div></div>';
      messagesEl.insertAdjacentHTML('beforeend', html);
    } else if (msg.role === 'assistant') {
      var dir = detectTextDirection(msg.content);
      var html = '<div class="message assistant" role="listitem" dir="' + dir + '">' +
        '<div class="message-avatar" aria-hidden="true">' +
        '<span class="material-symbols-outlined">auto_awesome</span>' +
        '</div>' +
        '<div class="message-bubble">' +
        '<div class="message-text">' + markdownToHtml(msg.content) + '</div>' +
        '<div class="message-time">' + time + '</div>' +
        '</div></div>';
      messagesEl.insertAdjacentHTML('beforeend', html);
    }
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

  // Load chats on page load
  loadChats();

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
