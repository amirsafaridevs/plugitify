<?php

namespace Plugifity\Service\Admin;

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Settings as CoreSettings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Settings service – tabbed UI: one tab per tool (Query, File, General), per-endpoint enable/disable.
 * Ping and other non-tool routes are not shown in settings.
 */
class Settings extends AbstractService
{
    public const SUBMENU_SLUG = 'plugifity-settings';

    /** Tab slug => label (one tab per tool category). English source for i18n. */
    public const TOOL_TABS = [
        'query'   => 'Query (Database)',
        'file'    => 'File (Files & folders)',
        'general' => 'General (Plugins, themes, debug)',
    ];

    /** Tool slug => [ endpoint_slug => label ]. Ping is not included. English source for i18n. */
    public const TOOL_ENDPOINTS = [
        'query' => [
            'read'         => 'Read query',
            'execute'      => 'Execute query',
            'create-table' => 'Create table',
            'backup'       => 'Backup',
            'backup-list'  => 'Backup list',
            'restore'      => 'Restore',
            'tables'       => 'List tables',
        ],
        'file' => [
            'grep'                => 'Grep (search in files)',
            'list-directory'     => 'List directory',
            'wp-path'            => 'WordPress path',
            'read'               => 'Read file',
            'create'             => 'Create file',
            'create-folder'      => 'Create folder',
            'replace-content'    => 'Replace content',
            'replace-line'       => 'Replace line',
            'delete'             => 'Delete file or folder',
            'search-replace'     => 'Search & replace',
            'read-range'         => 'Read line range',
            'create-with-content'=> 'Create file with content',
            'replace-lines'      => 'Replace lines',
        ],
        'general' => [
            'plugins'       => 'Plugins list',
            'themes'        => 'Themes list',
            'debug'         => 'Debug settings',
            'log'           => 'Read log',
            'site-urls'     => 'Site URLs',
            'create-plugin' => 'Create plugin',
            'create-theme'  => 'Create theme',
            'delete-plugin' => 'Delete plugin',
            'delete-theme'  => 'Delete theme',
        ],
    ];

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
     * Enqueue assets only on Settings page.
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
     * Render the settings page: one tab per tool (Query, File, General), per-endpoint checkboxes.
     *
     * @return void
     */
    public function renderSettingsPage(): void
    {
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'query';
        if (!isset(self::TOOL_TABS[$current_tab])) {
            $current_tab = 'query';
        }

        $tools_enabled = CoreSettings::get('tools_enabled', []);
        $message = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plugifity_settings_nonce'])) {
            if (current_user_can('manage_options') && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['plugifity_settings_nonce'])),
                'plugitify_save_settings'
            )) {
                $tab = isset($_POST['tab']) ? sanitize_key($_POST['tab']) : '';
                if (isset(self::TOOL_ENDPOINTS[$tab])) {
                    $new = [];
                    foreach (array_keys(self::TOOL_ENDPOINTS[$tab]) as $endpoint) {
                        $new[$endpoint] = !empty($_POST['tool_enabled'][$endpoint]);
                    }
                    $tools_enabled[$tab] = $new;
                    CoreSettings::set('tools_enabled', $tools_enabled);
                    $message = __('Settings saved.', 'plugitify');
                }
            }
        }

        // Default: all endpoints enabled when not set
        foreach (self::TOOL_ENDPOINTS as $tool => $endpoints) {
            if (!isset($tools_enabled[$tool]) || !is_array($tools_enabled[$tool])) {
                $tools_enabled[$tool] = [];
            }
            foreach (array_keys($endpoints) as $ep) {
                if (!isset($tools_enabled[$tool][$ep])) {
                    $tools_enabled[$tool][$ep] = true;
                }
            }
        }

        // Pass translated labels for i18n (text domain: plugitify).
        $available_tabs = [];
        foreach (self::TOOL_TABS as $k => $label) {
            $available_tabs[$k] = __($label, 'plugitify');
        }
        $tool_endpoints_translated = [];
        foreach (self::TOOL_ENDPOINTS as $tool => $endpoints) {
            $tool_endpoints_translated[$tool] = [];
            foreach ($endpoints as $ep => $label) {
                $tool_endpoints_translated[$tool][$ep] = __($label, 'plugitify');
            }
        }

        $app = $this->getApplication();
        $app->view('Settings/index', [
            'current_tab'   => $current_tab,
            'available_tabs'=> $available_tabs,
            'tool_endpoints'=> $tool_endpoints_translated,
            'tools_enabled' => $tools_enabled,
            'message'       => $message,
        ]);
    }
}
