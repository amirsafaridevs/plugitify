<?php
namespace Plugifity\App;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractSingleton;
use Plugifity\Core\Application;
use Plugifity\Provider\AdminServiceProvider;
use Plugifity\Provider\APIServiceProvider;
/**
 * App Class
 * 
 * Main application class for Easy Stock and Price Control plugin
 */
class App extends AbstractSingleton
{
    /** @var static|null */
    protected static ?self $instance = null;

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
        $this->application->setProperty('basePath', plugin_dir_path(PLUGITIFY_PLUGIN_FILE));
        $this->application->setProperty('version', '0.0.1');
        $this->application->setProperty('prefix', 'plugifity');
        $this->application->setProperty('textdomain', 'plugifity');
        $this->application->setProperty('migration_folder', $this->application->path('src' . DIRECTORY_SEPARATOR . 'Migration'));
        $this->application->setProperty('backend_main_address', 'http://127.0.0.1:8000/');
        $this->init();
    }

    /**
     * Initialize the application
     *
     * @return void
     */
    private function init(): void
    {
       
        $this->application->registerProvider(AdminServiceProvider::class);
        $this->application->registerProvider(APIServiceProvider::class);
        $this->application->boot();
    }

    /**
     * Run migrations (e.g. on plugin activation).
     */
    public function runMigrations(): void
    {
        $this->application->runMigrations();
    }
}
