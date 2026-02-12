<?php

namespace Plugifity\Provider;

use Plugifity\Contract\Abstract\AbstractServiceProvider;
use Plugifity\Service\Admin\Dashboard;
use Plugifity\Service\Admin\Settings;
use Plugifity\Service\Admin\Log;
use Plugifity\Service\Admin\Errors;
use Plugifity\Service\Admin\Changes;
use Plugifity\Helper\RecordBuffer;
use Plugifity\Repository\LogRepository;
use Plugifity\Repository\ErrorsRepository;
use Plugifity\Repository\ChangesRepository;
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
        $this->container->singleton( 'admin.dashboard', Dashboard::class );
        $this->container->singleton( 'admin.settings', Settings::class );
        $this->container->singleton( 'admin.log', Log::class )->bind( LogRepository::class );
        $this->container->singleton( 'admin.errors', Errors::class )->bind( ErrorsRepository::class );
        $this->container->singleton( 'admin.changes', Changes::class )->bind( ChangesRepository::class );
        $this->container->instance( RecordBuffer::class, RecordBuffer::get() );
    }

    /**
     * Boot services that need initialization (hooks, routes, etc.)
     *
     * @return void
     */
    protected function bootServices(): void
    {
        // Boot services (container is auto-injected when each is resolved)
        $dashboard = $this->container->get( 'admin.dashboard' );
        $dashboard->boot();
        $settings = $this->container->get( 'admin.settings' );
        $settings->boot();
        $log = $this->container->get( 'admin.log' );
        $log->boot();
        $errors = $this->container->get( 'admin.errors' );
        $errors->boot();
        $changes = $this->container->get( 'admin.changes' );
        $changes->boot();

      
    }
}

