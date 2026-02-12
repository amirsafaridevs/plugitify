<?php

namespace Plugifity\Contract\Interface;

/**
 * Service Interface
 *
 * Contract for service implementations.
 * Container is auto-injected when the service is resolved from the container (setContainer is called by Container::make).
 */
interface ServiceInterface
{
    /**
     * Boot the service (hooks, routes, etc.).
     * Use $this->getContainer() or $this->container when you need the container.
     *
     * @return void
     */
    public function boot(): void;
}