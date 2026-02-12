<?php

namespace Plugifity\Contract\Abstract;

use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Contract\Interface\ServiceInterface;
use Plugifity\Core\Application;

abstract class AbstractService implements ServiceInterface
{
    /**
     * Container instance (auto-injected when service is resolved from the container).
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Boot the service. Override in subclass.
     * Container is already available via $this->container or $this->getContainer().
     *
     * @return void
     */
    public function boot(): void
    {
    }

    /**
     * Get the application instance
     *
     * @return App
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
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
     * Get the application instance
     *
     * @return Application
     */
    protected function getApplication(): Application
    {
        return $this->container->get('app');
    }
}