<?php
/**
 * Plugin Name:       پلاگیتی فای | ساخت افزونه با هوش مصنوعی
 * Description:       ساخت و مدیریت افزونه‌های وردپرس با کمک هوش مصنوعی.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Amir Safari
 * Text Domain:       plugitify
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PLUGITIFY_VERSION', '1.0.0' );
define( 'PLUGITIFY_FILE', __FILE__ );
define( 'PLUGITIFY_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLUGITIFY_URL', plugin_dir_url( __FILE__ ) );

// AI provider settings — override these in wp-config.php (before this file
// loads) instead of editing them here, so API keys never end up in git.
//
// The agent runs in the admin's browser and calls the endpoint directly, so
// PLUGITIFY_AI_ENDPOINT must be a host that permits browser (CORS) requests.
// api.openai.com does NOT: it rejects the preflight for authenticated POSTs,
// so point this at an OpenAI-compatible gateway (LiteLLM, OpenRouter, …) that
// sends Access-Control-Allow-Origin and forwards to the real provider.
if ( ! defined( 'PLUGITIFY_AI_PROVIDER' ) ) {
	define( 'PLUGITIFY_AI_PROVIDER', 'openai' );
}
if ( ! defined( 'PLUGITIFY_AI_MODEL' ) ) {
	define( 'PLUGITIFY_AI_MODEL', 'gpt-5.6-luna' );
}
if ( ! defined( 'PLUGITIFY_AI_ENDPOINT' ) ) {
	define( 'PLUGITIFY_AI_ENDPOINT', 'https://api.openai.com/v1' );
}
// Wire format: 'responses' or 'chat_completions'.
//
// Reasoning models reject function tools on /v1/chat/completions unless
// reasoning is switched off entirely ("Function tools with reasoning_effort are
// not supported ... use /v1/responses or set reasoning_effort to 'none'").
// Since this agent is built around tool use, 'responses' is the default; on a
// gateway that only speaks Chat Completions, set 'chat_completions' here and
// PLUGITIFY_AI_REASONING_EFFORT to 'none' below.
if ( ! defined( 'PLUGITIFY_AI_API_STYLE' ) ) {
	define( 'PLUGITIFY_AI_API_STYLE', 'responses' );
}

// How hard the model thinks before acting: none, minimal, low, medium, high,
// xhigh, max. Anything above 'none' requires PLUGITIFY_AI_API_STYLE
// 'responses' when the model is a reasoning model.
if ( ! defined( 'PLUGITIFY_AI_REASONING_EFFORT' ) ) {
	define( 'PLUGITIFY_AI_REASONING_EFFORT', 'medium' );
}
if ( ! defined( 'PLUGITIFY_AI_API_KEY' ) ) {
	define( 'PLUGITIFY_AI_API_KEY', '' );
}

require_once PLUGITIFY_PATH . 'vendor/autoload.php';

\Plugitify\App\App::getInstance();
