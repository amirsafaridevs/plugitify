<?php

namespace Plugifity\Service\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Model\Task;
use Plugifity\Repository\TaskRepository;
use Plugifity\Service\Admin\ChatService;
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
            esc_html__( 'Agentify', 'plugitify' ),
            esc_html__( 'Agentify', 'plugitify' ),
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

        $app->enqueueScript(
            'jszip',
            'admin/jszip.min.js',
            [],
            true
        );
        $app->enqueueScript(
            'xlsx',
            'admin/xlsx.full.min.js',
            [],
            true
        );
        // Note: External scripts are discouraged but jsPDF is required for PDF generation
        // Consider bundling locally in future versions
        wp_enqueue_script(
            'jspdf',
            // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Required for PDF functionality, will be bundled locally in future version
            'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
            [],
            '2.5.1',
            true
        );
        // Load Assistant JavaScript (ES module for Agentify)
        $app->enqueueScript(
            'agentify-assistant',
            'admin/Assistant/app.js',
            [ 'jszip', 'xlsx', 'jspdf' ],
            true
        );
        wp_script_add_data( 'agentify-assistant', 'type', 'module' );

        $agentify_base = defined( 'PLUGITIFY_PLUGIN_FILE' )
            ? plugins_url( 'assets/Agentify/', PLUGITIFY_PLUGIN_FILE )
            : '';

        wp_localize_script( 'agentify-assistant', 'agentifyRest', [
            'restUrl'        => rest_url( self::REST_NAMESPACE ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'agentifyBaseUrl' => $agentify_base,
            'siteUrl'        => home_url( '/' ),
            'adminUrl'       => admin_url( '/' ),
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
                'model'           => [ 'type' => 'string', 'required' => false ],
                'allow_db_write'  => [ 'type' => 'boolean', 'required' => false ],
                'api_keys'        => [
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

        register_rest_route( self::REST_NAMESPACE, '/chats', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetChats' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
        register_rest_route( self::REST_NAMESPACE, '/chats', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restCreateChat' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'title' => [ 'type' => 'string', 'required' => false ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/chats/(?P<id>\\d+)/messages', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetMessages' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id' => [ 'type' => 'integer', 'required' => true, 'validate_callback' => function ( $v ) {
                    return is_numeric( $v ) && (int) $v > 0;
                } ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/chat', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restSendMessage' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'chat_id' => [ 'type' => 'integer', 'required' => false ],
                'content' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/chats/(?P<id>\\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'restUpdateChatTitle' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id'    => [ 'type' => 'integer', 'required' => true, 'validate_callback' => function ( $v ) {
                    return is_numeric( $v ) && (int) $v > 0;
                } ],
                'title' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/chats/(?P<id>\\d+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'restDeleteChat' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id' => [ 'type' => 'integer', 'required' => true, 'validate_callback' => function ( $v ) {
                    return is_numeric( $v ) && (int) $v > 0;
                } ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/chat/messages', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restAppendMessage' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'chat_id' => [ 'type' => 'integer', 'required' => true ],
                'role'    => [ 'type' => 'string', 'required' => true, 'enum' => [ 'user', 'assistant', 'system' ] ],
                'content' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/db-query', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restDbQuery' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'query' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/db-execute', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restDbExecute' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'query' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/plugins', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetPlugins' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/upload-zip', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restUploadZip' ],
            'permission_callback' => function () {
                return current_user_can( 'upload_files' ) && current_user_can( 'manage_options' );
            },
            'args' => [],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/upload-txt', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restUploadTxt' ],
            'permission_callback' => function () {
                return current_user_can( 'upload_files' ) && current_user_can( 'manage_options' );
            },
            'args' => [],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/upload-excel', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restUploadExcel' ],
            'permission_callback' => function () {
                return current_user_can( 'upload_files' ) && current_user_can( 'manage_options' );
            },
            'args' => [],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/upload-pdf', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restUploadPdf' ],
            'permission_callback' => function () {
                return current_user_can( 'upload_files' ) && current_user_can( 'manage_options' );
            },
            'args' => [],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/list-directory', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restListDirectory' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'path' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tools/read-file', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restReadFile' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'path' => [
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ] );

        register_rest_route( self::REST_NAMESPACE, '/tasks', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'restGetTasks' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'chat_id' => [ 'type' => 'integer', 'required' => false ],
                'status'  => [ 'type' => 'string', 'required' => false ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tasks', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restCreateTask' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'title'       => [ 'type' => 'string', 'required' => true ],
                'description' => [ 'type' => 'string', 'required' => false ],
                'chat_id'     => [ 'type' => 'integer', 'required' => false ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tasks/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [ $this, 'restUpdateTask' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id'     => [ 'type' => 'integer', 'required' => true ],
                'status' => [ 'type' => 'string', 'required' => false ],
            ],
        ] );
        register_rest_route( self::REST_NAMESPACE, '/tasks/sync', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'restSyncTasks' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [],
        ] );
    }

    public function restGetSettings( WP_REST_Request $request ): WP_REST_Response
    {
        $chatService = $this->getContainer()->get( ChatService::class );
        $data = get_option( self::OPTION_SETTINGS, [] );
        $data = wp_parse_args( $data, [
            'model'          => 'deepseek|deepseek-chat',
            'api_keys'       => [
                'deepseek' => '',
                'chatgpt'  => '',
                'gemini'   => '',
                'claude'   => '',
            ],
            'allow_db_write' => false,
        ] );
        $data['allow_db_write'] = ! empty( $data['allow_db_write'] );
        $data['system_instruction'] = $chatService->getSystemInstruction();
        return new WP_REST_Response( $data, 200 );
    }

    public function restSaveSettings( WP_REST_Request $request ): WP_REST_Response
    {
        $model    = $request->get_param( 'model' );
        $api_keys = $request->get_param( 'api_keys' );
        $current  = get_option( self::OPTION_SETTINGS, [] );
        $current  = wp_parse_args( $current, [
            'model'          => 'deepseek|deepseek-chat',
            'api_keys'       => [ 'deepseek' => '', 'chatgpt' => '', 'gemini' => '', 'claude' => '' ],
            'allow_db_write' => false,
        ] );
        if ( is_string( $model ) ) {
            $current['model'] = sanitize_text_field( $model );
        }
        $allow_db_write = $request->get_param( 'allow_db_write' );
        if ( $allow_db_write !== null ) {
            $current['allow_db_write'] = (bool) $allow_db_write;
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
            'message' => __( 'Settings saved.', 'plugitify' ),
        ], 200 );
    }

    public function restGetChats( WP_REST_Request $request ): WP_REST_Response
    {
        $chatService = $this->getContainer()->get( ChatService::class );
        $chats = $chatService->getChats();
        $items = [];
        foreach ( $chats as $chat ) {
            $items[] = [
                'id'         => $chat->id,
                'title'      => $chat->title,
                'status'     => $chat->status,
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
            ];
        }
        return new WP_REST_Response( [ 'chats' => $items ], 200 );
    }

    public function restCreateChat( WP_REST_Request $request ): WP_REST_Response
    {
        $title = $request->get_param( 'title' );
        $chatService = $this->getContainer()->get( ChatService::class );
        $id = $chatService->createChat( is_string( $title ) ? $title : null );
        if ( $id === false ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Failed to create chat.', 'plugitify' ) ], 500 );
        }
        return new WP_REST_Response( [ 'chat_id' => $id ], 200 );
    }

    public function restGetMessages( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = (int) $request->get_param( 'id' );
        $chatService = $this->getContainer()->get( ChatService::class );
        $messages = $chatService->getMessages( $chatId );
        $items = [];
        foreach ( $messages as $msg ) {
            $items[] = [
                'id'         => $msg->id,
                'role'       => $msg->role,
                'content'    => $msg->content,
                'created_at' => $msg->created_at,
            ];
        }
        return new WP_REST_Response( [ 'messages' => $items ], 200 );
    }

    public function restSendMessage( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = $request->get_param( 'chat_id' );
        $content = $request->get_param( 'content' );
        if ( ! is_string( $content ) || trim( $content ) === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Message is required.', 'plugitify' ) ], 400 );
        }
        $chatService = $this->getContainer()->get( ChatService::class );
        try {
            $result = $chatService->completeChat( $chatId !== null ? (int) $chatId : null, $content );
            return new WP_REST_Response( [
                'success' => true,
                'chat_id' => $result['chat_id'],
                'content'  => $result['content'],
            ], 200 );
        } catch ( \Exception $e ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $e->getMessage(),
            ], 400 );
        }
    }

    public function restUpdateChatTitle( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = (int) $request->get_param( 'id' );
        $title  = $request->get_param( 'title' );
        if ( ! is_string( $title ) || trim( $title ) === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Title is required.', 'plugitify' ) ], 400 );
        }
        $chatService = $this->getContainer()->get( ChatService::class );
        $ok = $chatService->updateChatTitleIfNew( $chatId, $title );
        if ( ! $ok ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Chat title can only be set when it is new or empty.', 'plugitify' ) ], 400 );
        }
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function restDeleteChat( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = (int) $request->get_param( 'id' );
        $chatService = $this->getContainer()->get( ChatService::class );
        $ok = $chatService->deleteChat( $chatId );
        if ( ! $ok ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Failed to delete chat.', 'plugitify' ) ], 500 );
        }
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    public function restAppendMessage( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = (int) $request->get_param( 'chat_id' );
        $role   = $request->get_param( 'role' );
        $content = $request->get_param( 'content' );
        if ( ! is_string( $content ) ) {
            $content = '';
        }
        $chatService = $this->getContainer()->get( ChatService::class );
        $ok = $chatService->appendMessage( $chatId, $role, $content );
        if ( ! $ok ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Failed to save message.', 'plugitify' ) ], 400 );
        }
        return new WP_REST_Response( [ 'success' => true ], 200 );
    }

    /**
     * Run a read-only SQL query (SELECT only). Single statement.
     */
    public function restDbQuery( WP_REST_Request $request ): WP_REST_Response
    {
        $query = $request->get_param( 'query' );
        if ( ! is_string( $query ) || trim( $query ) === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Query is required.', 'plugitify' ) ], 400 );
        }
        $query = trim( $query );
        if ( strpos( $query, ';' ) !== false ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Only a single statement is allowed.', 'plugitify' ) ], 400 );
        }
        $first = strtoupper( substr( $query, 0, 6 ) );
        if ( $first !== 'SELECT' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Only SELECT queries are allowed. Use the write tool for changes.', 'plugitify' ) ], 400 );
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is validated and sanitized before use
        $results = $wpdb->get_results( $query );
        if ( $results === null && $wpdb->last_error ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $wpdb->last_error,
            ], 400 );
        }
        $rows = is_array( $results ) ? array_map( function ( $row ) {
            return (array) $row;
        }, $results ) : [];
        return new WP_REST_Response( [ 'success' => true, 'data' => $rows, 'count' => count( $rows ) ], 200 );
    }

    /**
     * Run a write SQL query (INSERT/UPDATE/DELETE/REPLACE). Only if allow_db_write is enabled.
     */
    public function restDbExecute( WP_REST_Request $request ): WP_REST_Response
    {
        $settings = get_option( self::OPTION_SETTINGS, [] );
        $allow = ! empty( $settings['allow_db_write'] );
        if ( ! $allow ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Database changes are disabled. The admin must enable "Allow database changes" in Agentify Settings to run INSERT/UPDATE/DELETE.', 'plugitify' ),
                'code'    => 'db_write_disabled',
            ], 403 );
        }
        $query = $request->get_param( 'query' );
        if ( ! is_string( $query ) || trim( $query ) === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Query is required.', 'plugitify' ) ], 400 );
        }
        $query = trim( $query );
        if ( strpos( $query, ';' ) !== false ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Only a single statement is allowed.', 'plugitify' ) ], 400 );
        }
        $upper = strtoupper( substr( $query, 0, 12 ) );
        $allowed = ( strpos( $upper, 'INSERT' ) === 0 || strpos( $upper, 'UPDATE' ) === 0 || strpos( $upper, 'DELETE' ) === 0 || strpos( $upper, 'REPLACE' ) === 0 );
        if ( ! $allowed ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Only INSERT, UPDATE, DELETE, or REPLACE are allowed. Use the read tool for SELECT.', 'plugitify' ) ], 400 );
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is validated and sanitized before use
        $affected = $wpdb->query( $query );
        if ( $affected === false && $wpdb->last_error ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $wpdb->last_error,
            ], 400 );
        }
        return new WP_REST_Response( [
            'success'        => true,
            'rows_affected'  => $affected === false ? 0 : (int) $affected,
        ], 200 );
    }

    /**
     * Return list of installed plugins with name, status (active/inactive), and directory (slug).
     */
    public function restGetPlugins( WP_REST_Request $request ): WP_REST_Response
    {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all_plugins = get_plugins();
        $items = [];
        foreach ( $all_plugins as $plugin_file => $plugin_data ) {
            $directory = dirname( $plugin_file );
            if ( $directory === '.' ) {
                $directory = basename( $plugin_file, '.php' );
            }
            $items[] = [
                'name'      => isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '',
                'status'    => is_plugin_active( $plugin_file ) ? 'active' : 'inactive',
                'directory' => $directory,
                'version'   => isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '',
            ];
        }
        return new WP_REST_Response( [ 'success' => true, 'plugins' => $items, 'count' => count( $items ) ], 200 );
    }

    /**
     * Accept a ZIP file upload (multipart/form-data, field "file"), add to media library, return download URL.
     */
    public function restUploadZip( WP_REST_Request $request ): WP_REST_Response
    {
        if ( ! function_exists( 'wp_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $files = $request->get_file_params();
        if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'No file uploaded or invalid upload.', 'plugitify' ) ], 400 );
        }
        $file = $files['file'];
        $ext = strtolower( pathinfo( isset( $file['name'] ) ? $file['name'] : '', PATHINFO_EXTENSION ) );
        if ( $ext !== 'zip' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Only ZIP files are allowed.', 'plugitify' ) ], 400 );
        }
        $file_data = [
            'name'     => isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : 'upload.zip',
            'type'     => isset( $file['type'] ) ? $file['type'] : 'application/zip',
            'tmp_name' => $file['tmp_name'],
            'error'    => isset( $file['error'] ) ? (int) $file['error'] : 0,
            'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
        ];
        $sideload = wp_handle_sideload( $file_data, [ 'test_form' => false ] );
        if ( ! empty( $sideload['error'] ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => $sideload['error'] ], 400 );
        }
        $file_path = $sideload['file'];
        $url       = $sideload['url'];
        $attachment = [
            'post_mime_type' => $sideload['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $id = wp_insert_attachment( $attachment, $file_path, 0 );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $file_path );
            return new WP_REST_Response( [ 'success' => false, 'message' => $id->get_error_message() ], 500 );
        }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_path ) );
        $attachment_url = wp_get_attachment_url( $id );
        return new WP_REST_Response( [
            'success' => true,
            'url'     => $attachment_url ?: $url,
            'id'      => (int) $id,
            'message' => __( 'ZIP uploaded to media library.', 'plugitify' ),
        ], 200 );
    }

    /**
     * Accept a TXT file upload (multipart/form-data, field "file"), add to media library, return download URL.
     */
    public function restUploadTxt( WP_REST_Request $request ): WP_REST_Response
    {
        return $this->restUploadSingleFile( $request, 'txt', [ 'txt' ], 'text/plain', __( 'Only TXT files are allowed.', 'plugitify' ), 'document.txt' );
    }

    /**
     * Accept an Excel file upload (multipart/form-data, field "file"), add to media library, return download URL.
     */
    public function restUploadExcel( WP_REST_Request $request ): WP_REST_Response
    {
        return $this->restUploadSingleFile( $request, 'xlsx', [ 'xlsx' ], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', __( 'Only Excel (XLSX) files are allowed.', 'plugitify' ), 'export.xlsx' );
    }

    /**
     * Accept a PDF file upload (multipart/form-data, field "file"), add to media library, return download URL.
     */
    public function restUploadPdf( WP_REST_Request $request ): WP_REST_Response
    {
        return $this->restUploadSingleFile( $request, 'pdf', [ 'pdf' ], 'application/pdf', __( 'Only PDF files are allowed.', 'plugitify' ), 'document.pdf' );
    }

    /**
     * Generic single-file upload: validate, sideload, insert attachment, return URL.
     *
     * @param WP_REST_Request $request       Request with file params.
     * @param string         $extKey        Extension key for error message.
     * @param string[]       $allowedExt    Allowed file extensions (lowercase).
     * @param string         $defaultMime   Default MIME type.
     * @param string         $errorMessage  Message when extension not allowed.
     * @param string         $defaultName   Default filename if missing.
     * @return WP_REST_Response
     */
    private function restUploadSingleFile( WP_REST_Request $request, $extKey, array $allowedExt, $defaultMime, $errorMessage, $defaultName ): WP_REST_Response
    {
        if ( ! function_exists( 'wp_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $files = $request->get_file_params();
        if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'No file uploaded or invalid upload.', 'plugitify' ) ], 400 );
        }
        $file   = $files['file'];
        $ext    = strtolower( pathinfo( isset( $file['name'] ) ? $file['name'] : '', PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowedExt, true ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => $errorMessage ], 400 );
        }
        $file_data = [
            'name'     => isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : $defaultName,
            'type'     => isset( $file['type'] ) ? $file['type'] : $defaultMime,
            'tmp_name' => $file['tmp_name'],
            'error'    => isset( $file['error'] ) ? (int) $file['error'] : 0,
            'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
        ];
        $sideload = wp_handle_sideload( $file_data, [ 'test_form' => false ] );
        if ( ! empty( $sideload['error'] ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => $sideload['error'] ], 400 );
        }
        $file_path = $sideload['file'];
        $url       = $sideload['url'];
        $attachment = [
            'post_mime_type' => $sideload['type'],
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $file_path ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];
        $id = wp_insert_attachment( $attachment, $file_path, 0 );
        if ( is_wp_error( $id ) ) {
            wp_delete_file( $file_path );
            return new WP_REST_Response( [ 'success' => false, 'message' => $id->get_error_message() ], 500 );
        }
        wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file_path ) );
        $attachment_url = wp_get_attachment_url( $id );
        return new WP_REST_Response( [
            'success' => true,
            'url'     => $attachment_url ?: $url,
            'id'      => (int) $id,
            'message' => __( 'File uploaded to media library.', 'plugitify' ),
        ], 200 );
    }

    /**
     * Resolve and validate path: must be inside WordPress root. Returns real path or null.
     *
     * @param string $path Path relative to ABSPATH (e.g. "" or "wp-content/plugins").
     * @return string|null Real path or null if invalid/outside WP.
     */
    private function resolvePathInsideWordPress( string $path ): ?string
    {
        $path = preg_replace( '#/+#', '/', trim( $path, " \t\n\r\0\x0B/" ) );
        $base = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );
        $full = $path === '' ? $base : $base . '/' . $path;
        $real = realpath( $full );
        if ( $real === false || $real === '' ) {
            return null;
        }
        $real = str_replace( '\\', '/', $real );
        $baseReal = str_replace( '\\', '/', realpath( $base ) );
        if ( $baseReal === false || strpos( $real, $baseReal ) !== 0 ) {
            return null;
        }
        return $real;
    }

    /**
     * List directory contents (folders and files) inside WordPress. Path is relative to WP root.
     */
    public function restListDirectory( WP_REST_Request $request ): WP_REST_Response
    {
        $path = $request->get_param( 'path' );
        $resolved = $this->resolvePathInsideWordPress( $path );
        if ( $resolved === null ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Invalid path or outside WordPress directory.', 'plugitify' ),
                'items'   => [],
            ], 400 );
        }
        if ( ! is_dir( $resolved ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Path is not a directory.', 'plugitify' ),
                'items'   => [],
            ], 400 );
        }
        $list = @scandir( $resolved );
        if ( ! is_array( $list ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Could not read directory.', 'plugitify' ),
                'items'   => [],
            ], 500 );
        }
        $items = [];
        $base  = rtrim( str_replace( '\\', '/', ABSPATH ), '/' );
        $baseReal = str_replace( '\\', '/', realpath( $base ) );
        foreach ( $list as $name ) {
            if ( $name === '.' || $name === '..' ) {
                continue;
            }
            $full = $resolved . '/' . $name;
            $isDir = is_dir( $full );
            $item = [
                'name' => $name,
                'type' => $isDir ? 'directory' : 'file',
            ];
            if ( ! $isDir && is_file( $full ) ) {
                $item['size'] = (int) @filesize( $full );
            }
            $items[] = $item;
        }
        usort( $items, function ( $a, $b ) {
            if ( $a['type'] !== $b['type'] ) {
                return $a['type'] === 'directory' ? -1 : 1;
            }
            return strcasecmp( $a['name'], $b['name'] );
        } );
        $relativePath = $path === '' ? '' : ltrim( $path, '/' );
        return new WP_REST_Response( [
            'success' => true,
            'path'    => $relativePath,
            'items'   => $items,
        ], 200 );
    }

    /**
     * Read file contents. Path is relative to WordPress root. Limited to text files and max size.
     */
    public function restReadFile( WP_REST_Request $request ): WP_REST_Response
    {
        $path = $request->get_param( 'path' );
        $resolved = $this->resolvePathInsideWordPress( $path );
        if ( $resolved === null ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Invalid path or outside WordPress directory.', 'plugitify' ),
            ], 400 );
        }
        if ( ! is_file( $resolved ) ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Path is not a file.', 'plugitify' ),
            ], 400 );
        }
        $maxSize = 1024 * 1024; // 1 MB
        $size = @filesize( $resolved );
        if ( $size === false || $size > $maxSize ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => $size > $maxSize
                    ? __( 'File is too large to read (max 1 MB).', 'plugitify' )
                    : __( 'Could not read file.', 'plugitify' ),
            ], 400 );
        }
        $content = @file_get_contents( $resolved );
        if ( $content === false ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Could not read file contents.', 'plugitify' ),
            ], 500 );
        }
        // Detect binary: null bytes or high proportion of non-printable
        $sample = strlen( $content ) > 8192 ? substr( $content, 0, 8192 ) : $content;
        if ( strpos( $sample, "\0" ) !== false ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'Binary files cannot be read as text.', 'plugitify' ),
            ], 400 );
        }
        $nonPrintable = preg_match_all( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $sample );
        if ( $nonPrintable > strlen( $sample ) * 0.3 ) {
            return new WP_REST_Response( [
                'success' => false,
                'message' => __( 'File appears to be binary, not readable as text.', 'plugitify' ),
            ], 400 );
        }
        return new WP_REST_Response( [
            'success' => true,
            'path'    => ltrim( preg_replace( '#/+#', '/', trim( $path, '/' ) ), '/' ),
            'content' => $content,
            'size'    => $size,
        ], 200 );
    }

    /**
     * List tasks (optionally by chat_id or status). Only returns tasks created by the model via create_task tool.
     * Excludes internal Agentify sync entries (title starting with "chat:" or "tool_followup:").
     */
    public function restGetTasks( WP_REST_Request $request ): WP_REST_Response
    {
        $chatId = $request->get_param( 'chat_id' );
        $status = $request->get_param( 'status' );
        $repo   = $this->getContainer()->get( TaskRepository::class );
        $tasks  = $repo->get(
            $chatId !== null ? (int) $chatId : null,
            is_string( $status ) ? $status : null
        );
        $tasks = array_filter( $tasks, function ( Task $t ) {
            $title = $t->title ?? '';
            return strpos( $title, 'chat:' ) !== 0 && strpos( $title, 'tool_followup:' ) !== 0;
        } );
        $items = array_map( function ( Task $t ) {
            $title = isset( $t->title ) && trim( (string) $t->title ) !== '' ? $t->title : __( 'Task', 'plugitify' );
            return [
                'id'          => $t->id,
                'chat_id'     => $t->chat_id,
                'title'       => $title,
                'description' => $t->description,
                'status'      => $t->status,
                'created_at'  => $t->created_at,
                'updated_at'  => $t->updated_at,
            ];
        }, array_values( $tasks ) );
        return new WP_REST_Response( [ 'tasks' => $items ], 200 );
    }

    /**
     * Create a task (persisted to DB). Aligns with README: task persistence.
     */
    public function restCreateTask( WP_REST_Request $request ): WP_REST_Response
    {
        $title       = $request->get_param( 'title' );
        $description = $request->get_param( 'description' );
        $chatId      = $request->get_param( 'chat_id' );
        if ( ! is_string( $title ) || trim( $title ) === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Title is required.', 'plugitify' ) ], 400 );
        }
        $repo = $this->getContainer()->get( TaskRepository::class );
        $data = [
            'title'       => sanitize_text_field( trim( $title ) ),
            'description' => is_string( $description ) ? sanitize_textarea_field( $description ) : null,
            'status'     => 'pending',
        ];
        if ( $chatId !== null ) {
            $data['chat_id'] = (int) $chatId;
        }
        $id = $repo->create( $data );
        if ( $id === false ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Failed to create task.', 'plugitify' ) ], 500 );
        }
        return new WP_REST_Response( [
            'success' => true,
            'task_id' => (int) $id,
            'message' => __( 'Task created.', 'plugitify' ),
        ], 200 );
    }

    /**
     * Update task (e.g. status: completed, cancelled). Aligns with README: updateTaskStatus.
     */
    public function restUpdateTask( WP_REST_Request $request ): WP_REST_Response
    {
        $id     = (int) $request->get_param( 'id' );
        $status = $request->get_param( 'status' );
        if ( ! is_string( $status ) || trim( $status ) === '' ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Status is required.', 'plugitify' ) ], 400 );
        }
        $repo   = $this->getContainer()->get( TaskRepository::class );
        $task   = $repo->find( $id );
        if ( $task === null ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Task not found.', 'plugitify' ) ], 404 );
        }
        $ok = $repo->update( $id, [ 'status' => sanitize_text_field( $status ) ] );
        if ( $ok === false ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Failed to update task.', 'plugitify' ) ], 500 );
        }
        return new WP_REST_Response( [ 'success' => true, 'message' => __( 'Task updated.', 'plugitify' ) ], 200 );
    }

    /**
     * Sync Agentify tasks from client (agent.getTasks()) to DB. Body: { chat_id, tasks }.
     */
    public function restSyncTasks( WP_REST_Request $request ): WP_REST_Response
    {
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'Invalid body.', 'plugitify' ) ], 400 );
        }
        $chatId = isset( $body['chat_id'] ) ? (int) $body['chat_id'] : null;
        $tasks  = isset( $body['tasks'] ) && is_array( $body['tasks'] ) ? $body['tasks'] : [];
        if ( $chatId <= 0 ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => __( 'chat_id is required.', 'plugitify' ) ], 400 );
        }
        $repo = $this->getContainer()->get( TaskRepository::class );
        $synced = 0;
        foreach ( $tasks as $t ) {
            $type    = isset( $t['type'] ) ? sanitize_text_field( (string) $t['type'] ) : 'chat';
            $status  = isset( $t['status'] ) ? sanitize_text_field( (string) $t['status'] ) : 'pending';
            $input   = isset( $t['input'] ) ? (string) $t['input'] : '';
            $title   = $type . ( $input !== '' ? ': ' . wp_trim_words( $input, 8 ) : '' );
            $desc    = isset( $t['id'] ) ? 'agentify_id:' . sanitize_text_field( (string) $t['id'] ) : null;
            $data    = [
                'chat_id'     => $chatId,
                'title'       => substr( $title, 0, 255 ),
                'description' => $desc,
                'status'      => $status,
            ];
            $id = $repo->create( $data );
            if ( $id !== false ) {
                $synced++;
            }
        }
        return new WP_REST_Response( [
            'success' => true,
            'synced'  => $synced,
            /* translators: %d: number of tasks synced */
            'message' => sprintf( __( '%d task(s) synced.', 'plugitify' ), $synced ),
        ], 200 );
    }

    public function renderPage(): void
    {
        $app = $this->getApplication();
        $app->view( 'Assistant/index' );
    }
}
