<?php

namespace Plugifity\Service\Tools;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Http\Response;
use Plugifity\Helper\RecordBuffer;

/**
 * API Tools – General service (plugins, themes, debug, site URLs, log).
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
     * Boot the service – register general API routes.
     *
     * @return void
     */
    public function boot(): void
    {
        ApiRouter::post('general/plugins', [$this, 'pluginsList'])->name('api.tools.general.plugins');
        ApiRouter::post('general/themes', [$this, 'themesList'])->name('api.tools.general.themes');
        ApiRouter::post('general/debug', [$this, 'debugSettings'])->name('api.tools.general.debug');
        ApiRouter::post('general/log', [$this, 'readLog'])->name('api.tools.general.log');
        ApiRouter::post('general/site-urls', [$this, 'siteUrls'])->name('api.tools.general.site-urls');
    }

    /**
     * List all plugins with details (description, version, path, active status).
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: mixed}
     */
    public function pluginsList(Request $request): array
    {
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
}
