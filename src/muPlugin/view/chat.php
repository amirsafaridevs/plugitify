<?php
use Plugitify\muPlugin\Core\View;

$pi_locale         = get_locale();
$pi_lang_attr      = str_replace('_', '-', $pi_locale);
$pi_slug           = (string) ($slug ?? '');
$pi_iframe         = (string) ($iframeUrl ?? '');
$pi_config         = is_array($agentConfig ?? null) ? $agentConfig : [];
$pi_dashboard_url  = admin_url( 'admin.php?page=plugitify' );
$pi_settings_url   = admin_url( 'admin.php?page=plugitify-settings' );
?>
<html lang="<?php echo esc_attr($pi_lang_attr); ?>" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($pi_slug); ?></title>
        <?php echo View::css('chat.css'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- View::css() builds its own <link> tag with esc_url() around the only dynamic part. ?>
    </head>
    <body>
        <div id="pi-chat-app" class="pi-chat-layout">
            <div class="pi-chat-browser" id="pi-chat-browser">
                <div class="pi-browser-toolbar">
                    <button type="button" id="pi-browser-reload" class="pi-browser-btn" title="<?php esc_attr_e( 'بارگذاری مجدد', 'plugitify' ); ?>">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 12a9 9 0 1 1-2.64-6.36"></path>
                            <polyline points="21 3 21 9 15 9"></polyline>
                        </svg>
                    </button>
                    <div class="pi-browser-url-wrap">
                        <input type="text" id="pi-browser-url" class="pi-browser-url" value="<?php echo esc_attr( $pi_iframe ); ?>" dir="ltr" spellcheck="false" autocomplete="off" placeholder="https://">
                    </div>
                    <button type="button" id="pi-browser-go" class="pi-browser-btn" title="<?php esc_attr_e( 'برو', 'plugitify' ); ?>">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M5 12h14"></path>
                            <path d="m13 6 6 6-6 6"></path>
                        </svg>
                    </button>
                </div>
                <div class="pi-browser-frame-wrap">
                    <iframe id="pi-chat-iframe" src="<?php echo $pi_iframe !== '' ? esc_url( $pi_iframe ) : 'about:blank'; ?>" title="<?php esc_attr_e( 'Browser', 'plugitify' ); ?>"></iframe>
                </div>
                <div class="pi-browser-lock" id="pi-browser-lock" hidden aria-hidden="true">
                    <div class="pi-browser-lock__spinner" role="status" aria-label="<?php esc_attr_e( 'در حال کار…', 'plugitify' ); ?>"></div>
                </div>
            </div>

            <div class="pi-chat-resizer" id="pi-chat-resizer" role="separator" aria-orientation="vertical" aria-label="<?php esc_attr_e( 'تغییر عرض سایدبار', 'plugitify' ); ?>" tabindex="0"></div>

            <div class="pi-chat-sidebar" id="pi-chat-sidebar">
                <div class="pi-chat-header">
                    <button type="button" id="pi-chat-new" class="pi-chat-new-btn" title="<?php esc_attr_e( 'چت جدید', 'plugitify' ); ?>" aria-label="<?php esc_attr_e( 'چت جدید', 'plugitify' ); ?>">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span class="pi-chat-new-btn__label"><?php esc_html_e( 'چت جدید', 'plugitify' ); ?></span>
                    </button>
                    <div class="pi-chat-header-actions">
                        <a href="<?php echo esc_url( $pi_settings_url ); ?>" class="pi-chat-header-icon" title="<?php esc_attr_e( 'تنظیمات', 'plugitify' ); ?>" aria-label="<?php esc_attr_e( 'تنظیمات', 'plugitify' ); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                            </svg>
                        </a>
                        <a href="<?php echo esc_url( $pi_dashboard_url ); ?>" class="pi-chat-header-icon" title="<?php esc_attr_e( 'پیشخوان', 'plugitify' ); ?>" aria-label="<?php esc_attr_e( 'پیشخوان', 'plugitify' ); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="7" height="9" rx="1.5"></rect>
                                <rect x="14" y="3" width="7" height="5" rx="1.5"></rect>
                                <rect x="14" y="12" width="7" height="9" rx="1.5"></rect>
                                <rect x="3" y="16" width="7" height="5" rx="1.5"></rect>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="pi-chat-messages" id="pi-chat-messages">
                    <div class="pi-chat-empty" aria-hidden="true">
                        <div class="pi-chat-empty__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <p class="pi-chat-empty__text"><?php esc_html_e( 'از کجا شروع کنیم؟', 'plugitify' ); ?></p>
                        <p class="pi-chat-empty__hint"><?php esc_html_e( 'ایده‌ات را بنویس؛ با هم می‌سازیمش', 'plugitify' ); ?></p>
                    </div>
                </div>

                <div class="pi-chat-input-wrap">
                    <div class="pi-chat-notices" id="pi-chat-notices" aria-live="polite"></div>
                    <div class="pi-chat-input-box">
                        <textarea id="pi-chat-textarea" rows="1" placeholder="<?php esc_attr_e( 'پیام خود را بنویسید...', 'plugitify' ); ?>"></textarea>
                        <button type="button" id="pi-chat-send" class="pi-chat-send-btn" title="<?php esc_attr_e( 'ارسال', 'plugitify' ); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 19V5"></path>
                                <path d="M5 12l7-7 7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Agent configuration. JSON_HEX_* keeps "</script>" and friends from
        // ever terminating the block early, so no value here can break out of
        // it and become markup.
        printf(
            '<script type="application/json" id="pi-agent-config">%s</script>',
            wp_json_encode( $pi_config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE )
        );

        echo View::js( 'chat.js' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- View::js() builds its own <script> tag with esc_url() around the only dynamic part.
        echo View::js( 'agent.bundle.js' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- same as above.
        ?>
    </body>
</html>
