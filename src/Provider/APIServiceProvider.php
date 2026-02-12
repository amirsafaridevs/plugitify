<?php

namespace Plugifity\Provider;

use Plugifity\Contract\Abstract\AbstractServiceProvider;
use Plugifity\Service\API\Main;
use Plugifity\Service\Tools\Query;
use Plugifity\Service\Tools\File;
use Plugifity\Service\Tools\Plugin;
use Plugifity\Service\Tools\Theme;
use Plugifity\Service\Tools\General;

use Plugifity\Repository\LogRepository;
use Plugifity\Repository\ErrorsRepository;
use Plugifity\Repository\ChangesRepository;

/**
 * Admin Service Provider
 *
 * Service provider for admin-related functionality (Assistant UI, Agent, Chat).
 */
class APIServiceProvider extends AbstractServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        $this->container->singleton( 'api.main', Main::class );
        $this->container->singleton( 'api.tools.query', Query::class );
        $this->container->singleton( 'api.tools.file', File::class );
        $this->container->singleton( 'api.tools.plugin', Plugin::class );
        $this->container->singleton( 'api.tools.theme', Theme::class );
        $this->container->singleton( 'api.tools.general', General::class );




       
    }

    /**
     * Boot services that need initialization (hooks, routes, etc.)
     *
     * @return void
     */
    protected function bootServices(): void
    {
        // Boot services (container is auto-injected when each is resolved)
        $main = $this->container->get( 'api.main' );
        $main->boot();
        $query = $this->container->get( 'api.tools.query' );
        $query->boot();
        $file = $this->container->get( 'api.tools.file' );
        $file->boot();
        $plugin = $this->container->get( 'api.tools.plugin' );
        $plugin->boot();
        $theme = $this->container->get( 'api.tools.theme' );
        $theme->boot();
        $general = $this->container->get( 'api.tools.general' );
        $general->boot();
      
    }
}

