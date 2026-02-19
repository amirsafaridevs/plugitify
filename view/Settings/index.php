<?php
/**
 * Settings view: one tab per tool (Query, File, General). In each tab, enable/disable per endpoint.
 * Ping is not in settings. UI/UX aligned with Dashboard (cards, icons, tabs).
 *
 * @var string   $current_tab     Active tab key (query, file, general)
 * @var array   $available_tabs   Tab key => label
 * @var array   $tool_endpoints   Tool slug => [ endpoint_slug => label ]
 * @var array   $tools_enabled    Tool slug => [ endpoint_slug => bool ]
 * @var string|null $message     Success message after save
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_tab    = $current_tab ?? 'query';
$available_tabs = $available_tabs ?? [];
$tool_endpoints = $tool_endpoints ?? [];
$tools_enabled  = $tools_enabled ?? [];
$message        = $message ?? null;

$base_url = admin_url('admin.php?page=plugifity-settings');
$endpoints_for_tab = $tool_endpoints[$current_tab] ?? [];
?>
<div class="wrap plugifity-dashboard plugifity-settings-page">
    <svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true">
        <symbol id="pfy-icon-settings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></symbol>
    </svg>

    <div class="plugifity-content">
        <h1 class="plugifity-page-title">
            <svg class="plugifity-icon plugifity-icon--lg" aria-hidden="true"><use href="#pfy-icon-settings"/></svg>
            <?php esc_html_e('Settings', 'plugitify'); ?>
        </h1>

        <nav class="plugifity-settings-tabs" aria-label="<?php esc_attr_e('Settings tabs', 'plugitify'); ?>">
            <?php foreach ($available_tabs as $tab_key => $label) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, $base_url)); ?>"
                   class="plugifity-settings-tab <?php echo $current_tab === $tab_key ? 'plugifity-settings-tab--active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="plugifity-settings-content">
            <?php if ($message) : ?>
                <div class="plugifity-settings-notice plugifity-settings-notice--success" role="status">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="plugifity-card plugifity-settings-card">
                <div class="plugifity-card-header plugifity-settings-card-header">
                    <h2 class="plugifity-settings-section-title">
                        <?php echo esc_html($available_tabs[$current_tab] ?? $current_tab); ?>
                    </h2>
                </div>
                <p class="plugifity-settings-desc">
                    <?php esc_html_e('Enable or disable each endpoint. When disabled, API requests for that endpoint will be rejected with a message that the WordPress admin has disabled it from Plugifity settings.', 'plugitify'); ?>
                </p>

                <form method="post" action="" class="plugifity-settings-form">
                    <?php wp_nonce_field('plugitify_save_settings', 'plugifity_settings_nonce'); ?>
                    <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" />

                    <?php if (!empty($endpoints_for_tab)) : ?>
                        <ul class="plugifity-settings-list" role="list">
                            <?php foreach ($endpoints_for_tab as $endpoint_slug => $endpoint_label) : ?>
                                <?php
                                $is_enabled = !empty($tools_enabled[$current_tab][$endpoint_slug]);
                                ?>
                                <li class="plugifity-settings-item">
                                    <div class="plugifity-settings-item-body">
                                        <span class="plugifity-settings-item-label"><?php echo esc_html($endpoint_label); ?></span>
                                        <label class="plugifity-toggle-wrap" for="tool-<?php echo esc_attr($current_tab); ?>-<?php echo esc_attr($endpoint_slug); ?>">
                                            <input type="checkbox"
                                                   name="tool_enabled[<?php echo esc_attr($endpoint_slug); ?>]"
                                                   id="tool-<?php echo esc_attr($current_tab); ?>-<?php echo esc_attr($endpoint_slug); ?>"
                                                   value="1"
                                                   <?php checked($is_enabled); ?>
                                                   class="plugifity-toggle-input" />
                                            <span class="plugifity-toggle-track" aria-hidden="true"></span>
                                            <span class="plugifity-toggle-label">
                                                <?php echo $is_enabled
                                                    ? esc_html__('Enabled', 'plugitify')
                                                    : esc_html__('Disabled', 'plugitify'); ?>
                                            </span>
                                        </label>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <p class="plugifity-settings-actions">
                            <button type="submit" class="plugifity-btn plugifity-btn--primary">
                                <?php esc_html_e('Save changes', 'plugitify'); ?>
                            </button>
                        </p>
                    <?php else : ?>
                        <div class="plugifity-empty">
                            <p class="plugifity-empty-text"><?php esc_html_e('No endpoints in this category.', 'plugitify'); ?></p>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
