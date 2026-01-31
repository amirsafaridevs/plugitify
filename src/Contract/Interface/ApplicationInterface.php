<?php

namespace Plugifity\Contract\Interface;

use Closure;

/**
 * Application Interface
 * 
 * Contract for application implementations
 */
interface ApplicationInterface
{
    /**
     * Get the container instance
     *
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface;

    /**
     * Get the service registry instance
     *
     * @return ServiceRegistryInterface
     */
    public function getRegistry(): ServiceRegistryInterface;

    /**
     * Register service providers
     *
     * @param array $providers
     * @return self
     */
    public function registerProviders(array $providers): self;

    /**
     * Register a single service provider
     *
     * @param string|ServiceProviderInterface $provider
     * @return self
     */
    public function registerProvider($provider): self;

    /**
     * Boot the application
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Resolve a class from the container
     *
     * @param string $abstract
     * @param array $parameters
     * @return object
     */
    public function make(string $abstract, array $parameters = []): object;

    /**
     * Bind a class or interface to a concrete implementation
     *
     * @param string $abstract
     * @param string|Closure|null $concrete
     * @param bool $singleton
     * @return self
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): self;

    /**
     * Bind a singleton
     *
     * @param string $abstract
     * @param string|Closure|null $concrete
     * @return self
     */
    public function singleton(string $abstract, $concrete = null): self;

    /**
     * Register an existing instance
     *
     * @param string $abstract
     * @param object $instance
     * @return self
     */
    public function instance(string $abstract, object $instance): self;

    /**
     * Get application version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Get base path
     *
     * @return string
     */
    public function getBasePath(): string;

    /**
     * Get path relative to base path
     *
     * @param string $path
     * @return string
     */
    public function path(string $path = ''): string;
}

