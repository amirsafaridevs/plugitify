<?php

namespace Plugifity\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Request-scoped context (e.g. X-Chat-Id from current API request).
 * Set in ApiRouter for tool requests; read in RecordBuffer when persisting logs/changes/api_requests.
 */
class RequestContext
{
    /** @var string|null */
    private static ?string $chatId = null;

    /**
     * Set the current request's chat ID (e.g. from X-Chat-Id header).
     *
     * @param string|null $chatId
     * @return void
     */
    public static function setChatId(?string $chatId): void
    {
        $chatId = $chatId !== null ? trim($chatId) : null;
        self::$chatId = $chatId !== '' ? $chatId : null;
    }

    /**
     * Get the current request's chat ID (raw string).
     *
     * @return string|null
     */
    public static function getChatId(): ?string
    {
        return self::$chatId;
    }

    /**
     * Get chat ID for DB storage: integer if value is numeric, otherwise null (tables use int column).
     *
     * @return int|null
     */
    public static function getChatIdForStorage(): ?int
    {
        $id = self::$chatId;
        if ($id === null || $id === '') {
            return null;
        }
        if (is_numeric($id)) {
            return (int) $id;
        }
        return null;
    }

    /**
     * Clear context (call at end of request handling).
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$chatId = null;
    }
}
