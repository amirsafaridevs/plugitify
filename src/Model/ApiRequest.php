<?php

namespace Plugifity\Model;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractModel;

/**
 * ApiRequest model (prefix_api_requests, e.g. plugifity_api_requests).
 */
class ApiRequest extends AbstractModel
{
    public const TABLE = 'api_requests';

    public ?int $id = null;
    public ?string $url = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $from = null;
    public ?string $details = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    public static function fromRow(object $row): static
    {
        $model = new static();
        $model->id = isset($row->id) ? (int) $row->id : null;
        $model->url = isset($row->url) ? (string) $row->url : null;
        $model->title = isset($row->title) ? (string) $row->title : null;
        $model->description = isset($row->description) ? (string) $row->description : null;
        $model->from = isset($row->from_source) ? (string) $row->from_source : null;
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
            'id'           => $this->id,
            'url'          => $this->url,
            'title'        => $this->title,
            'description'  => $this->description,
            'from_source'  => $this->from,
            'details'      => $this->details,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
