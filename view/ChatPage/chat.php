<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="plugitify-chat-app app">
  <aside class="sidebar">
    <div class="sidebar-header">
      <span class="material-symbols-outlined sidebar-logo">smart_toy</span>
      <span class="sidebar-brand">Plugitify</span>
    </div>
    <button type="button" class="btn-new-chat" id="btnNewChat" aria-label="<?php esc_attr_e( 'New chat', 'plugifity' ); ?>">
      <span class="material-symbols-outlined">add</span>
      <?php esc_html_e( 'New chat', 'plugifity' ); ?>
    </button>
    <nav class="chat-list" id="chatList" aria-label="<?php esc_attr_e( 'Chat history', 'plugifity' ); ?>">
      <div class="chat-list-placeholder" id="chatListPlaceholder">
        <span class="material-symbols-outlined">forum</span>
        <p><?php esc_html_e( 'No chats yet', 'plugifity' ); ?></p>
        <span><?php esc_html_e( 'Start a new chat above', 'plugifity' ); ?></span>
      </div>
      <ul class="chat-items" id="chatItems" hidden></ul>
    </nav>
    <div class="sidebar-footer">
      <button type="button" class="sidebar-btn" id="btnSettings" aria-label="<?php esc_attr_e( 'Settings', 'plugifity' ); ?>">
        <span class="material-symbols-outlined">settings</span>
        <?php esc_html_e( 'Settings', 'plugifity' ); ?>
      </button>
    </div>
  </aside>

  <div class="main">
    <button type="button" class="hamburger-btn" id="hamburgerBtn" aria-label="<?php esc_attr_e( 'Toggle menu', 'plugifity' ); ?>">
      <span class="material-symbols-outlined">menu</span>
    </button>
    <div class="chat-panel">
      <div class="messages" id="messages" role="log" aria-live="polite">
        <div class="welcome" id="welcome">
          <span class="material-symbols-outlined welcome-icon">psychology</span>
          <p><?php esc_html_e( 'Hi. Ask me anything.', 'plugifity' ); ?></p>
        </div>
      </div>

      <form class="input-area" id="chatForm" novalidate>
        <div class="input-wrapper">
          <label for="userInput" class="visually-hidden"><?php esc_attr_e( 'Your message', 'plugifity' ); ?></label>
          <input
            type="text"
            id="userInput"
            class="input-field"
            placeholder="<?php esc_attr_e( 'Type your message...', 'plugifity' ); ?>"
            autocomplete="off"
            maxlength="2000"
          />
          <button type="submit" class="btn-send" id="btnSend" aria-label="<?php esc_attr_e( 'Send message', 'plugifity' ); ?>">
            <span class="material-symbols-outlined">send</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Settings modal -->
<div class="modal-overlay" id="settingsOverlay" aria-hidden="true">
  <div class="modal" id="settingsModal" role="dialog" aria-labelledby="settingsTitle">
    <div class="modal-header">
      <h2 id="settingsTitle" class="modal-title"><?php esc_html_e( 'Settings', 'plugifity' ); ?></h2>
      <button type="button" class="modal-close" id="settingsClose" aria-label="<?php esc_attr_e( 'Close', 'plugifity' ); ?>">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <div class="modal-body">
      <div class="settings-status" id="settingsStatus" role="alert" aria-live="polite" hidden>
        <span class="settings-status-icon" id="settingsStatusIcon" aria-hidden="true"></span>
        <span class="settings-status-text" id="settingsMessage"></span>
        <button type="button" class="settings-status-action" id="settingsRetry" hidden>
          <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
          <?php esc_html_e( 'Try again', 'plugifity' ); ?>
        </button>
      </div>
      <div class="settings-loading" id="settingsLoading" hidden aria-hidden="true">
        <span class="material-symbols-outlined settings-spinner" aria-hidden="true">progress_activity</span>
        <span class="settings-loading-text"><?php esc_html_e( 'Loading…', 'plugifity' ); ?></span>
      </div>
      <div class="settings-section">
        <label for="settingsModel" class="settings-label"><?php esc_html_e( 'Model', 'plugifity' ); ?></label>
        <select id="settingsModel" class="settings-select">
          <optgroup label="DeepSeek">
            <option value="deepseek|deepseek-chat">DeepSeek Chat</option>
            <option value="deepseek|deepseek-coder">DeepSeek Coder</option>
            <option value="deepseek|deepseek-r1">DeepSeek R1</option>
          </optgroup>
          <optgroup label="OpenAI (ChatGPT)">
            <option value="chatgpt|gpt-4o">GPT-4o</option>
            <option value="chatgpt|gpt-4o-mini">GPT-4o mini</option>
            <option value="chatgpt|gpt-4-turbo">GPT-4 Turbo</option>
            <option value="chatgpt|gpt-3.5-turbo">GPT-3.5 Turbo</option>
            <option value="chatgpt|o1">o1</option>
            <option value="chatgpt|o1-mini">o1 mini</option>
          </optgroup>
          <optgroup label="Google (Gemini)">
            <option value="gemini|gemini-1.5-pro">Gemini 1.5 Pro</option>
            <option value="gemini|gemini-1.5-flash">Gemini 1.5 Flash</option>
            <option value="gemini|gemini-1.0-pro">Gemini 1.0 Pro</option>
          </optgroup>
          <optgroup label="Anthropic (Claude)">
            <option value="claude|claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
            <option value="claude|claude-3-5-haiku-20241022">Claude 3.5 Haiku</option>
            <option value="claude|claude-3-opus-20240229">Claude 3 Opus</option>
          </optgroup>
        </select>
      </div>
      <div class="settings-section api-keys" id="apiKeysSection">
        <div class="settings-api-row" data-model="deepseek">
          <label for="apiKeyDeepSeek" class="settings-label">DeepSeek API Key</label>
          <input type="text" id="apiKeyDeepSeek" class="settings-input" placeholder="sk-..." autocomplete="off" />
        </div>
        <div class="settings-api-row" data-model="chatgpt">
          <label for="apiKeyChatGPT" class="settings-label">OpenAI API Key</label>
          <input type="text" id="apiKeyChatGPT" class="settings-input" placeholder="sk-..." autocomplete="off" />
        </div>
        <div class="settings-api-row" data-model="gemini">
          <label for="apiKeyGemini" class="settings-label">Google API Key</label>
          <input type="text" id="apiKeyGemini" class="settings-input" placeholder="AIza..." autocomplete="off" />
        </div>
        <div class="settings-api-row" data-model="claude">
          <label for="apiKeyClaude" class="settings-label">Anthropic API Key</label>
          <input type="text" id="apiKeyClaude" class="settings-input" placeholder="sk-ant-..." autocomplete="off" />
        </div>
      </div>
      <div class="settings-section">
        <label class="settings-checkbox-label">
          <input type="checkbox" id="settingsAllowDbWrites" class="settings-checkbox" />
          <span><?php esc_html_e( 'Allow database writes (agent can run INSERT/UPDATE/DELETE after enabling)', 'plugifity' ); ?></span>
        </label>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn-primary" id="settingsSave"><?php esc_html_e( 'Save', 'plugifity' ); ?></button>
      <button type="button" class="btn-secondary" id="settingsCloseBtn"><?php esc_html_e( 'Close', 'plugifity' ); ?></button>
    </div>
  </div>
</div>
