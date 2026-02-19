<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Settings as CoreSettings;

/**
 * Admin Licence service (submenu + license registration).
 */
class Licence extends AbstractService
{
    public const SUBMENU_SLUG = 'plugifity-licence';

    /**
     * Boot the service: register Licence submenu under Plugifity.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerSubmenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueLicenceAssets']);
        add_action('wp_ajax_plugitify_generate_tools_key', [$this, 'ajaxGenerateToolsKey']);
    }

    /**
     * Enqueue dashboard CSS on Licence page (same look as Dashboard).
     *
     * @param string $hook_suffix
     * @return void
     */
    public function enqueueLicenceAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'plugifity_page_plugifity-licence') {
            return;
        }
        $app = $this->getApplication();
        $app->enqueueStyle('plugitify-dashboard', 'admin/dashboard.css', [], 'admin_page:plugifity-licence');
        $app->enqueueScript('plugitify-dashboard', 'admin/dashboard.js', [], true, 'admin_page:plugifity-licence');
        wp_localize_script('plugitify-dashboard', 'plugitifyDashboard', [
            'generateToolsKeyNonce' => wp_create_nonce('plugitify_generate_tools_key'),
        ]);
    }

    /**
     * Register Licence submenu under the main Plugifity menu.
     *
     * @return void
     */
    public function registerSubmenu(): void
    {
        add_submenu_page(
            'plugifity',
            __('Licence', 'plugitify'),
            __('Licence', 'plugitify'),
            'manage_options',
            self::SUBMENU_SLUG,
            [$this, 'renderLicencePage']
        );
    }

    /**
     * Render the licence page (license key form + validation message).
     * Uses Application::view(). Handles POST save and validation.
     *
     * @return void
     */
    public function renderLicencePage(): void
    {
        $message = null;
        $is_valid = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugitify_license_nonce'])) {
            if (current_user_can('manage_options') && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['plugitify_license_nonce'])),
                'plugitify_save_license'
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

        // Tools API token: migrate from legacy option if needed, then generate if empty.
        $tools_api_token = CoreSettings::get('tools_api_token', '');
        $tools_api_token = is_string($tools_api_token) ? trim($tools_api_token) : '';
        if ($tools_api_token === '') {
            $legacy = CoreSettings::get('tools_api_key', '');
            $legacy = is_string($legacy) ? trim($legacy) : '';
            if ($legacy !== '') {
                $tools_api_token = $legacy;
                CoreSettings::set('tools_api_token', $tools_api_token);
            }
        }
        if ($tools_api_token === '') {
            try {
                $tools_api_token = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $tools_api_token = wp_generate_password(64, false, false);
            }
            CoreSettings::set('tools_api_token', $tools_api_token);
        }

        $app = $this->getApplication();
        $app->view('Licence/index', [
            'license_key' => $license_key,
            'license_message' => $message,
            'license_valid' => $is_valid,
            'tools_api_token' => $tools_api_token,
        ]);
    }

    /**
     * AJAX: generate a new 64-character random tools API token and save to options.
     *
     * @return void
     */
    public function ajaxGenerateToolsKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission.', 'plugitify')], 403);
        }

        check_ajax_referer('plugitify_generate_tools_key', 'nonce');

        try {
            $key = bin2hex(random_bytes(32));
        } catch (\Throwable $e) {
            $key = wp_generate_password(64, false, false);
        }

        if (!is_string($key) || strlen($key) < 32) {
            wp_send_json_error(['message' => __('Failed to generate token.', 'plugitify')], 500);
        }

        CoreSettings::set('tools_api_token', $key);

        wp_send_json_success(['key' => $key]);
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
        $backend_main_address = $this->getApplication()->getProperty('backend_main_address', '');
        $validation_url = $backend_main_address . 'sites/verify';
        $key = trim($key);

        if ($key === '') {
            return [
                'valid' => false,
                'message' => __('Please enter a license key.', 'plugitify'),
            ];
        }

        $payload = [
            'site_url' => site_url(),
            'license'  => $key,
        ];

        $response = Request::postJson($validation_url, $payload);

        if ($response === null) {
            return [
                'valid' => false,
                'message' => __('Could not reach license server. Please try again later.', 'plugitify'),
            ];
        }

        $success = !empty($response['success']);
        $message = isset($response['message']) && is_string($response['message'])
            ? $response['message']
            : ( $success
                ? __('License is valid and active.', 'plugitify')
                : __('License is invalid or inactive.', 'plugitify') );

        return [
            'valid'   => $success,
            'message' => $message,
        ];
    }
}
