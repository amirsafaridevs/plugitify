<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Settings as CoreSettings;

/**
 * Admin Settings service (submenu + license registration).
 */
class Settings extends AbstractService
{
    public const SUBMENU_SLUG = 'plugifity-settings';

    /**
     * Boot the service: register Settings submenu under Plugifity.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerSubmenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueSettingsAssets']);
    }

    /**
     * Enqueue dashboard CSS on Settings page (same look as Dashboard).
     *
     * @param string $hook_suffix
     * @return void
     */
    public function enqueueSettingsAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'plugifity_page_plugifity-settings') {
            return;
        }
        $app = $this->getApplication();
        $app->enqueueStyle('plugitify-dashboard', 'admin/dashboard.css', [], 'admin_page:plugifity-settings');
        $app->enqueueScript('plugitify-dashboard', 'admin/dashboard.js', [], true, 'admin_page:plugifity-settings');
    }

    /**
     * Register Settings submenu under the main Plugifity menu.
     *
     * @return void
     */
    public function registerSubmenu(): void
    {
        add_submenu_page(
            'plugifity',
            __('Settings', 'plugitify'),
            __('Settings', 'plugitify'),
            'manage_options',
            self::SUBMENU_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Render the settings page (license key form + validation message).
     * Uses Application::view(). Handles POST save and validation.
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        $message = null;
        $is_valid = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugitify_settings_nonce'])) {
            if (current_user_can('manage_options') && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['plugitify_settings_nonce'])),
                'plugitify_save_settings'
            )) {
                $key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';
                CoreSettings::set('license_key', $key);
                $result = $this->validateLicense($key);
                CoreSettings::set('license_status', $result['valid'] ? 'valid' : 'invalid');
                $is_valid = $result['valid'];
                $message = $result['message'];
            }
        }

        if ($message === null && $is_valid === null) {
            $stored = CoreSettings::get('license_status', null);
            $stored_key = CoreSettings::get('license_key', '');
            if ($stored !== null && $stored_key !== '') {
                $is_valid = $stored === 'valid';
                $message = $is_valid
                    ? __('License is valid and active.', 'plugitify')
                    : __('License is invalid or inactive.', 'plugitify');
            }
        }

        $license_key = CoreSettings::get('license_key', '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['license_key'])) {
            $license_key = sanitize_text_field(wp_unslash($_POST['license_key']));
        }

        $app = $this->getApplication();
        $app->view('Settings/index', [
            'license_key' => $license_key,
            'license_message' => $message,
            'license_valid' => $is_valid,
        ]);
    }

    /**
     * Validate license key (purchased from wpagentify.com).
     * Can be extended to call wpagentify API.
     *
     * @param string $key License key.
     * @return array{valid: bool, message: string}
     */
    private function validateLicense(string $key): array
    {
        $key = trim($key);
        if ($key === '') {
            return [
                'valid' => false,
                'message' => __('Please enter a license key.', 'plugitify'),
            ];
        }

        // Placeholder: extend to validate via wpagentify.com API (e.g. wp_remote_post).
        // Example: $response = wp_remote_post('https://wpagentify.com/api/license/validate', ['body' => ['key' => $key]]);
        $valid = strlen($key) >= 10;

        return [
            'valid' => $valid,
            'message' => $valid
                ? __('License is valid and active.', 'plugitify')
                : __('License is invalid or inactive.', 'plugitify'),
        ];
    }
}
