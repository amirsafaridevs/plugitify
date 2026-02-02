<?php

namespace Plugifity\Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Message model (plugifity_messages).
 */
class Message extends AbstractModel
{
    public const TABLE = 'plugifity_messages';

    public ?int $id = null;
    public int $chat_id;
    public string $role = '';
    public ?string $content = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromRow( object $row ): static
    {
        $model = new static();
        $model->id = isset( $row->id ) ? (int) $row->id : null;
        $model->chat_id = isset( $row->chat_id ) ? (int) $row->chat_id : 0;
        $model->role = isset( $row->role ) ? (string) $row->role : '';
        $model->content = isset( $row->content ) ? (string) $row->content : null;
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
            'chat_id'     => $this->chat_id,
            'role'        => $this->role,
            'content'     => $this->content,
            'updated_at'  => $this->updated_at,
        ];
        if ( $this->created_at !== null ) {
            $data['created_at'] = $this->created_at;
        }
        return $data;
    }
}
