<?php

namespace Plugifity\Core;

use Plugifity\Contract\Interface\ApplicationInterface;
use Plugifity\Contract\Interface\ContainerInterface;
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

        // Bind Application instance to container
        $this->container->instance(Application::class, $this);
        $this->container->instance('app', $this);
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
}

