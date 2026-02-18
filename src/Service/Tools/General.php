<?php

namespace Plugifity\Service\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Http\Response;
use Plugifity\Core\ToolsPolicy;
use Plugifity\Helper\RecordBuffer;

/**
 * API Tools Ã¢â‚¬â€œ General service (plugins, themes, debug, site URLs, log).
 */
class General extends AbstractService
{
    private const API_SOURCE = 'api.general';

    /**
     * Record general API call and return buffer for optional log/change and save.
     *
     * @param Request $request
     * @param string  $endpoint Endpoint path (e.g. 'general/plugins').
     * @param string  $title    Short title for the action.
     * @param array   $details  Optional details.
     * @return RecordBuffer
     */
    private function recordGeneralApi(Request $request, string $endpoint, string $title, array $details = []): RecordBuffer
    {
        $buffer = RecordBuffer::get();
        $buffer->addApiRequest(
            $endpoint,
            $title,
            null,
            self::API_SOURCE,
            $details !== [] ? wp_json_encode($details) : null
        );
        return $buffer;
    }

    /**
     * Boot the service Ã¢â‚¬â€œ register general API routes.
     *
     * @return void
     */
    public function boot(): void
    {
        ApiRouter::post('general/plugins', [$this, 'pluginsList'])->name('api.tools.general.plugins')->tool('general', 'plugins');
        ApiRouter::post('general/themes', [$this, 'themesList'])->name('api.tools.general.themes')->tool('general', 'themes');
        ApiRouter::post('general/debug', [$this, 'debugSettings'])->name('api.tools.general.debug')->tool('general', 'debug');
        ApiRouter::post('general/log', [$this, 'readLog'])->name('api.tools.general.log')->tool('general', 'log');
        ApiRouter::post('general/site-urls', [$this, 'siteUrls'])->name('api.tools.general.site-urls')->tool('general', 'site-urls');
        ApiRouter::post('general/create-plugin', [$this, 'createPlugin'])->name('api.tools.general.create-plugin')->tool('general', 'create-plugin');
        ApiRouter::post('general/create-theme', [$this, 'createTheme'])->name('api.tools.general.create-theme')->tool('general', 'create-theme');
        ApiRouter::post('general/delete-plugin', [$this, 'deletePlugin'])->name('api.tools.general.delete-plugin')->tool('general', 'delete-plugin');
        ApiRouter::post('general/delete-theme', [$this, 'deleteTheme'])->name('api.tools.general.delete-theme')->tool('general', 'delete-theme');
    }

