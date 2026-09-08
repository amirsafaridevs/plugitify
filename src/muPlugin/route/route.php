<?php
namespace Plugitify\muPlugin\Route;

use Plugitify\muPlugin\Core\RegisterRoute;
use Plugitify\muPlugin\Controller\AgentController;
use Plugitify\muPlugin\Controller\ChatController;

// ─── Chat shell: left side is an iframe "browser" (the previewed page for
// the given slug), right side is the agent conversation. ─────────────────
RegisterRoute::add('GET', '/chat/{slug}', ChatController::class, 'chat', [
    'boot'     => 'full',
    'auth'     => 'admin',
    'response' => 'html',
]);

// ─── Agent tool calls. The agent runs in the browser and reaches the model
// itself; this is the only endpoint it needs from us, and every call is
// scoped to the {slug} plugin directory. 'full' boot because the tools use
// WP_Filesystem-era APIs, current_user_can(), and the plugin activation
// functions, none of which exist at muplugins_loaded. ────────────────────
RegisterRoute::add('POST', '/agent/{slug}/tool/{tool}', AgentController::class, 'tool', [
    'boot'     => 'full',
    'auth'     => 'admin',
    'response' => 'json',
]);
