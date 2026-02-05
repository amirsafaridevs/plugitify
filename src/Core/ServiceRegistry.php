<?php

namespace Plugifity\Core;

use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Contract\Interface\ServiceProviderInterface as ContractServiceProviderInterface;
use Plugifity\Contract\Interface\ServiceRegistryInterface;

/**
 * Service Registry
 * 
 * Manages registration and booting of service providers
 */
class ServiceRegistry implements ServiceRegistryInterface
{
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Registered service providers
     *
     * @var array
     */
    private array $providers = [];

    /**
     * Booted service providers
     *
     * @var array
     */
    private array $booted = [];

    /**
     * Constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Register a service provider
     *
     * @param string|ContractServiceProviderInterface $provider
     * @return void
     * @throws \Exception
     */
    public function register($provider): void
    {
        
        // If it's a string, resolve it from container
        if (is_string($provider)) {
            if (!class_exists($provider)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message
                throw new \Exception("Service provider class [{$provider}] does not exist.");
            }

            $provider = $this->container->make($provider);
        }

        // Ensure it implements ServiceProvider interface
        if (!$provider instanceof ContractServiceProviderInterface) {
            throw new \Exception(
                "Service provider must implement " . ContractServiceProviderInterface::class
            );
        }

        // Register the provider
        $provider->register($this->container);

        $this->providers[] = $provider;

        // If already booted, boot this provider immediately
        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Register multiple service providers
     *
     * @param array $providers
     * @return void
     */
    public function registerProviders(array $providers): void
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Boot all registered service providers
     *
     * @return void
     */
    public function boot(): void
    {
        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = $this->providers;
    }

    /**
     * Boot a single service provider
     *
     * @param ContractServiceProviderInterface $provider
     * @return void
     */
    private function bootProvider(ContractServiceProviderInterface $provider): void
    {
        if (in_array($provider, $this->booted, true)) {
            return;
        }

        $provider->boot($this->container);
        $this->booted[] = $provider;
    }

    /**
     * Check if providers have been booted
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return !empty($this->booted);
    }

    /**
     * Get all registered providers
     *
     * @return array
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get all booted providers
     *
     * @return array
     */
    public function getBootedProviders(): array
    {
        return $this->booted;
    }
}

