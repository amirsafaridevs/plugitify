<?php
namespace Plugitify\muPlugin\Controller;

use Plugitify\muPlugin\Core\AgentTools;
use Plugitify\muPlugin\Core\HttpException;
use Plugitify\muPlugin\Core\PluginWorkspace;

/**
 * HTTP surface for the browser-side agent's tools.
 *
 * The agent itself runs in the user's browser and talks to the model directly;
 * the only thing it needs from the server is a way to touch the filesystem.
 * Every tool call lands here as POST /plugitify/v1/agent/{slug}/tool/{tool}.
 *
 * Three gates stand in front of the tools:
 *  1. the route's 'admin' auth (manage_options),
 *  2. a nonce, because this is a cookie-authenticated state-changing endpoint
 *     and capability alone would leave it open to CSRF,
 *  3. PluginWorkspace, which pins every path to the {slug} plugin directory.
 */
class AgentController
{
    public const NONCE_ACTION = 'plugitify_agent';

    /** Tool name -> AgentTools method. Anything not listed here is not callable. */
    private const TOOLS = [
        'workspace_info'   => 'workspace_info',
        'site_extensions'  => 'site_extensions',
        'list_files'       => 'list_files',
        'read_file'        => 'read_file',
        'search_files'     => 'search_files',
        'glob_files'       => 'glob_files',
        'write_file'       => 'write_file',
        'edit_file'        => 'edit_file',
        'delete_file'      => 'delete_file',
        'create_directory' => 'create_directory',
        'delete_directory' => 'delete_directory',
        'move_path'        => 'move_path',
        'php_lint'         => 'php_lint',
        'plugin_control'   => 'plugin_control',
        'debug_control'    => 'debug_control',
        'read_debug_log'   => 'read_debug_log',
    ];

    /**
     * @return array{output:string, meta:array<string, mixed>}
     */
    public function tool(): array
    {
        $this->verify_nonce();

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_nonce() ran on the line above.
        $slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_nonce() ran above; sanitize_key() constrains this to the TOOLS whitelist lookup below.
        $tool = isset($_GET['tool']) ? sanitize_key(wp_unslash($_GET['tool'])) : '';

        if (!isset(self::TOOLS[$tool])) {
            throw new HttpException(
                404,
                'unknown_tool',
                [],
                'Unknown tool: ' . $tool . '. Available: ' . implode(', ', array_keys(self::TOOLS))
            );
        }

        $tools  = new AgentTools(new PluginWorkspace($slug));
        $method = self::TOOLS[$tool];

        return $tools->$method($this->read_json_body());
    }

    /**
     * The nonce travels in a header rather than the body so it is verified
     * before any body parsing happens.
     */
    private function verify_nonce(): void
    {
        $nonce = isset($_SERVER['HTTP_X_PLUGITIFY_NONCE'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_PLUGITIFY_NONCE']))
            : '';

        if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            throw new HttpException(
                403,
                'invalid_nonce',
                [],
                'Invalid or expired session token. Reload the chat page to get a fresh one.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function read_json_body(): array
    {
        $raw = file_get_contents('php://input');

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new HttpException(422, 'invalid_json', [], 'Request body must be a JSON object.');
        }

        return $decoded;
    }
}
