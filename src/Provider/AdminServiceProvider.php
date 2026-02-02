<?php

namespace Plugifity\Provider;

use Plugifity\Contract\Abstract\AbstractServiceProvider;
use Plugifity\Repository\ChatRepository;
use Plugifity\Repository\ErrorRepository;
use Plugifity\Repository\MessageRepository;
use Plugifity\Service\Admin\Agent\PlugitifyAgent;
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
        // Services
        $this->container->singleton( 'admin.assistant', Assistant::class );
        // Agent: NOT singleton - rebuild each request so provider() reads fresh Settings
        $this->container->bind( 'admin.agent', PlugitifyAgent::class );
        $this->container->singleton( 'admin.chat', ChatService::class );

        // Repositories
        $this->container->singleton( 'chat.repository', ChatRepository::class );
        $this->container->singleton( 'message.repository', MessageRepository::class );
        $this->container->singleton( 'error.repository', ErrorRepository::class );

        // Aliases: Resolve by type (Container will inject these when building services by class type-hint)
        $this->container->alias( ChatRepository::class, 'chat.repository' );
        $this->container->alias( MessageRepository::class, 'message.repository' );
        $this->container->alias( ErrorRepository::class, 'error.repository' );
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

