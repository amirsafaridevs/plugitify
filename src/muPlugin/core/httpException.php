<?php
namespace Plugitify\muPlugin\Core;

/**
 * Structured HTTP error. The dispatcher maps it to the error envelope
 * with its HTTP status; any other Throwable stays a plain 500.
 */
class HttpException extends \RuntimeException
{
    private int $status;
    private string $errorCode;
    /** @var array<int, array<string, mixed>> */
    private array $errors;

    /**
     * @param array<int, array<string, mixed>> $errors
     */
    public function __construct(int $status, string $errorCode, array $errors = [], string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
        $this->status    = $status;
        $this->errorCode = $errorCode;
        $this->errors    = $errors;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<int, array<string, mixed>> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
