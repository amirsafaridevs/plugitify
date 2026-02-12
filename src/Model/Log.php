<?php

namespace Plugifity\Model;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Log model (prefix_logs, e.g. plugifity_logs).
 */
class Log extends AbstractModel
{
    public const TABLE = 'logs';

    public ?int $id = null;
    public ?string $type = null;
    public ?string $message = null;
    public ?string $context = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->id = isset($row->id) ? (int) $row->id : null;
        $model->type = isset($row->type) ? (string) $row->type : null;
        $model->message = isset($row->message) ? (string) $row->message : null;
        $model->context = isset($row->context) ? (string) $row->context : null;
        $model->created_at = isset($row->created_at) ? (string) $row->created_at : null;
        $model->updated_at = isset($row->updated_at) ? (string) $row->updated_at : null;
        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type,
            'message'    => $this->message,
            'context'    => $this->context,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
