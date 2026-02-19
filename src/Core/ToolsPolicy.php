<?php

namespace Plugifity\Core;

use Plugifity\Core\Http\Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Policy checks for API tools (e.g. enabled/disabled per endpoint, tools API token).
 * Use inside tool handlers to return a standard response when a check fails.
 */
class ToolsPolicy
{
    /** Header name for tools API token (alternative to Authorization: Bearer). */
    public const TOOLS_TOKEN_HEADER = 'X-Tools-Api-Token';

    /**
     * If the request token is missing or does not match the stored tools API token, return the error response;
     * otherwise null. Call this for every tool route (e.g. from the router) before running the handler.
     * Token is read from: Authorization: Bearer &lt;token&gt; or header X-Tools-Api-Token (see TOOLS_TOKEN_HEADER).
     * Return format is the same as Response::error(): array{success: bool, message: string, data: mixed}.
     *
     * @param string|null $tokenFromRequest Token from request header (e.g. Request::bearerToken() or Request::header(self::TOOLS_TOKEN_HEADER)).
     * @return array{success: bool, message: string, data: mixed}|null Error response array (same as Response::error()) or null if token is valid.
     */
    public static function getTokenMismatchResponse(?string $tokenFromRequest): ?array
    {
        $stored = Settings::get('tools_api_token', '');
        $stored = is_string($stored) ? trim($stored) : '';

        if ($stored === '') {
            return Response::error(
                __('Tools API token is not configured. Please set it in Plugifity → Licence.', 'plugitify')
            );
        }

        $provided = $tokenFromRequest !== null ? trim($tokenFromRequest) : '';
        if ($provided === '' || !hash_equals($stored, $provided)) {
            return Response::error(
                __('Invalid or missing tools API token.', 'plugitify')
            );
        }

        return null;
    }

    /**
     * Check if a tool endpoint is enabled in plugin settings.
     *
     * @param string $tool    Tool slug (e.g. 'query', 'file', 'general').
     * @param string $endpoint Endpoint slug (e.g. 'read', 'plugins').
     * @return bool True if enabled (or not set), false if explicitly disabled.
     */
    public static function isEndpointEnabled(string $tool, string $endpoint): bool
    {
        $enabled = Settings::get('tools_enabled', []);
        $toolOpt = $enabled[$tool] ?? null;
        if (!is_array($toolOpt)) {
            return true;
        }
        return !isset($toolOpt[$endpoint]) || !empty($toolOpt[$endpoint]);
    }

    /**
     * If the endpoint is disabled, return the standard error response array; otherwise null.
     * Use at the start of a tool handler: if ($r = ToolsPolicy::getDisabledResponse('general', 'plugins')) return $r;
     *
     * @param string $tool    Tool slug.
     * @param string $endpoint Endpoint slug.
     * @return array|null Response array (success, message, data) to return, or null if enabled.
     */
    public static function getDisabledResponse(string $tool, string $endpoint): ?array
    {
        if (self::isEndpointEnabled($tool, $endpoint)) {
            return null;
        }
        return Response::error(
            __('The WordPress site admin has disabled this tool from the Plugifity plugin settings; it is not available for use.', 'plugitify')
        );
    }

    /**
     * If the given path is inside an active plugin or the active (or parent) theme, return the standard error response;
     * otherwise null. Use in file-edit tools to block editing active plugin/theme files.
     *
     * @param string $resolvedPath Resolved absolute path (file or directory, may not exist yet).
     * @return array|null Response array to return, or null if editing is allowed.
     */
    public static function getActivePluginOrThemeEditDisabledResponse(string $resolvedPath): ?array
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolvedPath), DIRECTORY_SEPARATOR);
        if ($path === '') {
            return null;
        }

        // Block editing the Plugitify plugin itself (this plugin).
        $plugitifyRoot = @realpath(dirname(__DIR__, 2));
        if ($plugitifyRoot !== false && self::pathIsInside($path, $plugitifyRoot)) {
            return Response::error(
                __('Editing Plugitify plugin files is not allowed.', 'plugitify')
            );
        }

        $activePlugins = get_option('active_plugins', []);
        if (is_array($activePlugins)) {
            $pluginDirBase = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, WP_PLUGIN_DIR), DIRECTORY_SEPARATOR);
            foreach ($activePlugins as $plugin) {
                $pluginDir = $pluginDirBase . DIRECTORY_SEPARATOR . dirname($plugin);
                $pluginDirReal = @realpath($pluginDir);
                if ($pluginDirReal !== false && self::pathIsInside($path, $pluginDirReal)) {
                    return Response::error(
                        __('Editing files in the active plugin or theme is not allowed. Please deactivate the plugin or switch the theme first.', 'plugitify')
                    );
                }
            }
        }

        $themeRoot = get_theme_root();
        $themeRootReal = @realpath($themeRoot);
        if ($themeRootReal !== false) {
            $stylesheet = get_stylesheet();
            $template   = get_template();
            $themeDir   = $themeRootReal . DIRECTORY_SEPARATOR . $stylesheet;
            $themeDirReal = @realpath($themeDir);
            if ($themeDirReal !== false && self::pathIsInside($path, $themeDirReal)) {
                return Response::error(
                    __('Editing files in the active plugin or theme is not allowed. Please deactivate the plugin or switch the theme first.', 'plugitify')
                );
            }
            if ($stylesheet !== $template) {
                $parentDir = $themeRootReal . DIRECTORY_SEPARATOR . $template;
                $parentDirReal = @realpath($parentDir);
                if ($parentDirReal !== false && self::pathIsInside($path, $parentDirReal)) {
                    return Response::error(
                        __('Editing files in the active plugin or theme is not allowed. Please deactivate the plugin or switch the theme first.', 'plugitify')
                    );
                }
            }
        }

        return null;
    }

    /**
     * Check if path is inside base. Windows: case-insensitive. Linux: case-sensitive.
     *
     * @param string $path Normalized path (same separators as base).
     * @param string $base Base directory (realpath recommended).
     * @return bool
     */
    private static function pathIsInside(string $path, string $base): bool
    {
        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $baseWithSep = $base . DIRECTORY_SEPARATOR;
        if (DIRECTORY_SEPARATOR === '\\') {
            return str_starts_with(strtolower($path), strtolower($baseWithSep))
                || strtolower($path) === strtolower($base);
        }
        return str_starts_with($path, $baseWithSep) || $path === $base;
    }
}
