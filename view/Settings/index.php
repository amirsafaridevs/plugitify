<?php
/**
 * Settings view: one tab per tool (Query, File, General). In each tab, enable/disable per endpoint.
 * Ping is not in settings.
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
    <h1 class="plugifity-page-title">
        <?php esc_html_e('Settings', 'plugitify'); ?>
    </h1>

    <nav class="nav-tab-wrapper plugifity-settings-tabs" aria-label="<?php esc_attr_e('Settings tabs', 'plugitify'); ?>">
        <?php foreach ($available_tabs as $tab_key => $label) : ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_key, $base_url)); ?>"
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="plugifity-settings-content" style="margin-top: 20px;">
        <?php if ($message) : ?>
            <div class="notice notice-success is-dismissible" style="margin: 0 0 16px 0;">
                <p><?php echo esc_html($message); ?></p>
            </div>
        <?php endif; ?>

        <div class="plugifity-card" style="max-width: 720px;">
            <h2 class="plugifity-settings-section-title" style="margin-top: 0; font-size: 1.1em; color: #1c1b1f;">
                <?php echo esc_html($available_tabs[$current_tab] ?? $current_tab); ?>
            </h2>
            <p class="description" style="margin-bottom: 20px;">
                <?php esc_html_e('Enable or disable each endpoint. When disabled, API requests for that endpoint will be rejected with a message that the WordPress admin has disabled it from Plugifity settings.', 'plugitify'); ?>
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('plugitify_save_settings', 'plugifity_settings_nonce'); ?>
                <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" />

                <table class="form-table" role="presentation">
                    <tbody>
                        <?php foreach ($endpoints_for_tab as $endpoint_slug => $endpoint_label) : ?>
                            <?php
                            $is_enabled = !empty($tools_enabled[$current_tab][$endpoint_slug]);
                            ?>
                            <tr>
                                <th scope="row" style="padding: 12px 0;">
                                    <label for="tool-<?php echo esc_attr($current_tab); ?>-<?php echo esc_attr($endpoint_slug); ?>">
                                        <?php echo esc_html($endpoint_label); ?>
                                    </label>
                                </th>
                                <td style="padding: 12px 0;">
                                    <label class="plugifity-toggle-wrap">
                                        <input type="checkbox"
                                               name="tool_enabled[<?php echo esc_attr($endpoint_slug); ?>]"
                                               id="tool-<?php echo esc_attr($current_tab); ?>-<?php echo esc_attr($endpoint_slug); ?>"
                                               value="1"
                                               <?php checked($is_enabled); ?> />
                                        <span class="plugifity-toggle-label">
                                            <?php echo $is_enabled
                                                ? esc_html__('Enabled', 'plugitify')
                                                : esc_html__('Disabled', 'plugitify'); ?>
                                        </span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($endpoints_for_tab)) : ?>
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save changes', 'plugitify'); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
