<?php
namespace Plugitify\muPlugin\Route;

use Plugitify\muPlugin\Core\RegisterRoute;
use Plugitify\muPlugin\Controller\AgentController;
use Plugitify\muPlugin\Controller\ChatController;

// ─── Chat shell + agent tools. Both MUST stay 'early': if an active
// plugin/theme fatals during normal boot, a 'full' (wp_loaded) route never
// runs and the agent cannot open (or keep repairing) the site. Early
// dispatch exits at muplugins_loaded — before plugins/theme load. Cookie
// auth + nonces still work because the router loads pluggable.php itself
// for 'admin' early routes. ───────────────────────────────────────────────
RegisterRoute::add('GET', '/chat/{slug}', ChatController::class, 'chat', [
	'boot'     => 'early',
	'auth'     => 'admin',
	'response' => 'html',
]);

RegisterRoute::add('POST', '/agent/{slug}/tool/{tool}', AgentController::class, 'tool', [
	'boot'     => 'early',
	'auth'     => 'admin',
	'response' => 'json',
]);
