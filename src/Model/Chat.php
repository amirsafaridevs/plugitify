<?php

namespace Plugifity\Model;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Chat model (prefix_chats, e.g. plugifity_chats).
 */
class Chat extends AbstractModel
{
    public const TABLE = 'chats';

    public ?int $id = null;
    public ?string $title = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->id = isset($row->id) ? (int) $row->id : null;
        $model->title = isset($row->title) ? (string) $row->title : null;
        $model->created_at = isset($row->created_at) ? (string) $row->created_at : null;
        $model->updated_at = isset($row->updated_at) ? (string) $row->updated_at : null;
        $model->deleted_at = isset($row->deleted_at) ? (string) $row->deleted_at : null;
        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
