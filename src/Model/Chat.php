<?php

namespace Plugifity\Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Chat model (plugifity_chats).
 */
class Chat extends AbstractModel
{
    public const TABLE = 'plugifity_chats';

    public ?int $id = null;
    public ?string $title = null;
    public string $status = 'active';
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromRow( object $row ): static
    {
        $model = new static();
        $model->id = isset( $row->id ) ? (int) $row->id : null;
        $model->title = isset( $row->title ) ? (string) $row->title : null;
        $model->status = isset( $row->status ) ? (string) $row->status : 'active';
        $model->created_at = isset( $row->created_at ) ? (string) $row->created_at : null;
        $model->updated_at = isset( $row->updated_at ) ? (string) $row->updated_at : null;
        return $model;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'title'       => $this->title,
            'status'      => $this->status,
            'updated_at'  => $this->updated_at,
        ];
        if ( $this->created_at !== null ) {
            $data['created_at'] = $this->created_at;
        }
        return $data;
    }
}
