<?php

namespace Plugifity\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Model\Chat;

/**
 * Repository for plugifity_chats.
 */
class ChatRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return Chat::class;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareCreate( array $data ): array
    {
        $data = parent::prepareCreate( $data );
        $data['status'] = $data['status'] ?? 'active';
        return $data;
    }

    /**
     * Get all chats (optionally by status).
     *
     * @return Chat[]
     */
    public function get( ?string $status = null ): array
    {
        $query = $this->newQuery()->orderBy( $this->getOrderColumn(), $this->getOrderDirection() );
        if ( $status !== null ) {
            $query->where( 'status', $status );
        }
        $rows = $query->get();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = Chat::fromRow( $row );
        }
        return $result;
    }
}
