<?php

namespace Plugifity\Model;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * MessageMeta model (prefix_message_meta, e.g. plugifity_message_meta).
 */
class MessageMeta extends AbstractModel
{
    public const TABLE = 'message_meta';

    protected bool $timestamps = false;

    public ?int $id = null;
    public ?int $message_id = null;
    public ?string $meta_key = null;
    public ?string $meta_value = null;

    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->id = isset($row->id) ? (int) $row->id : null;
        $model->message_id = isset($row->message_id) ? (int) $row->message_id : null;
        $model->meta_key = isset($row->meta_key) ? (string) $row->meta_key : null;
        $model->meta_value = isset($row->meta_value) ? (string) $row->meta_value : null;
        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'message_id' => $this->message_id,
            'meta_key'   => $this->meta_key,
            'meta_value' => $this->meta_value,
        ];
    }
}
