<?php
/**
 * Chat view: LLM chat UI (English) with sidebar + thinking + reasoning_content.
 * Design language matches Dashboard/Licence (rounded, tokens, skeleton reveal).
 */

if (!defined('ABSPATH')) {
    exit;
}

$initial_chats = [];
if (!empty($chats)) {
    foreach ($chats as $c) {
        $initial_chats[] = [
            'id'         => (int) $c->id,
            'title'      => $c->title !== null && $c->title !== '' ? $c->title : __('Chat', 'plugitify'),
            'updated_at' => $c->updated_at ?? '',
        ];
    }
}
?>

<div class="wrap plugifity-dashboard plugifity-chat-page">
    <!-- Inline SVG sprite (chat icons, no CDN) -->
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <symbol id="pfy-icon-chat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
        </symbol>
        <symbol id="pfy-icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5v14"/><path d="M5 12h14"/>
        </symbol>
        <symbol id="pfy-icon-send" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>
        </symbol>
        <symbol id="pfy-icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>
        </symbol>
        <symbol id="pfy-icon-spark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 2l1.8 5.4L19 9l-5.2 1.6L12 16l-1.8-5.4L5 9l5.2-1.6z"/>
        </symbol>
        <symbol id="pfy-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
        </symbol>
        <symbol id="pfy-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="5"/><path d="M12 1v2"/><path d="M12 21v2"/><path d="M4.22 4.22l1.42 1.42"/><path d="M18.36 18.36l1.42 1.42"/><path d="M1 12h2"/><path d="M21 12h2"/><path d="M4.22 19.78l1.42-1.42"/><path d="M18.36 5.64l1.42-1.42"/>
        </symbol>
        <symbol id="pfy-icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </symbol>
        <symbol id="pfy-icon-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
        </symbol>
    </svg>

    <!-- Skeleton (initial loading) -->
    <div class="plugifity-skeleton">
        <div class="pfy-chat-skeleton" aria-hidden="true">
            <div class="pfy-chat-skel-col"></div>
            <div class="pfy-chat-skel-main"></div>
        </div>
    </div>

    <div class="plugifity-content">
        <div class="pfy-chat-shell">
            <aside class="pfy-chat-sidebar" aria-label="<?php echo esc_attr__('Chats', 'plugitify'); ?>">
                <div class="pfy-chat-sidebar-top">
                    <button type="button" class="plugifity-btn pfy-chat-new" data-pfy-new-chat>
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-plus"/></svg>
                        <?php esc_html_e('New chat', 'plugitify'); ?>
                    </button>
                </div>

                <label class="pfy-chat-search-wrap">
                    <span class="screen-reader-text"><?php esc_html_e('Search chats', 'plugitify'); ?></span>
                    <span class="pfy-chat-search-icon" aria-hidden="true">
                        <svg class="plugifity-icon"><use href="#pfy-icon-search"/></svg>
                    </span>
                    <input class="pfy-chat-search" type="search" inputmode="search"
                           placeholder="<?php echo esc_attr__('Search chats…', 'plugitify'); ?>"
                           data-pfy-search>
                </label>

                <div class="pfy-chat-thread-list" role="listbox" aria-label="<?php echo esc_attr__('Previous chats', 'plugitify'); ?>" data-pfy-thread-list data-initial-chats="<?php echo esc_attr(wp_json_encode($initial_chats)); ?>"></div>

                <div class="pfy-chat-sidebar-footer">
                    <button type="button" class="pfy-chat-theme-btn" data-pfy-theme-toggle aria-label="<?php echo esc_attr__('Toggle dark/light theme', 'plugitify'); ?>">
                        <span class="pfy-chat-theme-btn-icon pfy-chat-theme-btn-icon--light" aria-hidden="true">
                            <svg class="plugifity-icon"><use href="#pfy-icon-moon"/></svg>
                        </span>
                        <span class="pfy-chat-theme-btn-icon pfy-chat-theme-btn-icon--dark" aria-hidden="true">
                            <svg class="plugifity-icon"><use href="#pfy-icon-sun"/></svg>
                        </span>
                        <span class="pfy-chat-theme-btn-label" data-pfy-theme-label><?php esc_html_e('Dark mode', 'plugitify'); ?></span>
                    </button>
                </div>
            </aside>

            <main class="pfy-chat-main" aria-label="<?php echo esc_attr__('Conversation', 'plugitify'); ?>">
                <header class="pfy-chat-topbar">
                    <div class="pfy-chat-topbar-left">
                        <div class="pfy-chat-active-title" data-pfy-active-title><?php esc_html_e('Chat', 'plugitify'); ?></div>
                    </div>
                </header>

                <section class="pfy-chat-messages" data-pfy-messages role="log" aria-live="polite" aria-relevant="additions text">
                    <div class="pfy-chat-empty" data-pfy-empty>
                        <div class="pfy-chat-empty-inner">
                            <div class="pfy-chat-empty-icon" aria-hidden="true">
                                <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-spark"/></svg>
                            </div>
                            <p class="pfy-chat-empty-title"><?php esc_html_e('What would you like me to do? Type for me.', 'plugitify'); ?></p>
                        </div>
                    </div>
                    <div class="pfy-chat-messages-list" data-pfy-messages-list></div>
                </section>

                <footer class="pfy-chat-composer">
                    <form class="pfy-chat-form" data-pfy-form>
                        <label style="display:block;">
                            <span class="screen-reader-text"><?php esc_html_e('Message', 'plugitify'); ?></span>
                            <textarea class="pfy-chat-textarea"
                                      rows="1"
                                      placeholder="<?php echo esc_attr__('Message the assistant…', 'plugitify'); ?>"
                                      data-pfy-textarea></textarea>
                        </label>
                        <button type="submit" class="pfy-chat-send" data-pfy-send>
                            <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-send"/></svg>
                            <?php esc_html_e('Send', 'plugitify'); ?>
                        </button>
                    </form>
                </footer>
            </main>
        </div>
    </div>
</div>
