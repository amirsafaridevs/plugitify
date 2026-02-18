<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Model\Message;

/**
 * Repository for Message (plugifity_messages).
 */
class MessageRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return Message::class;
    }
}
