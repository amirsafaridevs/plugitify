<?php

namespace Plugifity\Core;

use Plugifity\Contract\Interface\ApplicationInterface;
use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Contract\Interface\MigrationInterface;
use Plugifity\Contract\Interface\ServiceProviderInterface;
use Plugifity\Contract\Interface\ServiceRegistryInterface;

/**
 * Application Class
 * 
 * Main application class that ties together Container and ServiceRegistry
 */
class Application implements ApplicationInterface
{
    /**
     * Singleton instance
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Service registry instance
     *
     * @var ServiceRegistryInterface
     */
    private ServiceRegistryInterface $registry;

    /**
     * Application version
     *
     * @var string
     */
    protected string $version = '1.0.0';

    /**
     * Application base path
     *
     * @var string
     */
    protected string $basePath = '';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->registry = new ServiceRegistry($this->container);

        // Bind container to its interface so services can be resolved by type (DI)
        $this->container->instance(ContainerInterface::class, $this->container);
        // Bind Application instance to container
        $this->container->instance(Application::class, $this);
        $this->container->instance('app', $this);
    }

    /**
     * Run on plugin activation (call from register_activation_hook in main plugin file).
     */
    public function pluginActivation(): void
    {
        $this->runMigrations();
    }

    /**
     * Run all migrations in the Migration folder.
     */
    public function runMigrations(): void
    {
        $migrationPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Migration';
        if (!is_dir($migrationPath)) {
            return;
        }

        $files = glob($migrationPath . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return;
        }

        sort($files);
        $namespace = 'Plugifity\\Migration\\';

        foreach ($files as $file) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $class = $namespace . $basename;

            if (!class_exists($class)) {
                require_once $file;
            }

            if (!class_exists($class) || !is_subclass_of($class, MigrationInterface::class)) {
                continue;
            }

            $migration = new $class();
            if (!$migration->shouldRun()) {
                continue;
            }

            $migration->up();
        }
    }
    /**
     * Get a configuration value
     *
     * @param string $key
     * @return mixed
     */
    public static function get(): mixed
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Set a configuration value
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setProperty(string $key, $value): self
    {
        $this->{$key} = $value;
        return $this;
    }

    /**
     * Get the container instance
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Get the service registry instance
     *
     * @return ServiceRegistryInterface
     */
    public function getRegistry(): ServiceRegistryInterface
    {
        return $this->registry;
    }

    /**
     * Register service providers
     *
     * @param array $providers
     * @return self
     */
    public function registerProviders(array $providers): self
    {
        $this->registry->registerProviders($providers);
        return $this;
    }

    /**
     * Register a single service provider
     *
     * @param string|ServiceProviderInterface $provider
     * @return self
     */
    public function registerProvider($provider): self
    {
        $this->registry->register($provider);
        return $this;
    }

    /**
     * Boot the application
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registry->boot();
    }

    /**
     * Resolve a class from the container
     *
     * @param string $abstract
     * @param array $parameters
     * @return object
     */
    public function make(string $abstract, array $parameters = []): object
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Bind a class or interface to a concrete implementation
     *
     * @param string $abstract
     * @param string|\Closure|null $concrete
     * @param bool $singleton
     * @return self
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): self
    {
        $this->container->bind($abstract, $concrete, $singleton);
        return $this;
    }

    /**
     * Bind a singleton
     *
     * @param string $abstract
     * @param string|\Closure|null $concrete
     * @return self
     */
    public function singleton(string $abstract, $concrete = null): self
    {
        $this->container->singleton($abstract, $concrete);
        return $this;
    }

    /**
     * Register an existing instance
     *
     * @param string $abstract
     * @param object $instance
     * @return self
     */
    public function instance(string $abstract, object $instance): self
    {
        $this->container->instance($abstract, $instance);
        return $this;
    }

    /**
     * Get application version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get base path
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Get path relative to base path
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }

    /**
     * Render a view file
     *
     * @param string $view View path relative to view directory (e.g., 'ChatPage/chat')
     * @param array $data Data to pass to the view
     * @return void
     */
    public function view(string $view, array $data = []): void
    {
        $viewBasePath = defined( 'PLUGIFITY_PLUGIN_FILE' )
            ? plugin_dir_path( PLUGIFITY_PLUGIN_FILE )
            : $this->basePath;
        $viewPath = $viewBasePath . 'view' . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $view ) . '.php';

        if ( ! is_readable( $viewPath ) ) {
            echo '<div class="wrap"><p>' . esc_html__('View not found: ', 'plugifity') . esc_html($view) . '</p></div>';
            return;
        }
        // Extract data to variables for use in view
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        include $viewPath;
    }


    /**
     * Enqueue a style with scope control
     *
     * @param string $handle Handle name
     * @param string $src Source path relative to assets directory
     * @param array $deps Dependencies
     * @param string|null $scope Scope: 'global', 'admin', 'frontend', 'admin_page:page_hook', 'frontend_page:page_id'
     * @return void
     */
    public function enqueueStyle(string $handle, string $src, array $deps = [], ?string $scope = 'global'): void
    {
        if (!$this->shouldEnqueueAsset($scope)) {
            return;
        }

        $url = $this->getAssetUrl($src);
        wp_enqueue_style($handle, $url, $deps, $this->version);
    }

    /**
     * Enqueue a script with scope control
     *
     * @param string $handle Handle name
     * @param string $src Source path relative to assets directory
     * @param array $deps Dependencies
     * @param bool $in_footer Load in footer
     * @param string|null $scope Scope: 'global', 'admin', 'frontend', 'admin_page:page_hook', 'frontend_page:page_id'
     * @return void
     */
    public function enqueueScript(string $handle, string $src, array $deps = [], bool $in_footer = true, ?string $scope = 'global'): void
    {
        if (!$this->shouldEnqueueAsset($scope)) {
            return;
        }

        $url = $this->getAssetUrl($src);
        wp_enqueue_script($handle, $url, $deps, $this->version, $in_footer);
    }

    /**
     * Enqueue external style (CDN, etc.)
     *
     * @param string $handle Handle name
     * @param string $url Full URL
     * @param array $deps Dependencies
     * @param string|null $scope Scope: 'global', 'admin', 'frontend', 'admin_page:page_hook'
     * @return void
     */
    public function enqueueExternalStyle(string $handle, string $url, array $deps = [], ?string $scope = 'global'): void
    {
        if (!$this->shouldEnqueueAsset($scope)) {
            return;
        }

        wp_enqueue_style($handle, $url, $deps, null);
    }

    /**
     * Get asset URL
     *
     * @param string $src Source path relative to assets directory
     * @return string
     */
    private function getAssetUrl(string $src): string
    {
        return plugins_url('assets/' . ltrim($src, '/'), PLUGIFITY_PLUGIN_FILE);
    }

    /**
     * Check if asset should be enqueued based on scope
     *
     * @param string|null $scope
     * @return bool
     */
    private function shouldEnqueueAsset(?string $scope): bool
    {
        if (!$scope || $scope === 'global') {
            return true;
        }

        if ($scope === 'admin') {
            return is_admin();
        }

        if ($scope === 'frontend') {
            return !is_admin();
        }

        // Handle specific page scopes
        if (strpos($scope, ':') !== false) {
            [$scopeType, $pageIdentifier] = explode(':', $scope, 2);
            
            if ($scopeType === 'admin_page') {
                return is_admin() && $this->isCurrentAdminPage($pageIdentifier);
            }

            if ($scopeType === 'frontend_page') {
                return !is_admin() && $this->isCurrentFrontendPage($pageIdentifier);
            }
        }

        return false;
    }

    /**
     * Check if current admin page matches identifier
     *
     * @param string $pageIdentifier Page hook or slug
     * @return bool
     */
    private function isCurrentAdminPage(string $pageIdentifier): bool
    {
        global $hook_suffix, $pagenow;
        
        // Check current hook
        if (isset($hook_suffix) && $hook_suffix === $pageIdentifier) {
            return true;
        }

        // Check page slug for top-level pages
        if (isset($_GET['page']) && $_GET['page'] === $pageIdentifier) {
            return true;
        }

        // Check pagenow
        if ($pagenow === $pageIdentifier) {
            return true;
        }

        return false;
    }

    /**
     * Check if current frontend page matches identifier
     *
     * @param string $pageIdentifier Page ID, slug, or template
     * @return bool
     */
    private function isCurrentFrontendPage(string $pageIdentifier): bool
    {
        if (is_admin()) {
            return false;
        }

        // Check page ID
        if (is_numeric($pageIdentifier) && is_page((int)$pageIdentifier)) {
            return true;
        }

        // Check page slug
        if (is_page($pageIdentifier)) {
            return true;
        }

        // Check template
        if (is_page_template($pageIdentifier)) {
            return true;
        }

        return false;
    }
}

