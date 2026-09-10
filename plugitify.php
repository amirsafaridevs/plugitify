<?php
/**
 * Plugin Name:       پلاگیتی فای | ساخت افزونه با هوش مصنوعی
 * Description:       ساخت و مدیریت افزونه‌های وردپرس با کمک هوش مصنوعی.
 * Version:           1.0.7
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

define( 'PLUGITIFY_VERSION', '1.0.7' );
define( 'PLUGITIFY_FILE', __FILE__ );
define( 'PLUGITIFY_PATH', plugin_dir_path( __FILE__ ) );
define( 'PLUGITIFY_URL', plugin_dir_url( __FILE__ ) );

require_once PLUGITIFY_PATH . 'vendor/autoload.php';

\Plugitify\App\App::getInstance();
