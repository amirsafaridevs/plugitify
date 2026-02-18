<?php

namespace Plugifity\Provider;

use Plugifity\Contract\Abstract\AbstractServiceProvider;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Service\API\Main;
use Plugifity\Service\Tools\Query;
use Plugifity\Service\Tools\File;
use Plugifity\Service\Tools\Plugin;
use Plugifity\Service\Tools\Theme;
use Plugifity\Service\Tools\General;
//use Plugifity\Service\Tools\woocommerce;
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
        $this->container->singleton( 'api.tools.general', General::class );
        //$this->container->singleton( 'api.tools.woocommerce', woocommerce::class );




       
    }

    /**
     * Boot services that need initialization (hooks, routes, etc.)
     *
     * @return void
     */
    protected function bootServices(): void
    {
        ApiRouter::getInstance()->setPrefix( 'api' );
        ApiRouter::getInstance()->setNamespace( 'plugitify/v1' );

        // Boot services first so they register their routes
        $main = $this->container->get( 'api.main' );
        $main->boot();
        $query = $this->container->get( 'api.tools.query' );
        $query->boot();
        $file = $this->container->get( 'api.tools.file' );
        $file->boot();
        $general = $this->container->get( 'api.tools.general' );
        $general->boot();

        // Register REST routes with WordPress (after all routes are added)
        ApiRouter::getInstance()->boot();
    }
}

