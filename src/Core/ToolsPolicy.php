<?php

namespace Plugifity\Core;

use Plugifity\Core\Http\Response;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Policy checks for API tools (e.g. enabled/disabled per endpoint).
 * Use inside tool handlers to return a standard response when a check fails.
 */
class ToolsPolicy
{
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
}
