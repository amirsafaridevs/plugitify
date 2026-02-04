<?php

namespace Plugifity\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Model\Message;

/**
 * Repository for plugifity_messages.
 */
class MessageRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return Message::class;
    }

    protected function getOrderDirection(): string
    {
        return 'ASC';
    }

    /**
     * Get all messages (optionally for a chat).
     *
     * @return Message[]
     */
    public function get( ?int $chatId = null ): array
    {
        $query = $this->newQuery()->orderBy( $this->getOrderColumn(), $this->getOrderDirection() );
        if ( $chatId !== null ) {
            $query->where( 'chat_id', $chatId );
        }
        $rows = $query->get();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = Message::fromRow( $row );
        }
        return $result;
    }

    /**
     * Get messages by chat_id (conversation history).
     *
     * @return Message[]
     */
    public function getByChatId( int $chatId ): array
    {
        return $this->get( $chatId );
    }

    /**
     * Delete all messages for a chat.
     *
     * @param int $chatId
     * @return int|false Rows affected or false
     */
    public function deleteByChatId( int $chatId )
    {
        return $this->newQuery()->where( 'chat_id', $chatId )->delete();
    }
}
