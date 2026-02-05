<?php
namespace Plugifity\App;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractSingleton;
use Plugifity\Core\Application;
use Plugifity\Provider\AdminServiceProvider;
use Plugifity\Provider\ExampleServiceProvider;
/**
 * App Class
 * 
 * Main application class for Easy Stock and Price Control plugin
 */
class App extends AbstractSingleton
{

    /**
     * Service registry instance
     *
     * @var 
     */
    protected  $providers = [];
    /**
     * Application instance
     *
     * @var Application
     */
    protected Application $application;

    /**
     * Get the singleton instance
     *
     * @return self
     */
    public function __construct() {
        $this->application = Application::get();
        $this->application->setProperty('basePath', plugin_dir_path(__FILE__));
        $this->application->setProperty('version', '0.0.1');
        $this->init();
    }

    /**
     * Initialize the application
     *
     * @return void
     */
    private function init(): void
    {
        if ( defined( 'PLUGITIFY_PLUGIN_FILE' ) ) {
            register_activation_hook( PLUGITIFY_PLUGIN_FILE, [ $this, 'runMigrations' ] );
        }
        
        // Load plugin text domain for translations
        add_action( 'plugins_loaded', [ $this, 'loadTextDomain' ] );
        
        $this->application->registerProvider(AdminServiceProvider::class);
        $this->application->boot();
    }

    /**
     * Load plugin text domain for translations
     *
     * @return void
     */
    public function loadTextDomain(): void
    {
        if ( defined( 'PLUGITIFY_PLUGIN_FILE' ) ) {
            // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for non-WordPress.org plugins
            load_plugin_textdomain(
                'plugitify',
                false,
                dirname( plugin_basename( PLUGITIFY_PLUGIN_FILE ) ) . '/languages'
            );
        }
    }

    /**
     * Run migrations (e.g. on plugin activation).
     */
    public function runMigrations(): void
    {
        $this->application->runMigrations();
    }
}
