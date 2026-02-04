<?php

namespace Plugifity\Service\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Repository\ChatRepository;
use Plugifity\Repository\MessageRepository;

/**
 * Chat service: create chats, persist messages, call AI with system instruction.
 */
class ChatService extends AbstractService
{
    public const OPTION_SETTINGS = 'agentify_settings';
    public const SYSTEM_INSTRUCTION_FILE = 'src/Instruction/System.md';

    /** @var ChatRepository */
    protected $chatRepository;
    /** @var MessageRepository */
    protected $messageRepository;

    public function __construct( ChatRepository $chatRepository, MessageRepository $messageRepository )
    {
        $this->chatRepository = $chatRepository;
        $this->messageRepository = $messageRepository;
    }

    public function boot( ContainerInterface $container ): void
    {
    }

    /**
     * System instruction text from System.md (for agent system message).
     *
     * @return string
     */
    public function getSystemInstruction(): string
    {
        $base = defined( 'PLUGIFITY_PLUGIN_FILE' )
            ? plugin_dir_path( PLUGIFITY_PLUGIN_FILE )
            : '';
        $path = $base . self::SYSTEM_INSTRUCTION_FILE;
        if ( ! is_readable( $path ) ) {
            return '';
        }
        $content = file_get_contents( $path );
        return is_string( $content ) ? trim( $content ) : '';
    }

    /**
     * Create a new chat. Title is set to "new #id". Returns new chat ID or false.
     *
     * @param string|null $title Ignored; title is always set to "new #id".
     * @return int|false
     */
    public function createChat( ?string $title = null )
    {
        $id = $this->chatRepository->create( [
            'title' => null,
        ] );
        if ( $id === false ) {
            return false;
        }
        $id = (int) $id;
        $this->chatRepository->update( $id, [ 'title' => 'new #' . $id ] );
        return $id;
    }

    /**
     * Update chat title only if it is empty or "new" or "new #id".
     *
     * @param int $chatId
     * @param string $title
     * @return bool
     */
    public function updateChatTitleIfNew( int $chatId, string $title ): bool
    {
        $chat = $this->chatRepository->find( $chatId );
        if ( ! $chat ) {
            return false;
        }
        $current = $chat->title;
        $current = $current === null ? '' : trim( (string) $current );
        $isNew = $current === '' || strtolower( $current ) === 'new' || preg_match( '/^new\s*#?\s*\d+$/i', $current );
        if ( ! $isNew ) {
            return false;
        }
        $title = sanitize_text_field( trim( $title ) );
        if ( $title === '' ) {
            return false;
        }
        $this->chatRepository->update( $chatId, [ 'title' => $title ] );
        return true;
    }

    /**
     * Append a message to a chat (for frontend Agentify sync).
     *
     * @param int $chatId
     * @param string $role user|assistant|system
     * @param string $content
     * @return bool
     */
    public function appendMessage( int $chatId, string $role, string $content ): bool
    {
        $chat = $this->chatRepository->find( $chatId );
        if ( ! $chat ) {
            return false;
        }
        $role = in_array( $role, [ 'user', 'assistant', 'system' ], true ) ? $role : 'user';
        $this->messageRepository->create( [
            'chat_id' => $chatId,
            'role'    => $role,
            'content' => $content,
        ] );
        $this->chatRepository->touch( $chatId );
        return true;
    }

    /**
     * List chats (active, most recent first).
     *
     * @return array<int, object> Chat models
     */
    public function getChats(): array
    {
        return $this->chatRepository->get( 'active' );
    }

    /**
     * Delete a chat and all its messages.
     *
     * @param int $chatId
     * @return bool
     */
    public function deleteChat( int $chatId ): bool
    {
        $this->messageRepository->deleteByChatId( $chatId );
        $result = $this->chatRepository->delete( $chatId );
        return $result !== false && $result >= 0;
    }

    /**
     * Get messages for a chat (chronological).
     *
     * @param int $chatId
     * @return array<int, object> Message models
     */
    public function getMessages( int $chatId ): array
    {
        return $this->messageRepository->getByChatId( $chatId );
    }

