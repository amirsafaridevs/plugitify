<?php

namespace Plugifity\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Core\Database\QueryBuilder;
use Plugifity\Model\Task;

/**
 * Repository for plugifity_tasks (with soft delete scope).
 */
class TaskRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return Task::class;
    }

    protected function newQuery(): QueryBuilder
    {
        return parent::newQuery()->whereNull( 'deleted_at' );
    }

    protected function getOrderDirection(): string
    {
        return 'ASC';
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareCreate( array $data ): array
    {
        $data = parent::prepareCreate( $data );
        $data['status'] = $data['status'] ?? 'pending';
        return $data;
    }

    /**
     * Get all tasks (optionally by chat_id or status).
     *
     * @return Task[]
     */
    public function get( ?int $chatId = null, ?string $status = null ): array
    {
        $query = $this->newQuery()->orderBy( $this->getOrderColumn(), $this->getOrderDirection() );
        if ( $chatId !== null ) {
            $query->where( 'chat_id', $chatId );
        }
        if ( $status !== null ) {
            $query->where( 'status', $status );
        }
        $rows = $query->get();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = Task::fromRow( $row );
        }
        return $result;
    }

    /**
     * Get tasks by chat_id.
     *
     * @return Task[]
     */
    public function getByChatId( int $chatId ): array
    {
        return $this->get( $chatId, null );
    }

    /**
     * Get tasks by chat_id and status.
     *
     * @return Task[]
     */
    public function getByChatIdAndStatus( int $chatId, string $status ): array
    {
        return $this->get( $chatId, $status );
    }

    /**
     * Soft delete (set deleted_at).
     *
     * @return int|false
     */
    public function delete( int $id )
    {
        return $this->update( $id, [ 'deleted_at' => current_time( 'mysql' ) ] );
    }

    /**
     * Hard delete from database.
     *
     * @return int|false
     */
    public function forceDelete( int $id )
    {
        return parent::delete( $id );
    }
}
