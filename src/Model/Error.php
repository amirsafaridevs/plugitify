<?php

namespace Plugifity\Model;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * Error model (plugifity_errors).
 */
class Error extends AbstractModel
{
    public const TABLE = 'plugifity_errors';

    public ?int $id = null;
    public string $message = '';
    public ?string $context = null;
    public ?string $code = null;
    public ?string $level = null;
    public ?string $file = null;
    public ?int $line = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromRow( object $row ): static
    {
        $model = new static();
        $model->id = isset( $row->id ) ? (int) $row->id : null;
        $model->message = isset( $row->message ) ? (string) $row->message : '';
        $model->context = isset( $row->context ) ? (string) $row->context : null;
        $model->code = isset( $row->code ) ? (string) $row->code : null;
        $model->level = isset( $row->level ) ? (string) $row->level : null;
        $model->file = isset( $row->file ) ? (string) $row->file : null;
        $model->line = isset( $row->line ) ? (int) $row->line : null;
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
            'message'     => $this->message,
            'context'     => $this->context,
            'code'        => $this->code,
            'level'       => $this->level,
            'file'        => $this->file,
            'line'        => $this->line,
            'updated_at'  => $this->updated_at,
        ];
        if ( $this->created_at !== null ) {
            $data['created_at'] = $this->created_at;
        }
        return $data;
    }
}
