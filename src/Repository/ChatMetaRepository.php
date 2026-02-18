<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Model\ChatMeta;

/**
 * Repository for ChatMeta (plugifity_chat_meta).
 */
class ChatMetaRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return ChatMeta::class;
    }

    /**
     * Default order by meta_key for consistency.
     */
    protected function getOrderColumn(): string
    {
        return 'id';
    }

    /**
     * No timestamps on chat_meta table.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareCreate(array $data): array
    {
        return $data;
    }

    /**
     * No timestamps on chat_meta table.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareUpdate(array $data): array
    {
        return $data;
    }
}
