<?php

namespace Plugifity\Helper;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractSingleton;
use Plugifity\Core\Application;
use Plugifity\Core\RequestContext;
use Plugifity\Core\DB;
use Plugifity\Repository\ApiRequestRepository;
use Plugifity\Repository\ChangeRepository;
use Plugifity\Repository\LogRepository;

/**
 * Helper for buffered recording of logs, API requests, and changes.
 * Use add*() methods during execution, then call save() once to persist all in a single transaction.
 */
class RecordBuffer extends AbstractSingleton
{
    /** @var static|null */
    protected static ?self $instance = null;

    /**
     * Pending log entries (type, message, context).
     *
     * @var array<int, array{type: string, message: string, context: string|null}>
     */
    protected array $logs = [];

    /**
     * Pending API request entries.
     *
     * @var array<int, array{url: string, title: string|null, description: string|null, from: string|null, details: string|null}>
     */
    protected array $apiRequests = [];

    /**
     * Pending change entries.
     *
     * @var array<int, array{type: string, from_value: string|null, to_value: string|null, details: string|null}>
     */
    protected array $changes = [];

    /**
     * Add a log entry to the buffer.
     *
     * @param string      $type    Log type (e.g. 'info', 'error', 'debug').
     * @param string      $message Log message.
     * @param string|null $context Optional context (e.g. JSON).
     * @return static
     */
    public function addLog(string $type, string $message, ?string $context = null): static
    {
        $this->logs[] = [
            'type'    => $type,
            'message' => $message,
            'context' => $context,
        ];
        return $this;
    }

    /**
     * Add an API request entry to the buffer.
     *
     * @param string      $url         Request URL.
     * @param string|null $title       Optional title.
     * @param string|null $description Optional description.
     * @param string|null $from        Optional source (from_source).
     * @param string|null $details     Optional details (e.g. JSON).
     * @return static
     */
    public function addApiRequest(
        string $url,
        ?string $title = null,
        ?string $description = null,
        ?string $from = null,
        ?string $details = null
    ): static {
        $this->apiRequests[] = [
            'url'          => $url,
            'title'        => $title,
            'description'  => $description,
            'from'         => $from,
            'details'      => $details,
        ];
        return $this;
    }

    /**
     * Add a change entry to the buffer.
     *
     * @param string      $type      Change type.
     * @param string|null $fromValue Previous value.
     * @param string|null $toValue   New value.
     * @param string|null $details   Optional details (e.g. JSON).
     * @return static
     */
    public function addChange(
        string $type,
        ?string $fromValue = null,
        ?string $toValue = null,
        ?string $details = null
    ): static {
        $this->changes[] = [
            'type'       => $type,
            'from_value' => $fromValue,
            'to_value'   => $toValue,
            'details'    => $details,
        ];
        return $this;
    }

    /**
     * Persist all buffered logs, API requests, and changes in one transaction.
     * Clears buffers on success.
     *
     * @return bool True if all saved successfully (or nothing to save), false on failure.
     */
    public function save(): bool
    {
        if ($this->isEmpty()) {
            return true;
        }

        $app = Application::get();
        /** @var LogRepository $logRepo */
        $logRepo = $app->make(LogRepository::class);
        /** @var ApiRequestRepository $apiRequestRepo */
        $apiRequestRepo = $app->make(ApiRequestRepository::class);
        /** @var ChangeRepository $changeRepo */
        $changeRepo = $app->make(ChangeRepository::class);

        $chatId = RequestContext::getChatIdForStorage();

        $started = DB::beginTransaction();

        try {
            foreach ($this->logs as $item) {
                $data = [
                    'type'    => $item['type'],
                    'message' => $item['message'],
                    'context' => $item['context'],
                ];
                if ($chatId !== null) {
                    $data['chat_id'] = $chatId;
                }
                $logRepo->create($data);
            }

            foreach ($this->apiRequests as $item) {
                $data = [
                    'url'          => $item['url'],
                    'title'        => $item['title'],
                    'description'  => $item['description'],
                    'from_source'  => $item['from'],
                    'details'      => $item['details'],
                ];
                if ($chatId !== null) {
                    $data['chat_id'] = $chatId;
                }
                $apiRequestRepo->create($data);
            }

            foreach ($this->changes as $item) {
                $data = [
                    'type'       => $item['type'],
                    'from_value' => $item['from_value'],
                    'to_value'   => $item['to_value'],
                    'details'    => $item['details'],
                ];
                if ($chatId !== null) {
                    $data['chat_id'] = $chatId;
                }
                $changeRepo->create($data);
            }

            if ($started) {
                DB::commit();
            }
            $this->clear();
            return true;
        } catch (\Throwable $e) {
            if ($started) {
                DB::rollBack();
            }
            if (function_exists('wp_die')) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log('RecordBuffer::save failed: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Clear all buffers without saving.
     *
     * @return static
     */
    public function clear(): static
    {
        $this->logs        = [];
        $this->apiRequests = [];
        $this->changes     = [];
        return $this;
    }

    /**
     * Check if there is nothing to save.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->logs === [] && $this->apiRequests === [] && $this->changes === [];
    }

    /**
     * Get counts of buffered items (for debugging).
     *
     * @return array{logs: int, api_requests: int, changes: int}
     */
    public function counts(): array
    {
        return [
            'logs'         => count($this->logs),
            'api_requests' => count($this->apiRequests),
            'changes'      => count($this->changes),
        ];
    }
}
