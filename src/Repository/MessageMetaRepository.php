<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Model\MessageMeta;

/**
 * Repository for MessageMeta (plugifity_message_meta).
 */
class MessageMetaRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return MessageMeta::class;
    }

    /**
     * Default order by id.
     */
    protected function getOrderColumn(): string
    {
        return 'id';
    }

    /**
     * No timestamps on message_meta table.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareCreate(array $data): array
    {
        return $data;
    }

    /**
     * No timestamps on message_meta table.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function prepareUpdate(array $data): array
    {
        return $data;
    }
}
