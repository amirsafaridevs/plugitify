<?php

namespace Plugifity\Model;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Change model (prefix_changes, e.g. plugifity_changes).
 */
class Change extends AbstractModel
{
    public const TABLE = 'changes';

    public ?int $id = null;
    public ?string $type = null;
    public ?string $from_value = null;
    public ?string $to_value = null;
    public ?string $details = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->id = isset($row->id) ? (int) $row->id : null;
        $model->type = isset($row->type) ? (string) $row->type : null;
        $model->from_value = isset($row->from_value) ? (string) $row->from_value : null;
        $model->to_value = isset($row->to_value) ? (string) $row->to_value : null;
        $model->details = isset($row->details) ? (string) $row->details : null;
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
            'from_value' => $this->from_value,
            'to_value'   => $this->to_value,
            'details'    => $this->details,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
