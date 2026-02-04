<?php

namespace Plugifity\Provider;

use Plugifity\Contract\Abstract\AbstractServiceProvider;
use Plugifity\Repository\ChatRepository;
use Plugifity\Repository\ErrorRepository;
use Plugifity\Repository\MessageRepository;
use Plugifity\Service\Admin\Assistant;

/**
 * Admin Service Provider
 *
 * Service provider for admin-related functionality (Assistant UI, Agent, Chat).
 */
class AdminServiceProvider extends AbstractServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Services
        $this->container->singleton( 'admin.assistant', Assistant::class );
       
    }

    /**
     * Boot services that need initialization (hooks, routes, etc.)
     *
     * @return void
     */
    protected function bootServices(): void
    {
        // Boot Assistant: registers admin menu, assets, and REST routes
        $assistant = $this->container->get( 'admin.assistant' );
        $assistant->boot( $this->container );

        // Note: Other services (Agent, ChatService, Repositories) do not need boot;
        // they are resolved on-demand when used.
    }
}

