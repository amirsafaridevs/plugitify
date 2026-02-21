<?php

namespace Plugifity\Repository;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Contract\Interface\ModelInterface;
use Plugifity\Model\Log;

/**
 * Repository for Log (plugifity_logs).
 */
class LogRepository extends AbstractRepository
{
    /**
     * @return class-string<ModelInterface>
     */
    protected function getModelClass(): string
    {
        return Log::class;
    }

    /**
     * Last N logs for a chat (newest first in DB, returned oldest-first for task context).
     *
     * @param int $chatId
     * @param int $limit
     * @return list<string> Each item e.g. "[type] message"
     */
    public function getLastTaskHistoryForChat(int $chatId, int $limit = 20): array
    {
        $rows = $this->query()
            ->where('chat_id', $chatId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
        $out = [];
        foreach (array_reverse($rows) as $model) {
            $type = $model->type !== null ? $model->type : 'info';
            $msg = $model->message !== null ? $model->message : '';
            $out[] = '[' . $type . '] ' . $msg;
        }
        return $out;
    }
}
