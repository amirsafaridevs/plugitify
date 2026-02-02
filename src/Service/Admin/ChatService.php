<?php

namespace Plugifity\Service\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\NeuronException;
use NeuronAI\Exceptions\ProviderException;
use Plugifity\Contract\Interface\ContainerInterface;
use Plugifity\Repository\ChatRepository;
use Plugifity\Repository\ErrorRepository;
use Plugifity\Repository\MessageRepository;
use Throwable;

/**
 * Handles chat flow: create/reuse chat, persist messages, call Agent, return assistant reply.
 * Errors are logged to plugifity_errors and a user-facing message is returned.
 */
class ChatService
{
    public const DEFAULT_CHAT_TITLE = 'new';

    public function __construct(
        protected ContainerInterface $container,
        protected ChatRepository $chatRepository,
        protected MessageRepository $messageRepository,
        protected ErrorRepository $errorRepository,
    ) {
    }

    /**
     * Send user message, run agent, persist assistant reply.
     *
     * @param string $message User message text (sanitized).
     * @param int|null $chatId Existing chat ID or null for new conversation.
     * @return array{success: true, chat_id: int, content: string}|array{success: false, error: array{message: string, code?: string}}
     */
    public function sendMessage( string $message, ?int $chatId = null ): array
    {
        try {
            if ( $chatId === null ) {
                $chatId = $this->chatRepository->create( [
                    'title' => self::DEFAULT_CHAT_TITLE,
                ] );
                if ( $chatId === false ) {
                    $this->logError( 'Failed to create chat', [ 'message' => $message ], 'CHAT_CREATE' );
                    return [
                        'success' => false,
                        'error'   => [ 'message' => __( 'Could not start the conversation. Please try again.', 'plugifity' ) ],
                    ];
                }
                $chatId = (int) $chatId;
            }

            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'user',
                'content' => $message,
            ] );

            $history = $this->messageRepository->getByChatId( $chatId );
            $messages = $this->buildNeuronMessages( $history );

            $agent = new \Plugifity\Service\Admin\Agent\PlugitifyAgent();
            $stream = $agent->stream( $messages );
            
            // Collect full content from stream
            $content = '';
            foreach ( $stream as $chunk ) {
                $content .= $chunk;
            }

            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'assistant',
                'content' => $content,
            ] );

            return [
                'success'  => true,
                'chat_id'  => $chatId,
                'content'  => $content,
            ];
        } catch ( ProviderException $e ) {
            $this->logError( $e->getMessage(), [ 'chat_id' => $chatId ?? null ], 'NEURON_PROVIDER', $e );
            return [
                'success' => false,
                'error'   => [
                    'message' => __( 'Could not reach the model. Please check your API key and model in Settings.', 'plugifity' ),
                    'code'    => 'NEURON_PROVIDER',
                ],
            ];
        } catch ( NeuronException $e ) {
            $this->logError( $e->getMessage(), [ 'chat_id' => $chatId ?? null ], 'NEURON', $e );
            return [
                'success' => false,
                'error'   => [
                    'message' => __( 'The assistant encountered an error. Please try again.', 'plugifity' ),
                    'code'    => 'NEURON',
                ],
            ];
        } catch ( Throwable $e ) {
            $this->logError( $e->getMessage(), [ 'chat_id' => $chatId ?? null, 'trace' => $e->getTraceAsString() ], 'CHAT_ERROR', $e );
            return [
                'success' => false,
                'error'   => [
                    'message' => __( 'Something went wrong. Please try again.', 'plugifity' ),
                    'code'    => 'CHAT_ERROR',
                ],
            ];
        }
    }

    /**
     * @param \Plugifity\Model\Message[] $history
     * @return array<int, UserMessage|AssistantMessage>
     */
    private function buildNeuronMessages( array $history ): array
    {
        $messages = [];
        foreach ( $history as $msg ) {
            $content = $msg->content ?? '';
            if ( $msg->role === 'user' ) {
                $messages[] = new UserMessage( $content );
            } elseif ( $msg->role === 'assistant' ) {
                $messages[] = new AssistantMessage( $content );
            }
        }
        return $messages;
    }

    private function logError( string $message, array $context, string $code, ?Throwable $e = null ): void
    {
        $this->errorRepository->create( [
            'message' => $message,
            'context' => wp_json_encode( $context ),
            'code'    => $code,
            'level'   => 'error',
            'file'    => $e ? $e->getFile() : null,
            'line'    => $e ? $e->getLine() : null,
        ] );
    }

    /**
     * Stream chat message (Server-Sent Events).
     * 
     * @param string   $userMessage User message text.
     * @param int|null $chatId      Existing chat ID or null for new.
     * @return void Outputs SSE stream and exits.
     */
    public function streamMessage( string $userMessage, ?int $chatId ): void
    {
        // Set headers for SSE
        header( 'Content-Type: text/event-stream' );
        header( 'Cache-Control: no-cache' );
        header( 'X-Accel-Buffering: no' );
        
        // Disable output buffering
        if ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        try {
            // Create or reuse chat
            if ( $chatId === null ) {
                $chatId = $this->chatRepository->create( [ 'title' => 'new', 'status' => 'active' ] );
                
                // Send chat_id event
                echo "event: chat_id\n";
                echo 'data: ' . wp_json_encode( [ 'chat_id' => $chatId ] ) . "\n\n";
                if ( function_exists( 'fastcgi_finish_request' ) ) {
                    flush();
                } else {
                    ob_flush();
                    flush();
                }
            }

            // Save user message
            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'user',
                'content' => $userMessage,
            ] );

            // Get history and build messages
            $history = $this->messageRepository->getByChatId( $chatId );
            $messages = $this->buildNeuronMessages( $history );

            // Stream response
            $agent = new \Plugifity\Service\Admin\Agent\PlugitifyAgent();
            $stream = $agent->stream( $messages );
            
            $fullContent = '';
            foreach ( $stream as $chunk ) {
                $fullContent .= $chunk;
                
                // Send chunk event
                echo "event: chunk\n";
                echo 'data: ' . wp_json_encode( [ 'text' => $chunk ] ) . "\n\n";
                if ( function_exists( 'fastcgi_finish_request' ) ) {
                    flush();
                } else {
                    ob_flush();
                    flush();
                }
            }

            // Save assistant message
            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'assistant',
                'content' => $fullContent,
            ] );

            // Send done event
            echo "event: done\n";
            echo 'data: ' . wp_json_encode( [ 'success' => true ] ) . "\n\n";
            if ( function_exists( 'fastcgi_finish_request' ) ) {
                flush();
            } else {
                ob_flush();
                flush();
            }

        } catch ( \Exception $e ) {
            $this->logError( $e->getMessage(), [ 'chat_id' => $chatId ?? null, 'trace' => $e->getTraceAsString() ], 'CHAT_STREAM_ERROR', $e );

            // Send error event
            echo "event: error\n";
            echo 'data: ' . wp_json_encode( [
                'message' => __( 'Something went wrong. Please try again.', 'plugifity' ),
                'details' => $e->getMessage(),
            ] ) . "\n\n";
            if ( function_exists( 'fastcgi_finish_request' ) ) {
                flush();
            } else {
                ob_flush();
                flush();
            }
        }

        exit;
    }
}
