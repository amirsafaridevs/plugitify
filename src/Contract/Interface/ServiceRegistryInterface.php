<?php

namespace Plugifity\Contract\Interface;

/**
 * Service Registry Interface
 * 
 * Contract for service registry implementations
 */
interface ServiceRegistryInterface
{
    /**
     * Register a service provider
     *
     * @param string|ServiceProviderInterface $provider
     * @return void
     * @throws \Exception
     */
    public function register($provider): void;

    /**
     * Register multiple service providers
     *
     * @param array $providers
     * @return void
     */
    public function registerProviders(array $providers): void;

    /**
     * Boot all registered service providers
     *
     * @return void
     */
    public function boot(): void;

    /**
     * Check if providers have been booted
     *
     * @return bool
     */
    public function isBooted(): bool;

    /**
     * Get all registered providers
     *
     * @return array
     */
    public function getProviders(): array;

    /**
     * Get all booted providers
     *
     * @return array
     */
    public function getBootedProviders(): array;
}

