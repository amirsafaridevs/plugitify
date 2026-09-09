<?php
namespace Plugitify\muPlugin\Controller;

use Plugitify\muPlugin\Core\HttpException;
use Plugitify\muPlugin\Core\PluginWorkspace;
use Plugitify\muPlugin\Core\View;
use Plugitify\Services\Admin\SettingsService;

class ChatController
{
    public function chat(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page view (no state change); access is already gated by the 'admin' auth route requirement.
        $slug = isset($_GET['slug']) ? sanitize_title(wp_unslash($_GET['slug'])) : '';

        if ($slug === '') {
            throw new HttpException(422, 'validation_error', [], 'Missing "slug"');
        }

        // Fail here rather than rendering a chat page whose every tool call
        // would 404 — the workspace constructor validates the slug and that
        // the plugin directory actually exists.
        new PluginWorkspace($slug);

        $iframeUrl = home_url('/');

        return View::render('chat', [
            'slug'        => $slug,
            'iframeUrl'   => $iframeUrl,
            'agentConfig' => $this->build_agent_config($slug, $iframeUrl),
        ]);
    }

    /**
     * Configuration handed to the browser-side agent.
     *
     * The API key is included because the agent calls the model directly from
     * the browser — that is the intended architecture, and it is why this page
     * is locked behind manage_options.
     *
     * @return array<string, string>
     */
    private function build_agent_config(string $slug, string $iframeUrl): array
    {
        return array_merge(
            SettingsService::agentConfig(),
            [
                'slug'       => $slug,
                'apiBase'    => home_url('/plugitify/v1'),
                'nonce'      => wp_create_nonce(AgentController::NONCE_ACTION),
                'locale'     => get_locale(),
                'siteUrl'    => home_url('/'),
                'previewUrl' => $iframeUrl,
            ]
        );
    }
}
