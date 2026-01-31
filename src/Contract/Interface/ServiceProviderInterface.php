<?php

namespace Plugifity\Contract\Interface;

use Plugifity\Contract\Interface\ContainerInterface;

/**
 * Service Provider Interface
 * 
 * Contract for service provider implementations
 */
interface ServiceProviderInterface
{
    /**
     * Register services to the container
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void;

    /**
     * Boot services after registration
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void;
}

