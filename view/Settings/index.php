<?php
/**
 * Settings view: license key (wpagentify.com) – same design as Dashboard (rounded, icons, animations).
 *
 * @var string      $license_key     Current license key value
 * @var string|null $license_message Message after validation (or current status)
 * @var bool|null   $license_valid   true = green, false = red, null = no message
 */

if (!defined('ABSPATH')) {
    exit;
}

$license_key = $license_key ?? '';
$license_message = $license_message ?? null;
$license_valid = $license_valid ?? null;
?>
<div class="wrap plugifity-dashboard plugifity-settings-page">
    <!-- Inline SVG sprite (settings + key icons, no CDN) -->
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <symbol id="pfy-icon-settings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></symbol>
        <symbol id="pfy-icon-key" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></symbol>
        <symbol id="pfy-icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></symbol>
        <symbol id="pfy-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></symbol>
    </svg>

    <div class="plugifity-content">
        <h1 class="plugifity-page-title">
            <svg class="plugifity-icon plugifity-icon--lg" aria-hidden="true"><use href="#pfy-icon-settings"/></svg>
            <?php esc_html_e('Settings', 'plugitify'); ?>
        </h1>

        <div class="plugifity-settings-card">
            <div class="plugifity-card-header" style="margin-bottom: 16px;">
                <div class="plugifity-card-icon api" style="width: 44px; height: 44px;">
                    <svg class="plugifity-icon" style="width: 22px; height: 22px;" aria-hidden="true"><use href="#pfy-icon-key"/></svg>
                </div>
                <div>
                    <h2 class="plugifity-card-title" style="margin: 0 0 4px 0;"><?php esc_html_e('License key', 'plugitify'); ?></h2>
                    <p class="plugifity-settings-desc" style="margin: 0;"><?php esc_html_e('Register the license purchased from wpagentify.com.', 'plugitify'); ?></p>
                </div>
            </div>

            <form method="post" action="">
                <?php wp_nonce_field('plugitify_save_settings', 'plugitify_settings_nonce'); ?>
                <div class="plugifity-field">
                    <label for="plugitify-license-key" class="plugifity-field-label"><?php esc_html_e('License key', 'plugitify'); ?></label>
                    <input type="text"
                           id="plugitify-license-key"
                           name="license_key"
                           value="<?php echo esc_attr($license_key); ?>"
                           class="plugifity-input"
                           placeholder="<?php esc_attr_e('Enter your license key', 'plugitify'); ?>"
                           autocomplete="off">
                </div>
                <p style="margin: 0;">
                    <button type="submit" class="plugifity-btn-submit">
                        <svg class="plugifity-icon" style="width: 18px; height: 18px;" aria-hidden="true"><use href="#pfy-icon-check"/></svg>
                        <?php esc_html_e('Save & validate', 'plugitify'); ?>
                    </button>
                </p>
            </form>
        </div>

        <?php if ($license_message !== null) : ?>
            <div class="plugifity-license-message plugifity-license-message--<?php echo $license_valid ? 'valid' : 'invalid'; ?>"
                 role="alert">
                <?php if ($license_valid) : ?>
                    <svg class="plugifity-icon" style="width: 20px; height: 20px;" aria-hidden="true"><use href="#pfy-icon-check"/></svg>
                <?php else : ?>
                    <svg class="plugifity-icon" style="width: 20px; height: 20px;" aria-hidden="true"><use href="#pfy-icon-close"/></svg>
                <?php endif; ?>
                <span><?php echo esc_html($license_message); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>
