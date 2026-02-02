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
    public const REST_NAMESPACE = 'plugifity/v1';

    public function boot(ContainerInterface $container): void
    {
        add_action( 'admin_menu', [ $this, 'addMenu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
        add_action( 'rest_api_init', [ $this, 'registerSettingsRestRoutes' ] );
        add_action( 'rest_api_init', [ $this, 'registerChatRestRoutes' ] );
    }

    public function registerChatRestRoutes(): void
    {
        register_rest_route( self::REST_NAMESPACE, '/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restChat' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => [
                'message' => [
                    'type'     => 'string',
                    'required' => true,
                ],
                'chat_id' => [
                    'type'     => 'integer',
                    'required' => false,
                ],
            ],
        ] );
    }

    /**
     * REST: POST chat — send user message, run agent, return assistant reply.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restChat( WP_REST_Request $request ): WP_REST_Response
    {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = [];
        }
        $message = isset( $body['message'] ) && is_string( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '';
        $chatId  = isset( $body['chat_id'] ) ? absint( $body['chat_id'] ) : null;
        if ( $chatId === 0 ) {
            $chatId = null;
        }

        if ( $message === '' ) {
            return new WP_REST_Response( [
                'success' => false,
                'error'   => [ 'message' => __( 'Message is required.', 'plugifity' ) ],
            ], 400 );
        }

        $chatService = $this->getContainer()->get( 'admin.chat' );
        $result      = $chatService->sendMessage( $message, $chatId );

        if ( ! empty( $result['success'] ) ) {
            return new WP_REST_Response( $result, 200 );
        }

        return new WP_REST_Response( $result, 500 );
    }

    public function registerSettingsRestRoutes(): void
    {
        register_rest_route( self::REST_NAMESPACE, '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'restGetSettings' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'restSaveSettings' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
                'args'                => [
                    'model'   => [
                        'type'     => 'string',
                        'required' => false,
                        'default'  => Settings::DEFAULT_MODEL,
                    ],
                    'apiKeys' => [
                        'type'     => 'object',
                        'required' => false,
                        'default'  => [],
                        'properties' => [
                            'deepseek' => [ 'type' => 'string' ],
                            'chatgpt'  => [ 'type' => 'string' ],
                            'gemini'   => [ 'type' => 'string' ],
                            'claude'   => [ 'type' => 'string' ],
                        ],
                    ],
                    'allowDbWrites' => [
                        'type'     => 'boolean',
                        'required' => false,
                        'default'  => false,
                    ],
                ],
            ],
        ] );
    }

    /**
     * REST: GET settings (model + per-provider API keys).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restGetSettings( WP_REST_Request $request ): WP_REST_Response
    {
        $data = Settings::get();
        return new WP_REST_Response( $data, 200 );
    }

    /**
     * REST: POST settings (model + per-provider API keys; each key stored in its own option).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restSaveSettings( WP_REST_Request $request ): WP_REST_Response
    {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            $body = [];
        }
        $model   = isset( $body['model'] ) && is_string( $body['model'] ) ? sanitize_text_field( $body['model'] ) : Settings::DEFAULT_MODEL;
        $apiKeys = isset( $body['apiKeys'] ) && is_array( $body['apiKeys'] ) ? $body['apiKeys'] : [];
        $allowDbWrites = isset( $body['allowDbWrites'] ) && $body['allowDbWrites'] === true;

        Settings::save( $model, $apiKeys, $allowDbWrites );
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function addMenu(): void
    {
        add_menu_page(
            esc_html__( 'Assistant', 'plugifity' ),
            esc_html__( 'Assistant', 'plugifity' ),
            'manage_options',
            'plugitify-assistant',
            [ $this, 'renderPage' ],
            'dashicons-superhero-alt',
            3
        );

        add_submenu_page(
            'plugitify-assistant',
            esc_html__( 'Chat', 'plugifity' ),
            esc_html__( 'Chat', 'plugifity' ),
            'manage_options',
            'plugitify-assistant',
            [ $this, 'renderPage' ]
        );

        add_submenu_page(
            'plugitify-assistant',
            esc_html__( 'Error Logs', 'plugifity' ),
            esc_html__( 'Error Logs', 'plugifity' ),
            'manage_options',
            'plugitify-error-logs',
            [ $this, 'renderErrorLogsPage' ]
        );
    }

    public function enqueueAdminAssets( string $hook ): void
    {
        $app = $this->getApplication();

        // Load menu CSS globally in admin
        $app->enqueueStyle( 'plugitify-admin-menu', 'admin/menu.css', [], 'admin' );

        // Load Material Symbols for both Chat and Error Logs pages
        $app->enqueueExternalStyle(
            'material-symbols-outlined',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0',
            [],
            'admin_page:toplevel_page_plugitify-assistant,admin_page:assistant_page_plugitify-error-logs'
        );

        // Load ChatPage assets only on Chat page
        $app->enqueueStyle(
            'plugitify-chat',
            'admin/ChatPage/style.css',
            [ 'material-symbols-outlined' ],
            'admin_page:toplevel_page_plugitify-assistant'
        );
        $app->enqueueScript(
            'plugitify-chat',
            'admin/ChatPage/app.js',
            [],
            true,
            'admin_page:toplevel_page_plugitify-assistant'
        );
        $app->enqueueScript(
            'plugitify-chat-mobile',
            'admin/ChatPage/mobile.js',
            [ 'plugitify-chat' ],
            true,
            'admin_page:toplevel_page_plugitify-assistant'
        );

        // Load Error Logs assets only on Error Logs page
        $app->enqueueStyle(
            'plugitify-error-logs',
            'admin/ErrorLogs/style.css',
            [ 'material-symbols-outlined' ],
            'admin_page:assistant_page_plugitify-error-logs'
        );

        if ( $hook === 'toplevel_page_plugitify-assistant' ) {
            wp_localize_script( 'plugitify-chat', 'plugitifyChat', [
                'restUrl' => rest_url( self::REST_NAMESPACE ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ] );
        }
    }

    public function renderPage(): void
    {
        $app = $this->getApplication();
        $app->view( 'ChatPage/chat' );
    }

    public function renderErrorLogsPage(): void
    {
        $app = $this->getApplication();
        $errorRepository = $this->getContainer()->get( 'error.repository' );
        
        // Pagination
        $perPage = 50;
        $currentPage = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $offset = ( $currentPage - 1 ) * $perPage;
        
        // Filter by level if provided
        $filterLevel = isset( $_GET['level'] ) ? sanitize_text_field( $_GET['level'] ) : null;
        
        $errors = $errorRepository->getPaginated( $perPage, $offset, $filterLevel );
        $totalErrors = $errorRepository->countAll( $filterLevel );
        $totalPages = ceil( $totalErrors / $perPage );
        
        $app->view( 'ErrorLogs/index', [
            'errors'      => $errors,
            'currentPage' => $currentPage,
            'totalPages'  => $totalPages,
            'totalErrors' => $totalErrors,
            'filterLevel' => $filterLevel,
        ] );
    }
}
