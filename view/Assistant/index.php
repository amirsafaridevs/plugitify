<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<style>
    #wpcontent{
        padding-left:0px !important;
    }
    #wpbody-content{
        padding-bottom: 0 !important
    }
    #wpfooter{
        display:none !important;
    }
</style>
<div class="agentify-app">
    <aside class="agentify-sidebar">
      <div class="agentify-sidebar-header">
        <span class="material-symbols-outlined agentify-sidebar-logo">smart_toy</span>
        <span class="agentify-sidebar-brand">Plugitify</span>
      </div>
      <button type="button" class="agentify-btn-new-chat" id="agentify-btn-new-chat" aria-label="New chat">
        <span class="material-symbols-outlined">add</span>
        New chat
      </button>
      <nav class="agentify-chat-list" id="agentify-chat-list" aria-label="Chat history">
        <div class="agentify-chat-list-placeholder" id="agentify-chat-list-placeholder">
          <span class="material-symbols-outlined">forum</span>
          <p>No chats yet</p>
          <span>Start a new chat above</span>
        </div>
        <ul class="agentify-chat-items" id="agentify-chat-items" hidden></ul>
      </nav>
      <div class="agentify-sidebar-footer">
        <button type="button" class="agentify-sidebar-btn" id="agentify-btn-settings" aria-label="Settings">
          <span class="material-symbols-outlined">settings</span>
          Settings
        </button>
      </div>
    </aside>

    <div class="agentify-main">
      <div class="agentify-chat-panel">
        <div class="agentify-messages" id="agentify-messages" role="log" aria-live="polite">
          <div class="agentify-welcome" id="agentify-welcome">
            <span class="material-symbols-outlined agentify-welcome-icon">psychology</span>
            <p>Hi. Ask me anything.</p>
          </div>
        </div>

        <form class="agentify-input-area" id="agentify-chat-form" novalidate>
          <div class="agentify-input-wrapper">
            <label for="agentify-user-input" class="agentify-visually-hidden">Your message</label>
            <textarea
              id="agentify-user-input"
              class="agentify-input-field"
              placeholder="Type your message... (Shift+Enter for new line)"
              autocomplete="off"
              maxlength="2000"
              rows="1"
            ></textarea>
            <button type="submit" class="agentify-btn-send" id="agentify-btn-send" aria-label="Send message">
              <span class="material-symbols-outlined">send</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Settings modal -->
  <div class="agentify-modal-overlay" id="agentify-settings-overlay" aria-hidden="true">
    <div class="agentify-modal" id="agentify-settings-modal" role="dialog" aria-labelledby="agentify-settings-title">
      <div class="agentify-modal-header">
        <h2 id="agentify-settings-title" class="agentify-modal-title">Settings</h2>
        <button type="button" class="agentify-modal-close" id="agentify-settings-close" aria-label="Close">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="agentify-modal-body">
        <div class="agentify-settings-section">
          <label for="agentify-settings-model" class="agentify-settings-label">Model</label>
          <select id="agentify-settings-model" class="agentify-settings-select">
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
        <div class="agentify-settings-section agentify-api-keys" id="agentify-api-keys-section">
          <div class="agentify-settings-api-row" data-model="deepseek">
            <label for="agentify-api-key-deepseek" class="agentify-settings-label">DeepSeek API Key</label>
            <input type="password" id="agentify-api-key-deepseek" class="agentify-settings-input" placeholder="sk-..." autocomplete="off" />
          </div>
          <div class="agentify-settings-api-row" data-model="chatgpt">
            <label for="agentify-api-key-chatgpt" class="agentify-settings-label">OpenAI API Key</label>
            <input type="password" id="agentify-api-key-chatgpt" class="agentify-settings-input" placeholder="sk-..." autocomplete="off" />
          </div>
          <div class="agentify-settings-api-row" data-model="gemini">
            <label for="agentify-api-key-gemini" class="agentify-settings-label">Google API Key</label>
            <input type="password" id="agentify-api-key-gemini" class="agentify-settings-input" placeholder="AIza..." autocomplete="off" />
          </div>
          <div class="agentify-settings-api-row" data-model="claude">
            <label for="agentify-api-key-claude" class="agentify-settings-label">Anthropic API Key</label>
            <input type="password" id="agentify-api-key-claude" class="agentify-settings-input" placeholder="sk-ant-..." autocomplete="off" />
          </div>
        </div>
      </div>
      <div class="agentify-modal-footer">
        <button type="button" class="agentify-btn-secondary" id="agentify-settings-save">Save</button>
        <button type="button" class="agentify-btn-primary" id="agentify-settings-close-btn">Close</button>
      </div>
    </div>
  </div>

