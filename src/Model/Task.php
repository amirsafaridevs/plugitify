<?php

namespace Plugifity\Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Task model (plugifity_tasks).
 */
class Task extends AbstractModel
{
    public const TABLE = 'plugifity_tasks';

    public ?int $id = null;
    public ?int $chat_id = null;
    public string $title = '';
    public ?string $description = null;
    public string $status = 'pending';
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $deleted_at = null;

    public static function fromRow( object $row ): static
    {
        $model = new static();
        $model->id = isset( $row->id ) ? (int) $row->id : null;
        $model->chat_id = isset( $row->chat_id ) ? (int) $row->chat_id : null;
        $model->title = isset( $row->title ) ? (string) $row->title : '';
        $model->description = isset( $row->description ) ? (string) $row->description : null;
        $model->status = isset( $row->status ) ? (string) $row->status : 'pending';
        $model->created_at = isset( $row->created_at ) ? (string) $row->created_at : null;
        $model->updated_at = isset( $row->updated_at ) ? (string) $row->updated_at : null;
        $model->deleted_at = isset( $row->deleted_at ) ? (string) $row->deleted_at : null;
        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'chat_id'     => $this->chat_id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => $this->status,
            'updated_at'  => $this->updated_at,
            'deleted_at'  => $this->deleted_at,
        ];
        if ( $this->created_at !== null ) {
            $data['created_at'] = $this->created_at;
        }
        return $data;
    }
}
