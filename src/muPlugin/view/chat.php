<?php
use Plugitify\muPlugin\Core\View;

$pi_locale    = get_locale();
$pi_lang_attr = str_replace('_', '-', $pi_locale);
$pi_slug      = (string) ($slug ?? '');
$pi_iframe    = (string) ($iframeUrl ?? '');
$pi_config    = is_array($agentConfig ?? null) ? $agentConfig : [];
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
            <div class="pi-chat-browser">
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
                </div>

                <div class="pi-chat-messages" id="pi-chat-messages"></div>

                <div class="pi-chat-input-wrap">
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
