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

            $agent = $this->container->get( 'admin.agent' );
            $handler = $agent->chat( $messages );
            $responseMessage = $handler->getMessage();
            $content = $responseMessage->getContent() ?? '';

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
}
