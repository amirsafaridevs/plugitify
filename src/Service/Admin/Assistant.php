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
        register_rest_route( self::REST_NAMESPACE, '/chats', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetChats' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

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

        register_rest_route( self::REST_NAMESPACE, '/chat/stream', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restChatStream' ],
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

        register_rest_route( self::REST_NAMESPACE, '/chat/(?P<id>\d+)/messages', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetChatMessages' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args'                => [
                'id' => [
                    'type'     => 'integer',
                    'required' => true,
                ],
            ],
        ] );
    }

    /**
     * REST: GET chats — fetch all chats (ordered by updated_at desc).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restGetChats( WP_REST_Request $request ): WP_REST_Response
    {
        $chatRepository = $this->getContainer()->get( 'chat.repository' );
        $chats = $chatRepository->get( 'active' );
        
        $result = [];
        foreach ( $chats as $chat ) {
            $result[] = [
                'id'         => $chat->id,
                'title'      => $chat->title ?? __( 'New Chat', 'plugifity' ),
                'status'     => $chat->status ?? 'active',
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
            ];
        }
        
        return new WP_REST_Response( [ 'success' => true, 'chats' => $result ], 200 );
    }

    /**
     * REST: GET chat/:id/messages — fetch all messages for a chat.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restGetChatMessages( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = absint( $request->get_param( 'id' ) );
        if ( $chatId === 0 ) {
            return new WP_REST_Response( [
                'success' => false,
                'error'   => [ 'message' => __( 'Invalid chat ID.', 'plugifity' ) ],
            ], 400 );
        }

        $messageRepository = $this->getContainer()->get( 'message.repository' );
        $messages = $messageRepository->getByChatId( $chatId );
        
        $result = [];
        foreach ( $messages as $msg ) {
            $result[] = [
                'id'         => $msg->id,
                'chat_id'    => $msg->chat_id,
                'role'       => $msg->role,
                'content'    => $msg->content,
                'created_at' => $msg->created_at,
            ];
        }
        
        return new WP_REST_Response( [ 'success' => true, 'messages' => $result ], 200 );
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

    /**
     * REST: POST chat/stream — send user message, stream agent response (SSE).
     *
     * @param WP_REST_Request $request
     * @return void (exits after streaming)
     */
    public function restChatStream( WP_REST_Request $request ): void
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
            header( 'Content-Type: text/event-stream' );
            echo "event: error\n";
            echo 'data: ' . wp_json_encode( [
                'message' => __( 'Message is required.', 'plugifity' ),
            ] ) . "\n\n";
            flush();
            exit;
        }

        $chatService = $this->getContainer()->get( 'admin.chat' );
        $chatService->streamMessage( $message, $chatId );
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

        register_rest_route( self::REST_NAMESPACE, '/errors/clear', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restClearAllErrors' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
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
            esc_html__( 'Plugitify', 'plugifity' ),
            esc_html__( 'Plugitify', 'plugifity' ),
            'manage_options',
            'plugitify-assistant',
            [ $this, 'renderPage' ],
            'dashicons-admin-plugins',
            3
        );

        // Rename first submenu (Chat) - WordPress auto-creates first submenu with parent name
        global $submenu;
        if ( isset( $submenu['plugitify-assistant'][0] ) ) {
            $submenu['plugitify-assistant'][0][0] = esc_html__( 'Chat', 'plugifity' );
        }

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

        // Load Material Symbols only on plugin pages
        if ( $hook === 'toplevel_page_plugitify-assistant' || $hook === 'plugitify_page_plugitify-error-logs' ) {
            $app->enqueueExternalStyle(
                'material-symbols-outlined',
                'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0',
                []
            );
        }

        // Load ChatPage assets only on Chat page
        if ( $hook === 'toplevel_page_plugitify-assistant' ) {
            $app->enqueueStyle(
                'plugitify-chat',
                'admin/ChatPage/style.css',
                [ 'material-symbols-outlined' ]
            );
            $app->enqueueScript(
                'plugitify-chat',
                'admin/ChatPage/app.js',
                [],
                true
            );
            $app->enqueueScript(
                'plugitify-chat-mobile',
                'admin/ChatPage/mobile.js',
                [ 'plugitify-chat' ],
                true
            );

            wp_localize_script( 'plugitify-chat', 'plugitifyChat', [
                'restUrl' => rest_url( self::REST_NAMESPACE ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            ] );
        }

        // Load Error Logs assets only on Error Logs page
        if ( $hook === 'plugitify_page_plugitify-error-logs' ) {
            $app->enqueueStyle(
                'plugitify-error-logs',
                'admin/ErrorLogs/style.css',
                [ 'material-symbols-outlined' ]
            );
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

    /**
     * REST: Clear all error logs.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restClearAllErrors( WP_REST_Request $request ): WP_REST_Response
    {
        $errorRepository = $this->getContainer()->get( 'error.repository' );
        $deletedCount = $errorRepository->deleteAll();
        
        // Success if no errors occurred (even if 0 rows deleted)
        if ( $deletedCount >= 0 ) {
            return new WP_REST_Response( [ 'success' => true, 'deleted' => $deletedCount ], 200 );
        }
        
        return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Failed to clear errors', 'plugifity' ) ], 500 );
    }
}
