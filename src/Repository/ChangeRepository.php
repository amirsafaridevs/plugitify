<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Model\Change;

/**
 * Repository for Change (plugifity_changes).
 */
class ChangeRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return Change::class;
    }
}
