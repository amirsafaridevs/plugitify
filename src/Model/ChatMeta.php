<?php

namespace Plugifity\Model;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * ChatMeta model (prefix_chat_meta, e.g. plugifity_chat_meta).
 */
class ChatMeta extends AbstractModel
{
    public const TABLE = 'chat_meta';

    protected bool $timestamps = false;

    public ?int $id = null;
    public ?int $chat_id = null;
    public ?string $meta_key = null;
    public ?string $meta_value = null;

    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->id = isset($row->id) ? (int) $row->id : null;
        $model->chat_id = isset($row->chat_id) ? (int) $row->chat_id : null;
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
            'chat_id'    => $this->chat_id,
            'meta_key'   => $this->meta_key,
            'meta_value' => $this->meta_value,
        ];
    }
}
