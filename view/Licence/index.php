<?php
/**
 * Licence view: license key (wpagentify.com) – centered large card + buy section.
 * Design language matches Dashboard/Chat (rounded, tokens, animations).
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
$buy_license_url = 'https://wpagentify.com';
?>
<div class="wrap plugifity-dashboard plugifity-licence-page">
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <symbol id="pfy-icon-key" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></symbol>
        <symbol id="pfy-icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></symbol>
        <symbol id="pfy-icon-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
        <symbol id="pfy-icon-external" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></symbol>
    </svg>

    <div class="plugifity-licence-hero">
        <div class="plugifity-licence-card" role="region" aria-labelledby="pfy-licence-title">
            <div class="plugifity-licence-card-inner">
                <div class="plugifity-licence-card-header">
                    <div class="plugifity-licence-icon" aria-hidden="true">
                        <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-key"/></svg>
                    </div>
                    <h1 id="pfy-licence-title" class="plugifity-licence-title"><?php esc_html_e('Licence', 'plugitify'); ?></h1>
                    <p class="plugifity-licence-desc"><?php esc_html_e('Register the license purchased from wpagentify.com to unlock all features.', 'plugitify'); ?></p>
                </div>

                <form method="post" action="" class="plugifity-licence-form">
                    <?php wp_nonce_field('plugitify_save_license', 'plugitify_license_nonce'); ?>
                    <div class="plugifity-licence-field">
                        <label for="plugitify-license-key" class="plugifity-licence-label"><?php esc_html_e('License key', 'plugitify'); ?></label>
                        <input type="text"
                               id="plugitify-license-key"
                               name="license_key"
                               value="<?php echo esc_attr($license_key); ?>"
                               class="plugifity-licence-input"
                               placeholder="<?php esc_attr_e('Enter your license key', 'plugitify'); ?>"
                               autocomplete="off">
                    </div>
                    <?php if ($license_message !== null) : ?>
                        <div class="plugifity-licence-message plugifity-licence-message--<?php echo $license_valid ? 'valid' : 'invalid'; ?>"
                             role="alert">
                            <?php if ($license_valid) : ?>
                                <span class="plugifity-licence-dot plugifity-licence-dot--valid" aria-hidden="true"></span>
                            <?php else : ?>
                                <span class="plugifity-licence-dot plugifity-licence-dot--invalid" aria-hidden="true"></span>
                            <?php endif; ?>
                            <span><?php echo esc_html($license_message); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="plugifity-licence-actions">
                        <button type="submit" class="plugifity-licence-btn">
                            <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-check"/></svg>
                            <?php esc_html_e('Save & validate', 'plugitify'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="plugifity-licence-buy" role="complementary" aria-labelledby="pfy-buy-title">
            <div class="plugifity-licence-buy-inner">
                <div class="plugifity-licence-buy-icon" aria-hidden="true">
                    <svg class="plugifity-icon" aria-hidden="true"><use href="#pfy-icon-shield"/></svg>
                </div>
                <h2 id="pfy-buy-title" class="plugifity-licence-buy-title"><?php esc_html_e('How to buy a licence', 'plugitify'); ?></h2>
                <p class="plugifity-licence-buy-desc"><?php esc_html_e('Purchase a valid licence from wpagentify.com to activate the plugin and get updates and support.', 'plugitify'); ?></p>
                <a href="<?php echo esc_url($buy_license_url); ?>" target="_blank" rel="noopener noreferrer" class="plugifity-licence-link">
                    <span><?php esc_html_e('Buy licence at wpagentify.com', 'plugitify'); ?></span>
                    <svg class="plugifity-icon plugifity-licence-link-icon" aria-hidden="true"><use href="#pfy-icon-external"/></svg>
                </a>
            </div>
        </div>
    </div>
</div>
