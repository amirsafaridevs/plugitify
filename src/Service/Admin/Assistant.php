<?php

namespace Plugifity\Service\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Contract\Interface\ContainerInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Assistant extends AbstractService
{
    public const REST_NAMESPACE = 'agentify/v1';
    public const OPTION_SETTINGS = 'agentify_settings';

    public function boot(ContainerInterface $container): void
    {
        add_action( 'admin_menu', [ $this, 'addMenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
        add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );
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

        wp_localize_script( 'agentify-assistant', 'agentifyRest', [
            'restUrl' => rest_url( self::REST_NAMESPACE ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function registerRestRoutes(): void
    {
        register_rest_route( self::REST_NAMESPACE, '/settings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetSettings' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
        register_rest_route( self::REST_NAMESPACE, '/settings', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restSaveSettings' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'model'    => [ 'type' => 'string', 'required' => false ],
                'api_keys' => [
                    'type'       => 'object',
                    'required'   => false,
                    'properties' => [
                        'deepseek' => [ 'type' => 'string' ],
                        'chatgpt'  => [ 'type' => 'string' ],
                        'gemini'   => [ 'type' => 'string' ],
                        'claude'   => [ 'type' => 'string' ],
                    ],
                ],
            ],
        ] );
    }

    public function restGetSettings( WP_REST_Request $request ): WP_REST_Response
    {
        $data = get_option( self::OPTION_SETTINGS, [] );
        $data = wp_parse_args( $data, [
            'model'    => 'deepseek|deepseek-chat',
            'api_keys' => [
                'deepseek' => '',
                'chatgpt'  => '',
                'gemini'   => '',
                'claude'   => '',
            ],
        ] );
        return new WP_REST_Response( $data, 200 );
    }

    public function restSaveSettings( WP_REST_Request $request ): WP_REST_Response
    {
        $model    = $request->get_param( 'model' );
        $api_keys = $request->get_param( 'api_keys' );
        $current  = get_option( self::OPTION_SETTINGS, [] );
        $current  = wp_parse_args( $current, [
            'model'    => 'deepseek|deepseek-chat',
            'api_keys' => [ 'deepseek' => '', 'chatgpt' => '', 'gemini' => '', 'claude' => '' ],
        ] );
        if ( is_string( $model ) ) {
            $current['model'] = sanitize_text_field( $model );
        }
        if ( is_array( $api_keys ) ) {
            foreach ( [ 'deepseek', 'chatgpt', 'gemini', 'claude' ] as $key ) {
                if ( isset( $api_keys[ $key ] ) && is_string( $api_keys[ $key ] ) ) {
                    $current['api_keys'][ $key ] = sanitize_text_field( $api_keys[ $key ] );
                }
            }
        }
        update_option( self::OPTION_SETTINGS, $current );
        return new WP_REST_Response( [
            'success' => true,
            'message' => __( 'Settings saved.', 'plugifity' ),
        ], 200 );
    }

    public function renderPage(): void
    {
        $app = $this->getApplication();
        $app->view( 'Assistant/index' );
    }
}
