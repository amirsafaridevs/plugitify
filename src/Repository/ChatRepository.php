<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Core\DB;
use Plugifity\Core\Database\QueryBuilder;
use Plugifity\Model\Chat;

/**
 * Repository for Chat (plugifity_chats).
 * Default scope: only non-deleted chats (deleted_at IS NULL).
 */
class ChatRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return Chat::class;
    }

    /**
     * Base query with soft-delete scope (exclude deleted_at).
     */
    protected function newQuery(): QueryBuilder
    {
        return DB::table($this->getTable())->whereNull('deleted_at');
    }
}
