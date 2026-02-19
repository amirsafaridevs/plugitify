<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Core\DB;
use Plugifity\Core\Database\QueryBuilder;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Model\Log;

/**
 * Repository for error logs: same table as Log (plugifity_logs) with type = 'error'.
 */
class ErrorsRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return Log::class;
    }

    /**
     * Base query scoped to type = 'error'.
     */
    protected function newQuery(): QueryBuilder
    {
        return DB::table($this->getTable())->where('type', 'error');
    }
}
