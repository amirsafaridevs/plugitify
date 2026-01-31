<?php

namespace Plugifity\Contract\Abstract;

use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Contract\Interface\ServiceProviderInterface;

/**
 * Abstract Service Provider
 * 
 * Base implementation for service providers with common functionality
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface
{
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Whether the provider has been registered
     *
     * @var bool
     */
    protected bool $registered = false;

    /**
     * Whether the provider has been booted
     *
     * @var bool
     */
    protected bool $booted = false;

    /**
     * Register services
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void
    {
        $this->container = $container;
        
        if (!$this->registered) {
            $this->registerServices();
            $this->registered = true;
        }
    }

    /**
     * Boot services
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void
    {
        $this->container = $container;
        
        if (!$this->booted) {
            $this->bootServices();
            $this->booted = true;
        }
    }

    /**
     * Register services (to be implemented by child classes)
     *
     * @return void
     */
    abstract protected function registerServices(): void;

    /**
     * Boot services (to be implemented by child classes)
     *
     * @return void
     */
    protected function bootServices(): void
    {
        // Override in child classes if needed
    }

    /**
     * Check if provider has been registered
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return $this->registered;
    }

    /**
     * Check if provider has been booted
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
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
}

