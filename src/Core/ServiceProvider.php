<?php

namespace Plugifity\Core;

use Plugifity\Contract\Interface\ServiceProviderInterface;

/**
 * Service Provider Interface
 * 
 * All service providers must implement this interface
 * 
 * @deprecated Use Plugifity\Contract\Interface\ServiceProviderInterface instead
 */
interface ServiceProvider extends ServiceProviderInterface
{
    /**
     * Register services to the container
     *
     * @param Container $container
     * @return void
     */
    public function register($container): void;

    /**
     * Boot services after registration
     *
     * @param Container $container
     * @return void
     */
    public function boot($container): void;
}

