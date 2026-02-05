<?php

namespace Plugifity\Provider;

use Plugifity\Contract\Abstract\AbstractServiceProvider;
use Plugifity\Repository\ChatRepository;
use Plugifity\Repository\MessageRepository;
use Plugifity\Service\Admin\Assistant;
use Plugifity\Service\Admin\ChatService;

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
        $this->container->singleton( ChatRepository::class, ChatRepository::class );
        $this->container->singleton( MessageRepository::class, MessageRepository::class );
        $this->container->singleton( ChatService::class, ChatService::class );
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

