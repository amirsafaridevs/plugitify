/**
 * Plugitify Chat - UI interactions (in-memory state; chats and messages from DB).
 *
 * UX and behavior:
 * - Sidebar threads list + New chat
 * - Composer (Enter to send, Shift+Enter newline, autogrow)
 * - Message rendering with thinking + reasoning_content (under Thinking)
 * - Initial skeleton handled by dashboard.js (adds --loaded)
 * - Per-thread "updating" shimmer while new response is generated
 */
(function () {
  'use strict';

  var root = document.querySelector('.plugifity-dashboard.plugifity-chat-page');
  if (!root) return;

  var els = {
    threadList: root.querySelector('[data-pfy-thread-list]'),
    newChatBtn: root.querySelector('[data-pfy-new-chat]'),
    search: root.querySelector('[data-pfy-search]'),
    messages: root.querySelector('[data-pfy-messages]'),
    messagesList: root.querySelector('[data-pfy-messages-list]'),
    activeTitle: root.querySelector('[data-pfy-active-title]'),
    form: root.querySelector('[data-pfy-form]'),
    textarea: root.querySelector('[data-pfy-textarea]'),
    sendBtn: root.querySelector('[data-pfy-send]'),
    emptyState: root.querySelector('[data-pfy-empty]'),
    themeToggle: root.querySelector('[data-pfy-theme-toggle]'),
    themeLabel: root.querySelector('[data-pfy-theme-label]'),
  };

  if (!els.threadList || !els.newChatBtn || !els.messages || !els.form || !els.textarea) {
    return;
  }

  var THEME_STORAGE_KEY = 'plugitify_chat_theme';
  /** Id for "new chat" not yet in list – only added when user sends first message */
  var NEW_CHAT_PLACEHOLDER_ID = '__new__';
  var initialChatsRaw = els.threadList ? els.threadList.getAttribute('data-initial-chats') : null;
  var initialChats = null;
  try {
    if (initialChatsRaw) initialChats = JSON.parse(initialChatsRaw);
  } catch (e) {}
  var state = loadState(Array.isArray(initialChats) ? initialChats : null);

  function getTheme() {
    try {
      var t = localStorage.getItem(THEME_STORAGE_KEY);
      return t === 'dark' ? 'dark' : 'light';
    } catch (e) { return 'light'; }
  }
  function setTheme(theme) {
    try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {}
    root.classList.toggle('pfy-chat-dark', theme === 'dark');
    if (els.themeLabel) els.themeLabel.textContent = theme === 'dark' ? 'Light mode' : 'Dark mode';
  }
  setTheme(getTheme());

  function nowIso() {
    return new Date().toISOString();
  }

  function uid(prefix) {
    return (prefix || 'id') + '_' + Math.random().toString(16).slice(2) + '_' + Date.now().toString(16);
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  /** Allowed HTML tags and attributes for model-sent HTML (safe subset). dir/style for RTL/LTR and alignment. */
  var ALLOWED_HTML_TAGS = {
    a: ['href', 'target', 'rel', 'title', 'dir', 'style'],
    p: ['dir', 'style'], div: ['dir', 'style'], span: ['dir', 'style'], br: [],
    ul: ['dir', 'style'], ol: ['dir', 'style'], li: ['dir', 'style'],
    h1: ['dir', 'style'], h2: ['dir', 'style'], h3: ['dir', 'style'],
    h4: ['dir', 'style'], h5: ['dir', 'style'], h6: ['dir', 'style'],
    table: ['dir', 'style'], thead: ['dir', 'style'], tbody: ['dir', 'style'],
    tr: ['dir', 'style'], th: ['dir', 'style'], td: ['dir', 'style'], caption: ['dir', 'style'],
    form: ['action', 'method', 'data-pfy-send-message', 'data-pfy-button-text', 'dir', 'style'],
    input: ['name', 'type', 'placeholder', 'value', 'id', 'required', 'checked'],
    textarea: ['name', 'placeholder', 'id', 'rows', 'dir', 'style'],
    select: ['name', 'id', 'dir', 'style'],
    option: ['value', 'selected'],
    button: ['type', 'name'],
    label: ['for', 'dir', 'style'],
    fieldset: ['dir', 'style'], legend: ['dir', 'style'],
    strong: ['dir', 'style'], em: ['dir', 'style'], code: ['dir', 'style'], pre: ['dir', 'style'],
  };
  function escapeAttr(val) {
    return String(val)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
  /** Allowed style props for direction/alignment only. Values must be safe. */
  function sanitizeStyleValue(val) {
    if (val == null || val === '') return '';
    var s = String(val).trim();
    var out = [];
    var parts = s.split(/\s*;\s*/);
    for (var i = 0; i < parts.length; i++) {
      var part = parts[i];
      var colon = part.indexOf(':');
      if (colon === -1) continue;
      var prop = part.slice(0, colon).trim().toLowerCase();
      var value = part.slice(colon + 1).trim().toLowerCase();
      if (prop === 'direction' && /^(ltr|rtl|auto)$/.test(value)) out.push('direction:' + value);
      else if (prop === 'text-align' && /^(left|right|start|end|center|justify)$/.test(value)) out.push('text-align:' + value);
    }
    return out.length ? out.join('; ') : '';
  }
  /** dir attribute: only ltr, rtl, auto. */
  function sanitizeDirValue(val) {
    if (val == null || val === '') return '';
    var v = String(val).trim().toLowerCase();
    return /^(ltr|rtl|auto)$/.test(v) ? v : '';
  }
  /**
   * Sanitize HTML from model: keep only allowed tags/attrs; strip script, on* handlers, javascript: URLs.
   */
  function sanitizeHtml(html) {
    if (html == null || html === '') return '';
    var doc = document.createElement('div');
    doc.innerHTML = String(html);
    function walk(node) {
      if (node.nodeType === 3) return escapeHtml(node.textContent);
      if (node.nodeType !== 1) return '';
      var tag = node.tagName ? node.tagName.toLowerCase() : '';
      var allowed = ALLOWED_HTML_TAGS[tag];
      if (!allowed) return Array.prototype.map.call(node.childNodes, walk).join('');
      var out = '<' + tag;
      if (allowed.length) {
        for (var i = 0; i < allowed.length; i++) {
          var attr = allowed[i];
          var val = node.getAttribute(attr);
          if (val == null || val === '' || /javascript\s*:/i.test(val)) continue;
          if (attr === 'dir') { val = sanitizeDirValue(val); if (!val) continue; }
          else if (attr === 'style') { val = sanitizeStyleValue(val); if (!val) continue; }
          out += ' ' + attr + '="' + escapeAttr(val) + '"';
        }
      }
      if (tag === 'br') return '<br>';
      out += '>';
      for (var i = 0; i < node.childNodes.length; i++) out += walk(node.childNodes[i]);
      if (tag !== 'br') out += '</' + tag + '>';
      return out;
    }
    return Array.prototype.map.call(doc.childNodes, walk).join('');
  }

  /** Block-level HTML tags we allow to be rendered as HTML when they appear as complete blocks. */
  var ALLOWED_HTML_BLOCK_TAGS = ['form', 'table'];

  /**
   * Find the next allowed HTML block (e.g. <form>...</form>) in str starting from startIndex.
   * Returns { start, end, tag } or null. Search is case-insensitive.
   * If closing tag is missing (e.g. truncated response), treat from opening tag to end of string as block.
   */
  function findNextHtmlBlock(str, startIndex) {
    if (startIndex >= str.length) return null;
    var lower = str.toLowerCase();
    var nextForm = lower.indexOf('<form', startIndex);
    var nextTable = lower.indexOf('<table', startIndex);
    var next = -1;
    var tag = '';
    if (nextForm !== -1 && (nextTable === -1 || nextForm <= nextTable)) {
      next = nextForm;
      tag = 'form';
    } else if (nextTable !== -1) {
      next = nextTable;
      tag = 'table';
    }
    if (next === -1) return null;
    var openEnd = str.indexOf('>', next);
    if (openEnd === -1) return null;
    var closeTag = '</' + tag + '>';
    var closeIndex = lower.indexOf(closeTag, openEnd + 1);
    var end = closeIndex === -1 ? str.length : closeIndex + closeTag.length;
    return { start: next, end: end, tag: tag };
  }

  /**
   * Render message content: markdown segments are rendered as markdown; allowed HTML blocks
   * (<form>...</form>, <table>...</table>) are sanitized and rendered as HTML. This way
   * both **bold** and embedded forms/tables work in the same message.
   */
  function messageContentToHtml(text) {
    if (text == null || text === '') return '';
    var s = String(text);
    var segments = [];
    var pos = 0;
    while (pos < s.length) {
      var block = findNextHtmlBlock(s, pos);
      if (!block) {
        segments.push({ type: 'markdown', text: s.slice(pos) });
        break;
      }
      if (block.start > pos) {
        segments.push({ type: 'markdown', text: s.slice(pos, block.start) });
      }
      segments.push({ type: 'html', text: s.slice(block.start, block.end) });
      pos = block.end;
    }
    return segments.map(function (seg) {
      return seg.type === 'markdown' ? markdownToHtml(seg.text) : sanitizeHtml(seg.text);
    }).join('');
  }

  /**
   * Detect text direction from content: 'rtl' (Arabic, Hebrew, Persian, etc.) or 'ltr'.
   */
  function getTextDirection(text) {
    if (text == null || String(text).trim() === '') return 'ltr';
    var s = String(text);
    var rtlRe = /[\u0590-\u05FF\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/;
    var ltrRe = /[a-zA-Z\u00C0-\u024F\u1E00-\u1EFF]/;
    var rtlCount = 0;
    var ltrCount = 0;
    for (var i = 0; i < s.length && i < 500; i++) {
      var c = s[i];
      if (rtlRe.test(c)) rtlCount++;
      else if (ltrRe.test(c)) ltrCount++;
    }
    return rtlCount >= ltrCount ? 'rtl' : 'ltr';
  }

  /**
   * Convert markdown table blocks (| A | B | ... |) to <table> HTML.
   * Runs of lines that look like table rows (contain |) are wrapped in table/thead/tbody/tr/td.
   */
  function markdownTablesToHtml(block) {
    if (!block || typeof block !== 'string') return block;
    var lines = block.split('\n');
    var result = [];
    var i = 0;
    while (i < lines.length) {
      var line = lines[i];
      var isSeparator = /^\s*\|[\s\-:|]+\|\s*$/.test(line);
      var isTableRow = /^\s*\|.+\|\s*$/.test(line) && (line.match(/\|/g) || []).length >= 2;
      if (!isTableRow) {
        result.push(line);
        i++;
        continue;
      }
      var tableRows = [];
      if (isSeparator) {
        i++;
        continue;
      }
      while (i < lines.length) {
        var rowLine = lines[i];
        var sep = /^\s*\|[\s\-:|]+\|\s*$/.test(rowLine);
        var row = /^\s*\|.+\|\s*$/.test(rowLine) && (rowLine.match(/\|/g) || []).length >= 2;
        if (sep) { i++; continue; }
        if (!row) break;
        tableRows.push(rowLine);
        i++;
      }
      if (tableRows.length === 0) continue;
      var tableHtml = '<table class="pfy-md-table"><thead>';
      for (var r = 0; r < tableRows.length; r++) {
        var cells = tableRows[r].split('|').slice(1, -1).map(function (c) { return c.trim(); });
        var tag = r === 0 ? 'th' : 'td';
        if (r === 1) tableHtml += '</thead><tbody>';
        tableHtml += '<tr>';
        for (var c = 0; c < cells.length; c++) {
          tableHtml += '<' + tag + ' class="pfy-md-' + tag + '">' + cells[c] + '</' + tag + '>';
        }
        tableHtml += '</tr>';
      }
      tableHtml += '</tbody></table>';
      result.push(tableHtml);
    }
    return result.join('\n');
  }

  /**
   * Convert markdown to safe HTML for message display.
   * Escapes HTML first, then applies markdown (code, bold, italic, links, headers, lists, tables, line breaks).
   */
  function markdownToHtml(text) {
    if (text == null || text === '') return '';
    var s = String(text);
    s = s
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
    var out = s;
    var codeBlockPlaceholders = [];
    out = out.replace(/```[\s\S]*?```/g, function (match) {
      var idx = codeBlockPlaceholders.length;
      var inner = match.slice(3, -3).trim();
      codeBlockPlaceholders.push('<pre class="pfy-md-pre"><code>' + inner + '</code></pre>');
      return '\x00P' + idx + 'P\x00';
    });
    out = out.replace(/`([^`\n]+)`/g, '<code class="pfy-md-code">$1</code>');
    out = out.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    out = out.replace(/__(.+?)__/g, '<strong>$1</strong>');
    out = out.replace(/\*(.+?)\*/g, '<em>$1</em>');
    out = out.replace(/_(.+?)_/g, '<em>$1</em>');
    out = out.replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, function (_, label, url) {
      return '<a href="' + url + '" target="_blank" rel="noopener noreferrer" class="pfy-md-link">' + label + '</a>';
    });
    out = out.replace(/^### (.+)$/gm, '<h3 class="pfy-md-h3">$1</h3>');
    out = out.replace(/^## (.+)$/gm, '<h2 class="pfy-md-h2">$1</h2>');
    out = out.replace(/^# (.+)$/gm, '<h1 class="pfy-md-h1">$1</h1>');
    out = out.replace(/^\s*[-*] (.+)$/gm, '<li class="pfy-md-li">$1</li>');
    out = markdownTablesToHtml(out);
    out = out.replace(/\n/g, '<br>\n');
    for (var i = 0; i < codeBlockPlaceholders.length; i++) {
      out = out.replace('\x00P' + i + 'P\x00', codeBlockPlaceholders[i]);
    }
    return out;
  }

  function formatRelative(iso) {
    if (!iso) return '';
    var t = new Date(iso).getTime();
    if (!isFinite(t)) return '';
    var delta = Math.floor((Date.now() - t) / 1000);
    if (delta < 5) return 'Just now';
    if (delta < 60) return delta + 's';
    var m = Math.floor(delta / 60);
    if (m < 60) return m + 'm';
    var h = Math.floor(m / 60);
    if (h < 24) return h + 'h';
    var d = Math.floor(h / 24);
    return d + 'd';
  }

  function truncate(s, max) {
    var str = String(s || '').trim();
    if (!str) return '';
    if (str.length <= max) return str;
    return str.slice(0, max - 1) + '…';
  }

  /**
   * Create a new chat thread (not yet in DB). Used only when creating a real thread after first message.
   */
  function makeNewChatThread(id) {
    var threadId = id != null ? String(id) : uid('thread');
    return {
      id: threadId,
      backendChatId: threadId,
      title: 'New chat',
      updatedAt: nowIso(),
      lastPreview: '',
    };
  }

  /**
   * Virtual thread for "new chat" mode – not in list until user sends first message.
   */
  function getNewChatPlaceholderThread() {
    return {
      id: NEW_CHAT_PLACEHOLDER_ID,
      backendChatId: null,
      title: 'New chat',
      updatedAt: nowIso(),
      lastPreview: '',
    };
  }

  function loadState(initialChatsFromServer) {
    var messagesByThread = {};

    if (Array.isArray(initialChatsFromServer) && initialChatsFromServer.length > 0) {
      var serverThreads = initialChatsFromServer.map(function (c) {
        return {
          id: String(c.id),
          title: c.title || 'Chat',
          updatedAt: c.updated_at || '',
          lastPreview: '',
        };
      });
      return normalizeState({
        activeThreadId: serverThreads[0].id,
        threads: serverThreads,
        messagesByThread: messagesByThread,
      });
    }

    // No chats from server: show empty state (new chat not in list). All data from DB only.
    messagesByThread[NEW_CHAT_PLACEHOLDER_ID] = [];
    return normalizeState({
      activeThreadId: NEW_CHAT_PLACEHOLDER_ID,
      threads: [],
      messagesByThread: messagesByThread,
    });
  }

  function normalizeState(s) {
    var st = s || {};
    st.threads = Array.isArray(st.threads) ? st.threads : [];
    st.messagesByThread = st.messagesByThread && typeof st.messagesByThread === 'object' ? st.messagesByThread : {};
    st.activeThreadId = st.activeThreadId || (st.threads[0] ? st.threads[0].id : NEW_CHAT_PLACEHOLDER_ID);
    return st;
  }

  function saveState() {
    // Chat/message state is not persisted to localStorage; all data comes from DB.
  }

  function getActiveThread() {
    if (state.activeThreadId === NEW_CHAT_PLACEHOLDER_ID) {
      return getNewChatPlaceholderThread();
    }
    return state.threads.find(function (t) { return t.id === state.activeThreadId; }) || null;
  }

  function getActiveMessages() {
    var tid = state.activeThreadId;
    if (!tid) return [];
    var msgs = state.messagesByThread[tid];
    return Array.isArray(msgs) ? msgs : [];
  }

  function removeChat(threadId) {
    var thread = state.threads.find(function (t) { return t.id === threadId; });
    if (!thread) return;
    var dbId = getDbChatId(thread);
    var wasActive = state.activeThreadId === threadId;
    var doRemove = function () {
      state.threads = state.threads.filter(function (t) { return t.id !== threadId; });
      delete state.messagesByThread[threadId];
      if (wasActive) {
        state.activeThreadId = state.threads.length ? state.threads[0].id : NEW_CHAT_PLACEHOLDER_ID;
        if (state.activeThreadId === NEW_CHAT_PLACEHOLDER_ID) {
          state.messagesByThread[NEW_CHAT_PLACEHOLDER_ID] = [];
        }
      }
      saveState();
      render();
      focusComposerSoon();
    };
    if (dbId) {
      deleteChatInDb(dbId).then(function () { doRemove(); }).catch(function () { doRemove(); });
    } else {
      doRemove();
    }
  }

  function setActiveThread(threadId) {
    state.activeThreadId = threadId;
    var thread = state.threads.find(function (t) { return t.id === threadId; });
    var dbId = thread ? getDbChatId(thread) : null;
    if (dbId) {
      state.messagesByThread[threadId] = state.messagesByThread[threadId] || [];
      saveState();
      render();
      focusComposerSoon();
      fetchChatMessages(dbId).then(function (messages) {
        if (state.activeThreadId !== threadId) return;
        state.messagesByThread[threadId] = messages;
        saveState();
        render();
        window.setTimeout(scrollToBottom, 50);
      });
      return;
    }
    saveState();
    render();
    focusComposerSoon();
  }

  function focusComposerSoon() {
    window.setTimeout(function () {
      if (els.textarea) els.textarea.focus();
    }, 30);
  }

  /**
   * Switch to "new chat" mode without adding anything to the list.
   * Chat will appear in list only when user sends first message.
   */
  function switchToNewChat() {
    state.activeThreadId = NEW_CHAT_PLACEHOLDER_ID;
    state.messagesByThread[NEW_CHAT_PLACEHOLDER_ID] = [];
    saveState();
    render();
    focusComposerSoon();
  }

  var TEXTAREA_LINE_HEIGHT = 20;
  var TEXTAREA_MAX_LINES = 3;
  var TEXTAREA_MAX_HEIGHT = 44 + (TEXTAREA_MAX_LINES - 1) * TEXTAREA_LINE_HEIGHT;

  function autogrowTextarea() {
    if (!els.textarea) return;
    els.textarea.style.height = '0px';
    var h = clamp(els.textarea.scrollHeight, 44, TEXTAREA_MAX_HEIGHT);
    els.textarea.style.height = h + 'px';
    if (els.textarea.scrollHeight > els.textarea.clientHeight) {
      els.textarea.scrollTop = els.textarea.scrollHeight - els.textarea.clientHeight;
    }
  }

  function setSendEnabled(enabled) {
    if (!els.sendBtn) return;
    els.sendBtn.disabled = !enabled;
  }

  function scrollToBottom() {
    var el = els.messagesList || els.messages;
    if (!el) return;
    el.scrollTop = el.scrollHeight;
  }

  function renderThreads() {
    var q = (els.search && els.search.value ? els.search.value.trim().toLowerCase() : '');
    var activeId = state.activeThreadId;

    var html = '';
    var threads = state.threads.slice();

    if (q) {
      threads = threads.filter(function (t) {
        var hay = (t.title || '') + ' ' + (t.lastPreview || '');
        return hay.toLowerCase().indexOf(q) !== -1;
      });
    }

    if (!threads.length) {
      html =
        '<div class="pfy-chat-thread-empty" role="status">' +
        '<div class="pfy-chat-thread-empty-icon" aria-hidden="true">' +
        '<svg class="plugifity-icon"><use href="#pfy-icon-chat"/></svg>' +
        '</div>' +
        '<p class="pfy-chat-thread-empty-text">' + escapeHtml('No chats yet') + '</p>' +
        '<p class="pfy-chat-thread-empty-hint">' + escapeHtml('Start a new chat to begin.') + '</p>' +
        '</div>';
      els.threadList.innerHTML = html;
      return;
    }

    threads.forEach(function (t) {
      var selected = t.id === activeId;
      html +=
        '<div class="pfy-chat-thread" role="option" tabindex="0" ' +
        'data-pfy-thread="' + escapeHtml(t.id) + '" aria-selected="' + (selected ? 'true' : 'false') + '">' +
          '<div class="pfy-chat-thread-avatar" aria-hidden="true">' +
            '<svg class="plugifity-icon"><use href="#pfy-icon-chat"/></svg>' +
          '</div>' +
          '<div class="pfy-chat-thread-body">' +
            '<p class="pfy-chat-thread-title">' + escapeHtml(t.title || 'Chat') + '</p>' +
            '<div class="pfy-chat-thread-meta">' +
              '<span>' + escapeHtml(formatRelative(t.updatedAt)) + '</span>' +
            '</div>' +
            '<p class="pfy-chat-thread-preview">' + escapeHtml(t.lastPreview || ' ') + '</p>' +
          '</div>' +
          '<button type="button" class="pfy-chat-thread-delete" data-pfy-delete-chat="' + escapeHtml(t.id) + '" aria-label="' + escapeHtml('Delete chat') + '" title="' + escapeHtml('Delete chat') + '">' +
            '<svg class="plugifity-icon"><use href="#pfy-icon-trash"/></svg>' +
          '</button>' +
        '</div>';
    });

    els.threadList.innerHTML = html;
  }

  function renderMessages() {
    var thread = getActiveThread();
    var msgs = getActiveMessages();

    if (els.activeTitle) els.activeTitle.textContent = thread ? (thread.title || 'Chat') : 'Chat';

    if (!msgs.length) {
      if (els.emptyState) els.emptyState.style.display = 'flex';
      if (els.messagesList) els.messagesList.innerHTML = '';
      return;
    }

    if (els.emptyState) els.emptyState.style.display = 'none';

    var html = '';

    msgs.forEach(function (m) {
      var role = m.role === 'user' ? 'user' : 'assistant';
      var roleLabel = role === 'user' ? 'You' : 'Assistant';
      var time = m.createdAt ? formatRelative(m.createdAt) : '';
      var isStreaming = !!m._isStreaming;

      var thinking = (m.thinking || '').trim();
      var reasoning = (m.reasoning_content || '').trim();
      var hasThoughts = role === 'assistant' && (thinking || reasoning || isStreaming);
      var dir = getTextDirection(m.content || '');

      html +=
        '<div class="pfy-msg pfy-msg--' + role + '" data-pfy-msg-id="' + escapeHtml(m.id) + '">' +
          '<div class="pfy-msg-avatar" aria-hidden="true">' +
            (role === 'user'
              ? '<svg class="plugifity-icon"><use href="#pfy-icon-user"/></svg>'
              : '<svg class="plugifity-icon"><use href="#pfy-icon-spark"/></svg>') +
          '</div>' +
          '<div class="pfy-msg-card" dir="' + dir + '">' +
            '<div class="pfy-msg-header">' +
              '<div class="pfy-msg-role">' + escapeHtml(roleLabel) + '</div>' +
              '<div class="pfy-msg-time">' + escapeHtml(time) + '</div>' +
            '</div>' +
            '<div class="pfy-msg-content">' + (role === 'assistant' ? messageContentToHtml(m.content || '') : markdownToHtml(m.content || '')) + '</div>';

      if (hasThoughts) {
        var thinkingPreview = thinking || reasoning || '';
        html +=
          '<div class="pfy-thinking">' +
            '<div class="pfy-thinking-head">' +
              '<span class="pfy-thinking-label">Thinking</span>' +
              '<span class="pfy-thinking-snippet" aria-hidden="true">' +
                '<span></span><span></span><span></span><span></span>' +
              '</span>' +
            '</div>' +
            '<div class="pfy-thinking-text" aria-live="polite">' +
              escapeHtml(thinkingPreview || (isStreaming ? '…' : '')) +
            '</div>' +
          '</div>';
      }

      html += '</div></div>';
    });

    if (els.messagesList) {
      els.messagesList.innerHTML = html;
      wrapTablesInScrollContainer(els.messagesList);
      processFormInMessages(els.messagesList);
    }
  }

  /**
   * Wrap each table inside a scroll container so tables are responsive (horizontal scroll on small screens).
   */
  function wrapTablesInScrollContainer(parent) {
    if (!parent) return;
    var tables = parent.querySelectorAll('table');
    for (var i = 0; i < tables.length; i++) {
      var table = tables[i];
      var p = table.parentNode;
      if (p && p.classList && p.classList.contains('pfy-msg-content-table-scroll')) continue;
      var wrapper = document.createElement('div');
      wrapper.className = 'pfy-msg-content-table-scroll';
      p.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  }

  /**
   * Build the user message from a form: use data-pfy-send-message template with {name} placeholders, or name: value lines.
   */
  function getFieldValue(el) {
    if (el.nodeName === 'SELECT') {
      var opt = el.options[el.selectedIndex];
      return opt ? (opt.value || opt.textContent || '').trim() : '';
    }
    if (el.type === 'checkbox' || el.type === 'radio') return el.checked ? (el.value || 'yes') : '';
    return (el.value || '').trim();
  }

  function buildMessageFromForm(form) {
    var template = form.getAttribute('data-pfy-send-message');
    var fields = form.querySelectorAll('input, textarea, select');
    if (template) {
      for (var i = 0; i < fields.length; i++) {
        var el = fields[i];
        if (!el.name || (el.type === 'submit' || el.type === 'button')) continue;
        var val = getFieldValue(el);
        template = template.split('{' + el.name + '}').join(val);
      }
      return template.trim() || '(form submitted)';
    }
    var parts = [];
    for (var i = 0; i < fields.length; i++) {
      var el = fields[i];
      var name = el.name || el.placeholder || ('field' + (i + 1));
      if (el.type === 'checkbox' || el.type === 'radio') {
        if (el.checked) parts.push(name + ': ' + (el.value || 'yes'));
      } else if (el.type !== 'submit' && el.type !== 'button') {
        var val = el.nodeName === 'SELECT' ? getFieldValue(el) : (el.value || '').trim();
        if (val) parts.push(name + ': ' + val);
      }
    }
    return parts.length ? parts.join('\n') : '(form submitted)';
  }

  /**
   * Process forms in message content: remove model-supplied submit/button, add single "send to model" button.
   * Form can set data-pfy-send-message="Message with {name} placeholders" and data-pfy-button-text="Send".
   */
  function processFormInMessages(parent) {
    if (!parent) return;
    var forms = parent.querySelectorAll('form');
    for (var f = 0; f < forms.length; f++) {
      var form = forms[f];
      if (!root.contains(form)) continue;
      var toRemove = form.querySelectorAll('button, input[type="submit"], input[type="button"]');
      for (var r = 0; r < toRemove.length; r++) toRemove[r].parentNode.removeChild(toRemove[r]);
      var btnText = form.getAttribute('data-pfy-button-text') || 'Send';
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pfy-form-send-btn';
      btn.textContent = btnText;
      form.appendChild(btn);
      btn.addEventListener('click', function (formEl) {
        return function () {
          var msg = buildMessageFromForm(formEl);
          handleSend(msg);
        };
      }(form));
    }
  }

  function render() {
    // Ensure we have an active thread if any exist.
    if (!state.activeThreadId && state.threads[0]) state.activeThreadId = state.threads[0].id;
    renderThreads();
    renderMessages();
    autogrowTextarea();
    window.setTimeout(scrollToBottom, 10);
  }

  function markThreadUpdating(threadId, on) {
    var node = els.threadList.querySelector('[data-pfy-thread="' + threadId + '"]');
    if (!node) return;
    if (on) node.classList.add('pfy-is-updating');
    else node.classList.remove('pfy-is-updating');
  }

  function updateThreadPreview(threadId, text) {
    var t = state.threads.find(function (x) { return x.id === threadId; });
    if (!t) return;
    t.updatedAt = nowIso();
    t.lastPreview = truncate(text, 90);
  }

  // Demo "LLM" response generator (kept subtle; user can replace with real API)
  function demoAssistantReply(userText) {
    var t = (userText || '').trim();
    if (!t) t = '…';
    return {
      content:
        "I’m ready. If you connect an API endpoint, I’ll stream real responses.\n\nFor now, here’s an example reply based on your last message:\n\n" +
        "- You said: " + t,
      thinking: "Interpreting the user message… building a response structure… preparing output…",
      reasoning_content: "This is placeholder reasoning_content for UI preview. Replace with your LLM response fields.",
    };
  }

  var api = typeof plugitifyChat !== 'undefined' ? plugitifyChat : {
    baseUrl: '', siteUrl: '', hasLicense: false, licenseKey: '', licenseMenuUrl: '',
    toolsApiToken: '', restUrl: '', nonce: '',
  };

  function showLicensePopup() {
    window.alert('Please enter your license key from the License menu.');
    if (api.licenseMenuUrl) window.location.href = api.licenseMenuUrl;
  }

  function getChatToken() {
    var tokenUrl = (api.baseUrl || '').replace(/\/$/, '') + '/messages/token';
    return window.fetch(tokenUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        site_token: api.licenseKey || '',
        site_url: api.siteUrl || window.location.origin.replace(/\/$/, ''),
      }),
    }).then(function (res) {
      return res.json().then(function (data) {
        if (!res.ok) {
          var msg = (data && data.message) ? data.message : 'Please enter your license key from the License menu.';
          return Promise.reject(new Error(msg));
        }
        return data.access_token;
      });
    });
  }

  function isThreadDbCreated(thread) {
    if (!thread) return false;
    var id = thread.id;
    return typeof id === 'number' && id > 0 || (typeof id === 'string' && /^\d+$/.test(id));
  }

  function getDbChatId(thread) {
    if (!thread) return null;
    var id = thread.id;
    if (typeof id === 'number' && id > 0) return id;
    if (typeof id === 'string' && /^\d+$/.test(id)) return parseInt(id, 10);
    return null;
  }

  function createChatViaRest(title, firstMessage) {
    var url = (api.restUrl || '').replace(/\/$/, '') + '/chat';
    return window.fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': api.nonce || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ title: title || 'New chat', first_message: firstMessage || '' }),
    }).then(function (res) { return res.json(); });
  }

  function saveMessageToDb(chatId, role, content) {
    if (!chatId || !api.restUrl) return Promise.resolve();
    var url = (api.restUrl || '').replace(/\/$/, '') + '/chat/' + chatId + '/messages';
    return window.fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': api.nonce || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ role: role, content: content || '' }),
    }).then(function () {});
  }

  function updateChatTitleInDb(chatId, title) {
    if (!chatId || !api.restUrl) return Promise.resolve();
    var url = (api.restUrl || '').replace(/\/$/, '') + '/chat/' + chatId;
    return window.fetch(url, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': api.nonce || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ title: title || '' }),
    }).then(function (res) { return res.json(); });
  }

  function deleteChatInDb(chatId) {
    if (!chatId || !api.restUrl) return Promise.resolve();
    var url = (api.restUrl || '').replace(/\/$/, '') + '/chat/' + chatId + '/delete';
    return window.fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': api.nonce || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({}),
    }).then(function (res) { return res.json(); });
  }

  function fetchChatMessages(chatId) {
    if (!chatId || !api.restUrl) return Promise.resolve([]);
    var url = (api.restUrl || '').replace(/\/$/, '') + '/chat/' + chatId + '/messages';
    return window.fetch(url, {
      method: 'GET',
      headers: { 'X-WP-Nonce': api.nonce || '' },
      credentials: 'same-origin',
    }).then(function (res) { return res.json(); }).then(function (data) {
      var list = (data && data.messages) ? data.messages : [];
      return list.map(function (m) {
        return {
          id: String(m.id),
          role: m.role === 'user' ? 'user' : 'assistant',
          content: m.content || '',
          createdAt: m.created_at || '',
        };
      });
    }).catch(function () { return []; });
  }

  function buildMessageHistory(msgs, excludeStreaming) {
    var out = [];
    for (var i = 0; i < msgs.length; i++) {
      if (excludeStreaming && msgs[i]._isStreaming) break;
      out.push({
        role: msgs[i].role === 'user' ? 'user' : 'assistant',
        content: (msgs[i].content || '').trim(),
      });
    }
    return out;
  }

  function updateStreamingMessageInDom(msgId, content, thinkingText) {
    var wrap = root.querySelector('[data-pfy-msg-id="' + msgId + '"]');
    if (!wrap) return;
    var contentEl = wrap.querySelector('.pfy-msg-content');
    var thinkingEl = wrap.querySelector('.pfy-thinking-text');
    var cardEl = wrap.querySelector('.pfy-msg-card');
    if (contentEl) {
      contentEl.innerHTML = messageContentToHtml(content || '');
      wrapTablesInScrollContainer(contentEl);
      processFormInMessages(contentEl);
    }
    if (cardEl) cardEl.setAttribute('dir', getTextDirection(content || ''));
    if (thinkingEl) {
      thinkingEl.textContent = thinkingText || '…';
      thinkingEl.scrollTop = thinkingEl.scrollHeight;
    }
  }

  function pushMessage(threadId, msg) {
    var arr = state.messagesByThread[threadId];
    if (!Array.isArray(arr)) arr = state.messagesByThread[threadId] = [];
    arr.push(msg);
  }

  function handleSend(text) {
    if (!api.hasLicense) {
      showLicensePopup();
      return;
    }

    var content = String(text || '').trim();
    if (!content) return;

    var thread = getActiveThread();
    var isFirstMessageInThread = !thread || (state.messagesByThread[thread.id] && state.messagesByThread[thread.id].length === 0);
    var isPlaceholder = thread && thread.id === NEW_CHAT_PLACEHOLDER_ID;

    if (!thread) return;
    if (!isPlaceholder && isFirstMessageInThread) {
      thread.title = truncate(content.split('\n')[0] || content, 50);
    }
    var threadId = thread.id;

    pushMessage(threadId, {
      id: uid('msg'),
      role: 'user',
      createdAt: nowIso(),
      content: content,
    });

    var assistantId = uid('msg');
    pushMessage(threadId, {
      id: assistantId,
      role: 'assistant',
      createdAt: nowIso(),
      content: '',
      thinking: '',
      reasoning_content: '',
      _isStreaming: true,
    });

    updateThreadPreview(threadId, content);
    saveState();
    markThreadUpdating(threadId, true);
    render();

    var streamUrl = api.baseUrl ? (api.baseUrl.replace(/\/$/, '') + '/messages/stream') : '';
    if (!streamUrl) {
      var target = state.messagesByThread[threadId].find(function (m) { return m.id === assistantId; });
      if (target) {
        target.content = 'Backend URL is not configured.';
        target._isStreaming = false;
      }
      markThreadUpdating(threadId, false);
      saveState();
      render();
      return;
    }

    var dbChatId = getDbChatId(thread);

    var ensureChatThenStream = function (accessToken) {
      if (dbChatId) {
        return saveMessageToDb(dbChatId, 'user', content).then(function () { return accessToken; });
      }
      return createChatViaRest(thread.title, content).then(function (data) {
        if (data && data.id) {
          var oldId = thread.id;
          var newThread = makeNewChatThread(data.id);
          newThread.title = (data.title || truncate(content.split('\n')[0] || content, 50));
          newThread.updatedAt = nowIso();
          newThread.lastPreview = truncate(content, 90);
          if (state.messagesByThread[oldId]) {
            state.messagesByThread[data.id] = state.messagesByThread[oldId];
            delete state.messagesByThread[oldId];
          } else {
            state.messagesByThread[data.id] = [];
          }
          state.threads.unshift(newThread);
          state.activeThreadId = newThread.id;
          threadId = newThread.id;
          thread = newThread;
          dbChatId = data.id;
          saveState();
          render();
        }
        return accessToken;
      });
    };

    getChatToken()
      .then(ensureChatThenStream)
      .then(function (accessToken) {
        var msgs = state.messagesByThread[threadId];
        if (!msgs) msgs = state.messagesByThread[thread.id];
        var messageHistory = buildMessageHistory(msgs || [], true);
        var streamChatId = (dbChatId != null) ? String(dbChatId) : (thread.backendChatId || String(thread.id));
        var taskHistoryPromise = /^\d+$/.test(streamChatId) && api.restUrl
          ? window.fetch(api.restUrl + '/chat/' + streamChatId + '/task-history', {
              method: 'GET',
              credentials: 'same-origin',
              headers: { 'X-WP-Nonce': api.nonce || '' },
            }).then(function (r) { return r.ok ? r.json() : {}; })
              .then(function (data) { return Array.isArray(data.task_history) ? data.task_history : []; })
              .catch(function () { return []; })
          : Promise.resolve([]);
        return taskHistoryPromise.then(function (taskHistory) {
          return window.fetch(streamUrl, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + accessToken,
            },
            body: JSON.stringify({
              site_url: api.siteUrl || window.location.origin,
              chat_id: streamChatId,
              task_history: taskHistory,
              message_history: messageHistory,
              tools_api_token: api.toolsApiToken || undefined,
            }),
          });
        });
      }).then(function (response) {
      if (!response.ok) {
        var target = state.messagesByThread[threadId].find(function (m) { return m.id === assistantId; });
        if (target) {
          target.content = 'Request failed: ' + response.status;
          target._isStreaming = false;
        }
        markThreadUpdating(threadId, false);
        saveState();
        render();
        return;
      }
      return response.body.getReader();
    }).then(function (reader) {
      if (!reader) return;
      var decoder = new TextDecoder();
      var buffer = '';
      var contentSoFar = '';
      var thinkingSoFar = '';

      function processLine(line) {
        var s = (line || '').trim();
        if (s.indexOf('data:') !== 0) return;
        var jsonStr = s.slice(5).trim();
        if (jsonStr === '') return;
        try {
          var obj = JSON.parse(jsonStr);
          var type = obj.type || '';
          var payload = obj.content || '';
          var msgs = state.messagesByThread[threadId] || state.messagesByThread[thread.id];
          var target = msgs ? msgs.find(function (m) { return m.id === assistantId; }) : null;
          if (type === 'chat_title' && payload) {
            var chatIdToUpdate = dbChatId || getDbChatId(thread);
            if (chatIdToUpdate) updateChatTitleInDb(chatIdToUpdate, payload);
            var t = state.threads.find(function (x) { return x.id === threadId || x.id === thread.id; });
            if (t) { t.title = payload; updateThreadPreview(t.id, payload); saveState(); }
            return;
          }
          if (type === 'message' && payload) {
            if (dbChatId) saveMessageToDb(dbChatId, 'assistant', payload);
            contentSoFar = payload;
            if (target) { target.content = payload; updateStreamingMessageInDom(assistantId, contentSoFar, thinkingSoFar || (target.reasoning_content || '')); }
            return;
          }
          if (!target) return;
          if (type === 'thinking') {
            thinkingSoFar = payload;
            target.thinking = thinkingSoFar;
            updateStreamingMessageInDom(assistantId, contentSoFar, payload);
          } else if (type === 'reasoning_chunk') {
            target.reasoning_content = (target.reasoning_content || '') + payload;
            updateStreamingMessageInDom(assistantId, contentSoFar, target.reasoning_content);
          } else if (type === 'chunk') {
            contentSoFar += payload;
            target.content = contentSoFar;
            updateStreamingMessageInDom(assistantId, contentSoFar, thinkingSoFar || target.reasoning_content || '');
          } else if (type === 'error') {
            target.content = (target.content || '') + (payload ? '\nError: ' + payload : '');
            target._isStreaming = false;
            updateStreamingMessageInDom(assistantId, target.content, thinkingSoFar || target.reasoning_content || '');
          } else if (type === 'done') {
            target._isStreaming = false;
            target.thinking = '';
            target.reasoning_content = '';
            updateThreadPreview(threadId, contentSoFar);
            if (thread.id !== threadId) updateThreadPreview(thread.id, contentSoFar);
            saveState();
            markThreadUpdating(threadId, false);
            if (thread.id !== threadId) markThreadUpdating(thread.id, false);
            render();
          }
        } catch (e) {}
      }

      function read() {
        reader.read().then(function (result) {
          if (result.done) {
            var target = state.messagesByThread[threadId].find(function (m) { return m.id === assistantId; });
            if (target && target._isStreaming) {
              target._isStreaming = false;
              updateThreadPreview(threadId, target.content || '');
            }
            markThreadUpdating(threadId, false);
            saveState();
            render();
            return;
          }
          buffer += decoder.decode(result.value, { stream: true });
          var idx = buffer.indexOf('\n');
          while (idx !== -1) {
            processLine(buffer.slice(0, idx));
            buffer = buffer.slice(idx + 1);
            idx = buffer.indexOf('\n');
          }
          read();
        });
      }
      read();
    }).catch(function (err) {
      var target = state.messagesByThread[threadId].find(function (m) { return m.id === assistantId; });
      if (target) {
        target.content = (target.content || '') + (err && err.message ? err.message : 'Request failed.');
        target._isStreaming = false;
      }
      markThreadUpdating(threadId, false);
      saveState();
      render();
      if (err && err.message && err.message.indexOf('license') !== -1) showLicensePopup();
    });
  }

  // Events
  els.newChatBtn.addEventListener('click', function () {
    switchToNewChat();
  });

  if (els.search) {
    els.search.addEventListener('input', function () {
      renderThreads();
    });
  }

  els.threadList.addEventListener('click', function (e) {
    var el = e.target;
    while (el && el !== els.threadList) {
      var deleteChatId = el.getAttribute && el.getAttribute('data-pfy-delete-chat');
      if (deleteChatId) {
        e.preventDefault();
        e.stopPropagation();
        removeChat(deleteChatId);
        return;
      }
      if (el.getAttribute && el.getAttribute('data-pfy-thread')) {
        setActiveThread(el.getAttribute('data-pfy-thread'));
        return;
      }
      el = el.parentNode;
    }
  });

  els.threadList.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    var el = e.target;
    if (el && el.getAttribute && el.getAttribute('data-pfy-thread')) {
      setActiveThread(el.getAttribute('data-pfy-thread'));
    }
  });

  els.textarea.addEventListener('input', function () {
    autogrowTextarea();
    setSendEnabled(String(els.textarea.value || '').trim().length > 0);
  });

  els.textarea.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      els.form.requestSubmit();
    }
  });

  els.form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = els.textarea.value;
    els.textarea.value = '';
    autogrowTextarea();
    setSendEnabled(false);
    handleSend(text);
  });

  /* Forms inside assistant messages: never navigate; always send as user message to the model */
  if (els.messages) {
    els.messages.addEventListener('submit', function (e) {
      var form = e.target && e.target.nodeName === 'FORM' ? e.target : null;
      if (!form || !root.contains(form)) return;
      e.preventDefault();
      var msg = buildMessageFromForm(form);
      handleSend(msg);
    });
  }

  if (els.themeToggle) {
    els.themeToggle.addEventListener('click', function () {
      var next = root.classList.contains('pfy-chat-dark') ? 'light' : 'dark';
      setTheme(next);
    });
  }

  // Initial: load messages for first chat when we have server threads and one is selected
  if (state.threads.length && state.activeThreadId !== NEW_CHAT_PLACEHOLDER_ID) {
    var aid = state.activeThreadId;
    fetchChatMessages(aid).then(function (messages) {
      if (state.activeThreadId === aid) {
        state.messagesByThread[aid] = messages;
        saveState();
        render();
        window.setTimeout(scrollToBottom, 50);
      }
    });
  }
  setSendEnabled(String(els.textarea.value || '').trim().length > 0);
  render();
})();