    /**
     * Send user message, call AI with system + history, save both messages.
     * Creates a new chat if $chatId is null.
     *
     * @param int|null $chatId
     * @param string $userContent
     * @return array{ chat_id: int, content: string } Assistant reply content
     * @throws \Exception On API or validation error
     */
    public function completeChat( ?int $chatId, string $userContent ): array
    {
        $userContent = trim( $userContent );
        if ( $userContent === '' ) {
            throw new \InvalidArgumentException( __( 'Message cannot be empty.', 'plugifity' ) );
        }

        $settings = get_option( self::OPTION_SETTINGS, [] );
        $settings = wp_parse_args( $settings, [
            'model'    => 'deepseek|deepseek-chat',
            'api_keys' => [
                'deepseek' => '',
                'chatgpt'  => '',
                'gemini'   => '',
                'claude'   => '',
            ],
        ] );
        $modelKey = $settings['model'];
        $apiKeys  = $settings['api_keys'];

        $parts = explode( '|', $modelKey, 2 );
        $provider = $parts[0] ?? 'deepseek';
        $model    = $parts[1] ?? 'deepseek-chat';
        $apiKey   = isset( $apiKeys[ $provider ] ) ? $apiKeys[ $provider ] : '';

        if ( $apiKey === '' ) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: %s: provider name */
                    __( 'API key for %s is not set. Please configure it in Settings.', 'plugifity' ),
                    $provider
                )
            );
        }

        if ( $chatId === null ) {
            $newId = $this->createChat( wp_trim_words( $userContent, 5 ) );
            if ( $newId === false ) {
                throw new \RuntimeException( __( 'Failed to create chat.', 'plugifity' ) );
            }
            $chatId = $newId;
        } else {
            $chat = $this->chatRepository->find( $chatId );
            if ( ! $chat ) {
                throw new \InvalidArgumentException( __( 'Chat not found.', 'plugifity' ) );
            }
        }

        $this->messageRepository->create( [
            'chat_id' => $chatId,
            'role'    => 'user',
            'content' => $userContent,
        ] );

        $history = $this->getMessages( $chatId );
        $messagesForApi = $this->buildMessagesForApi( $history );

        $reply = $this->callProvider( $provider, $model, $apiKey, $messagesForApi );

        $this->messageRepository->create( [
            'chat_id' => $chatId,
            'role'    => 'assistant',
            'content' => $reply,
        ] );

        $this->chatRepository->touch( $chatId );

        return [
            'chat_id' => $chatId,
            'content' => $reply,
        ];
    }

    /**
     * Build messages array for API: system (if any) + history (user/assistant only).
     *
     * @param array<int, object> $history
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildMessagesForApi( array $history ): array
    {
        $system = $this->getSystemInstruction();
        $out = [];
        if ( $system !== '' ) {
            $out[] = [ 'role' => 'system', 'content' => $system ];
        }
        foreach ( $history as $msg ) {
            $role = $msg->role ?? '';
            $content = $msg->content ?? '';
            if ( $role === 'user' || $role === 'assistant' ) {
                $out[] = [ 'role' => $role, 'content' => $content ];
            }
        }
        return $out;
    }

    /**
     * Call provider API (OpenAI-compatible: DeepSeek, ChatGPT).
     *
     * @param string $provider deepseek|chatgpt
     * @param string $model
     * @param string $apiKey
     * @param array<int, array{role: string, content: string}> $messages
     * @return string Assistant content
     * @throws \Exception
     */
    protected function callProvider( string $provider, string $model, string $apiKey, array $messages ): string
    {
        $url = $this->getProviderUrl( $provider );
        if ( $url === '' ) {
            throw new \RuntimeException(
                sprintf(
                    /* translators: %s: provider name */
                    __( 'Unsupported provider: %s.', 'plugifity' ),
                    $provider
                )
            );
        }

        $body = [
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ];

        $response = wp_remote_post( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException(
                __( 'Request failed: ', 'plugifity' ) . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $bodyResponse = wp_remote_retrieve_body( $response );
        $data = json_decode( $bodyResponse, true );

        if ( $code >= 400 ) {
            $message = isset( $data['error']['message'] ) ? $data['error']['message'] : (string) $bodyResponse;
            throw new \RuntimeException(
                sprintf(
                    /* translators: 1: HTTP code, 2: error message */
                    __( 'API error (%1$d): %2$s', 'plugifity' ),
                    $code,
                    $message
                )
            );
        }

        if ( ! is_array( $data ) || empty( $data['choices'][0]['message']['content'] ) ) {
            throw new \RuntimeException( __( 'Invalid API response.', 'plugifity' ) );
        }

        return (string) $data['choices'][0]['message']['content'];
    }

    /**
     * @param string $provider
     * @return string
     */
    protected function getProviderUrl( string $provider ): string
    {
        $urls = [
            'deepseek' => 'https://api.deepseek.com/v1/chat/completions',
            'chatgpt'  => 'https://api.openai.com/v1/chat/completions',
        ];
        return $urls[ $provider ] ?? '';
    }
}
