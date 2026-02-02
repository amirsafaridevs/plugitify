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

            // Update chat timestamp
            $this->chatRepository->touch( $chatId );

            $history = $this->messageRepository->getByChatId( $chatId );
            $messages = $this->buildNeuronMessages( $history );

            $agent = new \Plugifity\Service\Admin\Agent\PlugitifyAgent();
            
            // For non-streaming fallback, use chat() instead of stream()
            $handler = $agent->chat( $messages );
            $responseMessage = $handler->getMessage();
            $content = $responseMessage->getContent() ?? '';

            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'assistant',
                'content' => $content,
            ] );

            // Update chat timestamp
            $this->chatRepository->touch( $chatId );

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
                $this->flushOutput();
            }

            // Save user message
            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'user',
                'content' => $userMessage,
            ] );

            // Update chat timestamp
            $this->chatRepository->touch( $chatId );

            // Get history and build messages
            $history = $this->messageRepository->getByChatId( $chatId );
            $messages = $this->buildNeuronMessages( $history );

            // Send processing step
            echo "event: step\n";
            echo 'data: ' . wp_json_encode( [ 'step' => __( 'Analyzing your request...', 'plugifity' ) ] ) . "\n\n";
            $this->flushOutput();

            // Create agent and get response (blocking)
            $agent = new \Plugifity\Service\Admin\Agent\PlugitifyAgent();
            $handler = $agent->chat( $messages );
            
            // Run workflow and get state (includes message and history)
            $state = $handler->run();
            $responseMessage = $state->getMessage();
            $fullContent = $responseMessage->getContent() ?? '';
            
            // Check if tools were used by examining chat history
            $toolCalls = [];
            if ( method_exists( $state, 'getChatHistory' ) ) {
                $chatHistory = $state->getChatHistory();
                
                // Extract tool calls from history
                foreach ( $chatHistory->getMessages() as $msg ) {
                    if ( $msg instanceof \NeuronAI\Chat\Messages\ToolCallMessage ) {
                        foreach ( $msg->getTools() as $tool ) {
                            $toolCalls[] = [
                                'label' => $this->formatToolLabel( $tool->getName() ),
                            ];
                        }
                    }
                }
            }
            
            // Send task list if tools were used (BEFORE streaming starts)
            if ( count( $toolCalls ) > 0 ) {
                echo "event: task\n";
                echo 'data: ' . wp_json_encode( [ 'task' => [ 'action' => 'list', 'tasks' => $toolCalls ] ] ) . "\n\n";
                $this->flushOutput();
                
                // Send each task as "start" then immediately "done"
                for ( $i = 0; $i < count( $toolCalls ); $i++ ) {
                    // Show task is running
                    echo "event: task\n";
                    echo 'data: ' . wp_json_encode( [
                        'task' => [
                            'action' => 'start',
                            'index'  => $i,
                        ],
                    ] ) . "\n\n";
                    $this->flushOutput();
                    
                    // Update step text
                    echo "event: step\n";
                    echo 'data: ' . wp_json_encode( [ 'step' => $toolCalls[ $i ]['label'] ] ) . "\n\n";
                    $this->flushOutput();
                    
                    // Small delay to show task is processing
                    usleep( 300000 ); // 300ms
                    
                    // Mark as done
                    echo "event: task\n";
                    echo 'data: ' . wp_json_encode( [
                        'task' => [
                            'action' => 'done',
                            'index'  => $i,
                        ],
                    ] ) . "\n\n";
                    $this->flushOutput();
                }
            }
            
            // Send generating step (before streaming chunks)
            echo "event: step\n";
            echo 'data: ' . wp_json_encode( [ 'step' => __( 'Generating response...', 'plugifity' ) ] ) . "\n\n";
            $this->flushOutput();
            
            // Small delay before starting streaming
            usleep( 200000 ); // 200ms
            
            // Simulate streaming by sending content in chunks (word by word)
            $words = preg_split( '/(\s+)/u', $fullContent, -1, PREG_SPLIT_DELIM_CAPTURE );
            foreach ( $words as $word ) {
                if ( $word === '' ) {
                    continue;
                }
                
                echo "event: chunk\n";
                echo 'data: ' . wp_json_encode( [ 'text' => $word ] ) . "\n\n";
                $this->flushOutput();
                
                // Small delay to make streaming visible
                usleep( 50000 ); // 50ms
            }

            // Save assistant message
            $this->messageRepository->create( [
                'chat_id' => $chatId,
                'role'    => 'assistant',
                'content' => $fullContent,
            ] );

            // Update chat timestamp
            $this->chatRepository->touch( $chatId );

            // Send done event
            echo "event: done\n";
            echo 'data: ' . wp_json_encode( [ 'success' => true ] ) . "\n\n";
            $this->flushOutput();

        } catch ( \Exception $e ) {
            // Log to database with full context
            $this->errorRepository->create( [
                'message' => $e->getMessage(),
                'context' => wp_json_encode( [
                    'chat_id'    => $chatId ?? null,
                    'user_msg'   => $userMessage ?? null,
                    'trace'      => $e->getTraceAsString(),
                ] ),
                'code'    => 'STREAM_EXCEPTION',
                'level'   => 'error',
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ] );

            // Send error event to frontend (user-facing, safe message)
            echo "event: error\n";
            echo 'data: ' . wp_json_encode( [
                'message' => __( 'An error occurred while processing your request. Please check your settings and try again.', 'plugifity' ),
                'code'    => 'STREAM_EXCEPTION',
            ] ) . "\n\n";
            $this->flushOutput();
        }

        exit;
    }

    /**
     * Format tool name to user-friendly label.
     */
    private function formatToolLabel( string $toolName ): string
    {
        $labels = [
            'database_query' => __( 'Querying database...', 'plugifity' ),
            'file_read'      => __( 'Reading file...', 'plugifity' ),
            'file_write'     => __( 'Writing file...', 'plugifity' ),
        ];
        
        return $labels[ $toolName ] ?? sprintf( __( 'Executing: %s', 'plugifity' ), $toolName );
    }

    /**
     * Flush output for SSE streaming (safe wrapper for ob_flush + flush).
     */
    private function flushOutput(): void
    {
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            flush();
        } else {
            if ( ob_get_level() > 0 ) {
                ob_flush();
            }
            flush();
        }
    }
}
