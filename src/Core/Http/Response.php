<?php

namespace Plugifity\Core\Http;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unified API response structure: success, message, data.
 * Use for all API endpoints so responses are consistent.
 */
class Response
{
    /** @var bool */
    private bool $success;

    /** @var string */
    private string $message;

    /** @var mixed */
    private $data;

    /**
     * @param bool   $success
     * @param string $message
     * @param mixed  $data
     */
    public function __construct(bool $success, string $message = '', $data = null)
    {
        $this->success = $success;
        $this->message = $message;
        $this->data    = $data;
    }

    /**
     * Success response.
     *
     * @param string $message
     * @param mixed  $data
     * @return array{success: bool, message: string, data: mixed}
     */
    public static function success(string $message = '', $data = null): array
    {
        return (new self(true, $message, $data))->toArray();
    }

    /**
     * Error response.
     *
     * @param string $message
     * @param mixed  $data
     * @return array{success: bool, message: string, data: mixed}
     */
    public static function error(string $message = '', $data = null): array
    {
        return (new self(false, $message, $data))->toArray();
    }

    /**
     * Return array for REST API (rest_ensure_response).
     *
     * @return array{success: bool, message: string, data: mixed}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data'    => $this->data,
        ];
    }
}
