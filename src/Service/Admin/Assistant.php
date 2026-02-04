<?php

namespace Plugifity\Service\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Contract\Interface\ContainerInterface;

class Assistant extends AbstractService
{
    public function boot(ContainerInterface $container): void
    {
        add_action( 'admin_menu', [ $this, 'addMenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
    }

    public function addMenu(): void
    {
        add_menu_page(
            esc_html__( 'Agentify', 'plugifity' ),
            esc_html__( 'Agentify', 'plugifity' ),
            'manage_options',
            'agentify',
            [ $this, 'renderPage' ],
            'dashicons-admin-plugins',
            3
        );
    }

    public function enqueueAdminAssets( string $hook ): void
    {
        if ( $hook !== 'toplevel_page_agentify' ) {
            return;
        }

        $app = $this->getApplication();

        // Load Material Symbols (local)
        $app->enqueueStyle(
            'material-symbols-outlined',
            'admin/fonts/material-symbols-outlined.css',
            []
        );

        // Load Assistant styles
        $app->enqueueStyle(
            'agentify-assistant',
            'admin/Assistant/style.css',
            [ 'material-symbols-outlined' ]
        );

        // Load Assistant JavaScript
        $app->enqueueScript(
            'agentify-assistant',
            'admin/Assistant/app.js',
            [],
            true
        );
    }

    public function renderPage(): void
    {
        $app = $this->getApplication();
        $app->view( 'Assistant/index' );
    }
}