    /**
     * List all plugins with details (description, version, path, active status).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function pluginsList(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'plugins')) !== null) {
            return $r;
        }
        $buffer = $this->recordGeneralApi($request, 'general/plugins', __('List plugins', 'plugitify'));

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all   = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $list  = [];
        foreach ($all as $pluginFile => $info) {
            $list[] = [
                'name'        => isset($info['Name']) ? (string) $info['Name'] : '',
                'description' => isset($info['Description']) ? (string) $info['Description'] : '',
                'version'     => isset($info['Version']) ? (string) $info['Version'] : '',
                'author'      => isset($info['Author']) ? (string) $info['Author'] : '',
                'plugin_uri'  => isset($info['PluginURI']) ? (string) $info['PluginURI'] : '',
                'path'        => $pluginFile,
                'is_active'   => in_array($pluginFile, $active, true),
            ];
        }
        $buffer->addLog('info', __('Plugins list.', 'plugitify'), wp_json_encode(['count' => count($list)]));
        $buffer->save();
        return Response::success(__('Plugins list.', 'plugitify'), ['plugins' => $list]);
    }

    /**
     * List all themes with details (description, version, path, active status).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function themesList(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'themes')) !== null) {
            return $r;
        }
        $buffer = $this->recordGeneralApi($request, 'general/themes', __('List themes', 'plugitify'));

        $current_stylesheet = get_option('stylesheet', '');
        $themes             = wp_get_themes();
        $list               = [];
        foreach ($themes as $slug => $theme) {
            /** @var \WP_Theme $theme */
            $list[] = [
                'name'        => $theme->get('Name'),
                'description' => $theme->get('Description'),
                'version'     => $theme->get('Version'),
                'author'      => $theme->get('Author'),
                'theme_uri'   => $theme->get('ThemeURI'),
                'path'        => $theme->get_stylesheet_directory(),
                'template'    => $theme->get_template(),
                'is_active'   => ($theme->get_stylesheet() === $current_stylesheet),
            ];
        }
        $buffer->addLog('info', __('Themes list.', 'plugitify'), wp_json_encode(['count' => count($list)]));
        $buffer->save();
        return Response::success(__('Themes list.', 'plugitify'), ['themes' => $list]);
    }

    /**
     * Get or set debug settings (WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY) in wp-config.php.
     * POST with enabled, log_to_file, display to update; empty body returns current state.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function debugSettings(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'debug')) !== null) {
            return $r;
        }
        $buffer = $this->recordGeneralApi($request, 'general/debug', __('Get or set debug settings', 'plugitify'), [
            'enabled'     => $request->input('enabled'),
            'log_to_file' => $request->input('log_to_file'),
            'display'     => $request->input('display'),
        ]);

        $configPath = ABSPATH . 'wp-config.php';
        if (!is_file($configPath) || !is_readable($configPath)) {
            $buffer->addLog('error', __('wp-config.php not found or not readable.', 'plugitify'));
            $buffer->save();
            return Response::error(__('wp-config.php not found or not readable.', 'plugitify'));
        }

        $content   = file_get_contents($configPath);
        if ($content === false) {
            $buffer->addLog('error', __('Could not read wp-config.php.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Could not read wp-config.php.', 'plugitify'));
        }

        $enabled   = $request->input('enabled');
        $logToFile = $request->input('log_to_file');
        $display   = $request->input('display');

        $isUpdate = $enabled !== null || $logToFile !== null || $display !== null;

        if ($isUpdate) {
            if (!is_writable($configPath)) {
                $buffer->addLog('error', __('wp-config.php is not writable.', 'plugitify'));
                $buffer->save();
                return Response::error(__('wp-config.php is not writable.', 'plugitify'));
            }
            $oldState = $this->readWpConfigDebugState($content);
            $content  = $this->updateWpConfigDebug($content, $enabled, $logToFile, $display);
            if ($content === null) {
                $buffer->addLog('error', __('Could not update debug settings.', 'plugitify'));
                $buffer->save();
                return Response::error(__('Could not update debug settings.', 'plugitify'));
            }
            if (file_put_contents($configPath, $content) === false) {
                $buffer->addLog('error', __('Could not write wp-config.php.', 'plugitify'));
                $buffer->save();
                return Response::error(__('Could not write wp-config.php.', 'plugitify'));
            }
            $newState = $this->readWpConfigDebugState($content);
            $buffer->addChange(
                'debug_settings_updated',
                wp_json_encode($oldState),
                wp_json_encode($newState),
                wp_json_encode(['path' => $configPath])
            );
        }

        $current = $this->readWpConfigDebugState($isUpdate ? $content : file_get_contents($configPath));
        $buffer->addLog('info', $isUpdate ? __('Debug settings updated.', 'plugitify') : __('Debug settings.', 'plugitify'), wp_json_encode($current));
        $buffer->save();
        return Response::success(
            $isUpdate ? __('Debug settings updated.', 'plugitify') : __('Debug settings.', 'plugitify'),
            $current
        );
    }

    /**
     * Update debug defines in wp-config content.
     *
     * @param string $content
     * @param mixed  $enabled
     * @param mixed  $logToFile
     * @param mixed  $display
     * @return string
     */
    private function updateWpConfigDebug(string $content, $enabled, $logToFile, $display): string
    {
        $getCurrent = function ($name) use ($content) {
            if (preg_match('/define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*(true|false)\s*\)/i', $content, $m)) {
                return strtolower($m[1]) === 'true';
            }
            return false;
        };

        $val = function ($requestVal, $current, $default) {
            if ($requestVal !== null) {
                return (bool) $requestVal;
            }
            return $current !== null ? $current : $default;
        };

        $debug = $val($enabled, $getCurrent('WP_DEBUG'), false);
        $log = $val($logToFile, $getCurrent('WP_DEBUG_LOG'), true);
        $disp = $val($display, $getCurrent('WP_DEBUG_DISPLAY'), false);

        $defines = ['WP_DEBUG', 'WP_DEBUG_LOG', 'WP_DEBUG_DISPLAY'];
        $values = [$debug, $log, $disp];
        $stop = "/* That's all, stop editing!";

        foreach ($defines as $i => $name) {
            $bool = $values[$i] ? 'true' : 'false';
            $replacement = "define( '" . $name . "', " . $bool . " );";
            $pattern = '/define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*(?:true|false)\s*\)\s*;/i';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content, 1);
            } else {
                $content = str_replace($stop, $replacement . "\n" . $stop, $content);
            }
        }

        return $content;
    }

    /**
     * Read current debug state from wp-config content.
     *
     * @param string|false $content
     * @return array{enabled: bool, log_to_file: bool, display: bool}
     */
    private function readWpConfigDebugState($content): array
    {
        $out = ['enabled' => false, 'log_to_file' => false, 'display' => false];
        if (!is_string($content)) {
            return $out;
        }
        foreach (['WP_DEBUG' => 'enabled', 'WP_DEBUG_LOG' => 'log_to_file', 'WP_DEBUG_DISPLAY' => 'display'] as $name => $key) {
            if (preg_match('/define\s*\(\s*[\'"]' . preg_quote($name, '/') . '[\'"]\s*,\s*(true|false)\s*\)/i', $content, $m)) {
                $out[$key] = strtolower($m[1]) === 'true';
            }
        }
        return $out;
    }

    /**
     * Read debug log file (default: wp-content/debug.log). Optional path in body to read another file under ABSPATH.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function readLog(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'log')) !== null) {
            return $r;
        }
        $pathInput = $request->str('path', '');
        $buffer    = $this->recordGeneralApi($request, 'general/log', __('Read log file', 'plugitify'), ['path' => $pathInput]);

        if ($pathInput === '') {
            $path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'debug.log';
        } else {
            $base     = rtrim(realpath(ABSPATH) ?: ABSPATH, '/\\');
            $combined = $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathInput), DIRECTORY_SEPARATOR);
            $resolved = realpath($combined);
            if ($resolved === false || !str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', $base))) {
                $buffer->addLog('error', __('Log file path is outside WordPress root.', 'plugitify'), wp_json_encode(['path' => $pathInput]));
                $buffer->save();
                return Response::error(__('Log file path is outside WordPress root.', 'plugitify'), ['path' => $pathInput]);
            }
            $path = $resolved;
        }
        if (!is_file($path) || !is_readable($path)) {
            $buffer->addLog('error', __('Log file not found or not readable.', 'plugitify'), wp_json_encode(['path' => $path]));
            $buffer->save();
            return Response::error(__('Log file not found or not readable.', 'plugitify'), ['path' => $path]);
        }
        $content = file_get_contents($path);
        if ($content === false) {
            $buffer->addLog('error', __('Could not read log file.', 'plugitify'), wp_json_encode(['path' => $path]));
            $buffer->save();
            return Response::error(__('Could not read log file.', 'plugitify'));
        }
        $buffer->addLog('info', __('Log file read.', 'plugitify'), wp_json_encode(['path' => $path]));
        $buffer->save();
        return Response::success(__('Log file read.', 'plugitify'), [
            'path'    => $path,
            'content' => $content,
        ]);
    }

    /**
     * Get site URL and home URL from options table.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function siteUrls(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'site-urls')) !== null) {
            return $r;
        }
        $buffer  = $this->recordGeneralApi($request, 'general/site-urls', __('Get site URLs', 'plugitify'));
        $siteurl = get_option('siteurl', '');
        $home    = get_option('home', '');
        $buffer->addLog('info', __('Site URLs returned.', 'plugitify'), wp_json_encode(['siteurl' => $siteurl, 'home' => $home]));
        $buffer->save();
        return Response::success('', [
            'siteurl' => $siteurl,
            'home'    => $home,
        ]);
    }

    /**
     * Create a new plugin with folder structure (classes, assets) and main file with headers.
     * Input: plugin_name (display name), folder_name (slug), version.
     * Creates under wp-content/plugins/{folder_name}/.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function createPlugin(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'create-plugin')) !== null) {
            return $r;
        }
        $pluginName = $request->str('plugin_name', '');
        $folderName = $request->str('folder_name', '');
        $version    = $request->str('version', '1.0.0');

        $buffer = $this->recordGeneralApi($request, 'general/create-plugin', __('Create plugin', 'plugitify'), [
            'plugin_name' => $pluginName,
            'folder_name' => $folderName,
            'version'     => $version,
        ]);

        if ($pluginName === '' || $folderName === '') {
            $buffer->addLog('error', __('plugin_name and folder_name are required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('plugin_name and folder_name are required.', 'plugitify'));
        }

        $slug = $this->sanitizeSlug($folderName);
        if ($slug === '') {
            $buffer->addLog('error', __('Invalid folder_name.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Invalid folder_name.', 'plugitify'));
        }

        $pluginsDir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins';
        $pluginPath = $pluginsDir . DIRECTORY_SEPARATOR . $slug;

        if (is_dir($pluginPath)) {
            $buffer->addLog('error', __('Plugin folder already exists.', 'plugitify'), wp_json_encode(['path' => $pluginPath]));
            $buffer->save();
            return Response::error(__('Plugin folder already exists.', 'plugitify'), ['path' => $pluginPath]);
        }

        $templateDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'plugin-template';
        if (!is_dir($templateDir)) {
            $buffer->addLog('error', __('Plugin template not found.', 'plugitify'), wp_json_encode(['path' => $templateDir]));
            $buffer->save();
            return Response::error(__('Plugin template not found.', 'plugitify'), ['path' => $templateDir]);
        }

        if (!$this->copyTemplateRecursive($templateDir, $pluginPath)) {
            $buffer->addLog('error', __('Could not copy plugin template.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Could not copy plugin template.', 'plugitify'));
        }

        $mainTemplatePath = $pluginPath . DIRECTORY_SEPARATOR . 'plugin-main.php';
        $mainFile         = $pluginPath . DIRECTORY_SEPARATOR . $slug . '.php';
        if (is_file($mainTemplatePath)) {
            rename($mainTemplatePath, $mainFile);
        }

        $replacements = $this->pluginTemplateReplacements($slug, $pluginName, $version);
        $this->replaceInPluginFiles($pluginPath, $replacements);

        $buffer->addChange('plugin_created', null, $pluginPath, wp_json_encode(['plugin_name' => $pluginName, 'folder' => $slug]));
        $buffer->addLog('info', __('Plugin created.', 'plugitify'), wp_json_encode(['path' => $pluginPath]));
        $buffer->save();
        return Response::success(__('Plugin created.', 'plugitify'), [
            'path'         => $pluginPath,
            'folder_name'  => $slug,
            'main_file'    => $slug . '.php',
        ]);
    }

    /**
     * Create a new theme with folder structure (style.css, functions.php, index.php, assets).
     * Input: theme_name (display name), folder_name (slug), version.
     * Creates under wp-content/themes/{folder_name}/.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function createTheme(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'create-theme')) !== null) {
            return $r;
        }
        $themeName  = $request->str('theme_name', '');
        $folderName = $request->str('folder_name', '');
        $version    = $request->str('version', '1.0.0');

        $buffer = $this->recordGeneralApi($request, 'general/create-theme', __('Create theme', 'plugitify'), [
            'theme_name'  => $themeName,
            'folder_name' => $folderName,
            'version'     => $version,
        ]);

        if ($themeName === '' || $folderName === '') {
            $buffer->addLog('error', __('theme_name and folder_name are required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('theme_name and folder_name are required.', 'plugitify'));
        }

        $slug = $this->sanitizeSlug($folderName);
        if ($slug === '') {
            $buffer->addLog('error', __('Invalid folder_name.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Invalid folder_name.', 'plugitify'));
        }

        $themesDir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'themes';
        $themePath = $themesDir . DIRECTORY_SEPARATOR . $slug;

        if (is_dir($themePath)) {
            $buffer->addLog('error', __('Theme folder already exists.', 'plugitify'), wp_json_encode(['path' => $themePath]));
            $buffer->save();
            return Response::error(__('Theme folder already exists.', 'plugitify'), ['path' => $themePath]);
        }

        $templateDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'theme-template';
        if (!is_dir($templateDir)) {
            $buffer->addLog('error', __('Theme template not found.', 'plugitify'), wp_json_encode(['path' => $templateDir]));
            $buffer->save();
            return Response::error(__('Theme template not found.', 'plugitify'), ['path' => $templateDir]);
        }

        if (!$this->copyTemplateRecursive($templateDir, $themePath)) {
            $buffer->addLog('error', __('Could not copy theme template.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Could not copy theme template.', 'plugitify'));
        }

        $replacements = $this->themeTemplateReplacements($slug, $themeName, $version);
        $this->replaceInPluginFiles($themePath, $replacements);

        $buffer->addChange('theme_created', null, $themePath, wp_json_encode(['theme_name' => $themeName, 'folder' => $slug]));
        $buffer->addLog('info', __('Theme created.', 'plugitify'), wp_json_encode(['path' => $themePath]));
        $buffer->save();
        return Response::success(__('Theme created.', 'plugitify'), [
            'path'        => $themePath,
            'folder_name' => $slug,
        ]);
    }

    /**
     * Delete a plugin by folder name (slug). Deactivates first if active.
     * Input: folder_name (slug under wp-content/plugins).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function deletePlugin(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'delete-plugin')) !== null) {
            return $r;
        }
        $folderName = $request->str('folder_name', '');

        $buffer = $this->recordGeneralApi($request, 'general/delete-plugin', __('Delete plugin', 'plugitify'), ['folder_name' => $folderName]);

        if ($folderName === '') {
            $buffer->addLog('error', __('folder_name is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('folder_name is required.', 'plugitify'));
        }

        $slug = $this->sanitizeSlug($folderName);
        if ($slug === '') {
            $buffer->addLog('error', __('Invalid folder_name.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Invalid folder_name.', 'plugitify'));
        }

        $pluginsDir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins';
        $pluginPath = $pluginsDir . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($pluginPath)) {
            $buffer->addLog('error', __('Plugin folder not found.', 'plugitify'), wp_json_encode(['path' => $pluginPath]));
            $buffer->save();
            return Response::error(__('Plugin folder not found.', 'plugitify'), ['path' => $pluginPath]);
        }

        $realPath = realpath($pluginPath);
        $realBase = realpath($pluginsDir);
        if ($realPath === false || $realBase === false || !str_starts_with(str_replace('\\', '/', $realPath . '/'), str_replace('\\', '/', $realBase . '/'))) {
            $buffer->addLog('error', __('Plugin path is invalid.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Plugin path is invalid.', 'plugitify'));
        }

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all   = get_plugins();
        $active = (array) get_option('active_plugins', []);
        foreach ($all as $pluginFile => $info) {
            if (str_starts_with($pluginFile, $slug . '/') && in_array($pluginFile, $active, true)) {
                if (function_exists('deactivate_plugins')) {
                    deactivate_plugins($pluginFile, true);
                }
            }
        }

        if (!$this->deleteDirectoryRecursive($pluginPath)) {
            $buffer->addLog('error', __('Could not delete plugin directory.', 'plugitify'), wp_json_encode(['path' => $pluginPath]));
            $buffer->save();
            return Response::error(__('Could not delete plugin directory.', 'plugitify'), ['path' => $pluginPath]);
        }

        $buffer->addChange('plugin_deleted', $pluginPath, null, wp_json_encode(['folder' => $slug]));
        $buffer->addLog('info', __('Plugin deleted.', 'plugitify'), wp_json_encode(['path' => $pluginPath]));
        $buffer->save();
        return Response::success(__('Plugin deleted.', 'plugitify'), ['path' => $pluginPath]);
    }

    /**
     * Delete a theme by folder name (slug). Fails if it is the active theme.
     * Input: folder_name (slug under wp-content/themes).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function deleteTheme(Request $request): array
    {
        if (($r = ToolsPolicy::getDisabledResponse('general', 'delete-theme')) !== null) {
            return $r;
        }
        $folderName = $request->str('folder_name', '');

        $buffer = $this->recordGeneralApi($request, 'general/delete-theme', __('Delete theme', 'plugitify'), ['folder_name' => $folderName]);

        if ($folderName === '') {
            $buffer->addLog('error', __('folder_name is required.', 'plugitify'));
            $buffer->save();
            return Response::error(__('folder_name is required.', 'plugitify'));
        }

        $slug = $this->sanitizeSlug($folderName);
        if ($slug === '') {
            $buffer->addLog('error', __('Invalid folder_name.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Invalid folder_name.', 'plugitify'));
        }

        $current = get_stylesheet();
        if ($slug === $current) {
            $buffer->addLog('error', __('Cannot delete the active theme. Switch to another theme first.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Cannot delete the active theme. Switch to another theme first.', 'plugitify'));
        }

        $themesDir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'themes';
        $themePath = $themesDir . DIRECTORY_SEPARATOR . $slug;

        if (!is_dir($themePath)) {
            $buffer->addLog('error', __('Theme folder not found.', 'plugitify'), wp_json_encode(['path' => $themePath]));
            $buffer->save();
            return Response::error(__('Theme folder not found.', 'plugitify'), ['path' => $themePath]);
        }

        $realPath = realpath($themePath);
        $realBase = realpath($themesDir);
        if ($realPath === false || $realBase === false || !str_starts_with(str_replace('\\', '/', $realPath . '/'), str_replace('\\', '/', $realBase . '/'))) {
            $buffer->addLog('error', __('Theme path is invalid.', 'plugitify'));
            $buffer->save();
            return Response::error(__('Theme path is invalid.', 'plugitify'));
        }

        if (!$this->deleteDirectoryRecursive($themePath)) {
            $buffer->addLog('error', __('Could not delete theme directory.', 'plugitify'), wp_json_encode(['path' => $themePath]));
            $buffer->save();
            return Response::error(__('Could not delete theme directory.', 'plugitify'), ['path' => $themePath]);
        }

        $buffer->addChange('theme_deleted', $themePath, null, wp_json_encode(['folder' => $slug]));
        $buffer->addLog('info', __('Theme deleted.', 'plugitify'), wp_json_encode(['path' => $themePath]));
        $buffer->save();
        return Response::success(__('Theme deleted.', 'plugitify'), ['path' => $themePath]);
    }

    /**
     * Copy template directory recursively to destination.
     *
     * @param string $source
     * @param string $dest
     * @return bool
     */
    private function copyTemplateRecursive(string $source, string $dest): bool
    {
        if (!is_dir($source)) {
            return false;
        }
        if (!is_dir($dest) && !mkdir($dest, 0755, true)) {
            return false;
        }
        $items = array_diff(scandir($source), ['.', '..']);
        foreach ($items as $item) {
            $srcPath = $source . DIRECTORY_SEPARATOR . $item;
            $dstPath = $dest . DIRECTORY_SEPARATOR . $item;
            if (is_dir($srcPath)) {
                if (!$this->copyTemplateRecursive($srcPath, $dstPath)) {
                    return false;
                }
            } else {
                if (!copy($srcPath, $dstPath)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Build replacement map for plugin template (prefix, headers, constants).
     * Order matters: replace longer strings first to avoid partial replacements.
     *
     * @param string $slug
     * @param string $pluginName
     * @param string $version
     * @return array<string, string>
     */
    private function pluginTemplateReplacements(string $slug, string $pluginName, string $version): array
    {
        $textDomain   = str_replace('-', '_', $slug);
        $constPrefix  = $this->constantName($slug);
        $classPrefix  = $this->classPrefix($slug);
        $funcPrefix   = $this->functionPrefix($slug);

        return [
            'SAMPLE_PLUGIN'   => $constPrefix,
            'Sample_Plugin'   => $classPrefix . 'Plugin',
            'Sample_'        => $classPrefix,
            'sample_plugin_' => $funcPrefix,
            'sample_plugin'  => rtrim($funcPrefix, '_'),
            'Sample Plugin'   => $pluginName,
            '1.0.0'          => $version,
            'sample-plugin'  => $textDomain,
        ];
    }

    /**
     * Build replacement map for theme template (prefix, headers, constants).
     * Order matters: replace longer strings first.
     *
     * @param string $slug
     * @param string $themeName
     * @param string $version
     * @return array<string, string>
     */
    private function themeTemplateReplacements(string $slug, string $themeName, string $version): array
    {
        $textDomain  = str_replace('-', '_', $slug);
        $constPrefix = $this->constantName($slug);
        $classPrefix = $this->classPrefix($slug);
        $funcPrefix  = $this->functionPrefix($slug);

        return [
            'SAMPLE_THEME'   => $constPrefix,
            'Sample_Theme'   => $classPrefix . 'Theme',
            'Sample_'        => $classPrefix,
            'sample_theme_' => $funcPrefix,
            'sample_theme'  => rtrim($funcPrefix, '_'),
            'Sample Theme'   => $themeName,
            '1.0.0'          => $version,
            'sample-theme'  => $textDomain,
        ];
    }

    /**
     * Apply replacements to all PHP, CSS, JS files under the plugin path.
     *
     * @param string $pluginPath
     * @param array<string, string> $replacements
     * @return void
     */
    private function replaceInPluginFiles(string $pluginPath, array $replacements): void
    {
        $extensions = ['php', 'css', 'js'];
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($pluginPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
        } catch (\Throwable $e) {
            return;
        }
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $ext  = strtolower($file->getExtension());
            if (!in_array($ext, $extensions, true)) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            foreach ($replacements as $search => $replace) {
                $content = str_replace($search, $replace, $content);
            }
            file_put_contents($path, $content);
        }
    }

    /**
     * Sanitize string to a safe folder/slug (lowercase, alphanumeric, dashes).
     *
     * @param string $name
     * @return string
     */
    private function sanitizeSlug(string $name): string
    {
        $slug = sanitize_title($name);
        $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        return $slug;
    }

    /**
     * Convert slug to UPPER_SNAKE_CASE constant prefix.
     *
     * @param string $slug
     * @return string
     */
    private function constantName(string $slug): string
    {
        $s = strtoupper(str_replace('-', '_', $slug));
        return preg_replace('/[^A-Z0-9_]/', '', $s) ?: 'PLUGIN';
    }

    /**
     * Convert slug to class prefix (PascalCase with underscores, e.g. My_Plugin_).
     *
     * @param string $slug
     * @return string
     */
    private function classPrefix(string $slug): string
    {
        $s = ucwords(str_replace('-', ' ', $slug));
        $s = str_replace(' ', '_', $s);
        $s = preg_replace('/[^A-Za-z0-9_]/', '', $s);
        return $s !== '' ? $s . '_' : 'Plugin_';
    }

    /**
     * Convert slug to function/variable prefix (lowercase with underscores, e.g. my_plugin_).
     *
     * @param string $slug
     * @return string
     */
    private function functionPrefix(string $slug): string
    {
        $s = str_replace('-', '_', $slug);
        $s = preg_replace('/[^a-z0-9_]/', '', strtolower($s));
        return $s !== '' ? $s . '_' : 'plugin_';
    }

    /**
     * Delete directory and all contents recursively. Caller must ensure path is inside allowed base.
     *
     * @param string $dir
     * @return bool
     */
    private function deleteDirectoryRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                if (!$this->deleteDirectoryRecursive($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }
        return @rmdir($dir);
    }
}
