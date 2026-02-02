<?php

namespace Plugifity\Repository;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractRepository;
use Plugifity\Model\Error;

/**
 * Repository for plugifity_errors.
 */
class ErrorRepository extends AbstractRepository
{
    protected function getModelClass(): string
    {
        return Error::class;
    }

    /**
     * Get all errors (optionally by code or level).
     *
     * @return Error[]
     */
    public function get( ?string $code = null, ?string $level = null ): array
    {
        $query = $this->newQuery()->orderBy( $this->getOrderColumn(), $this->getOrderDirection() );
        if ( $code !== null ) {
            $query->where( 'code', $code );
        }
        if ( $level !== null ) {
            $query->where( 'level', $level );
        }
        $rows = $query->get();
        $result = [];
        foreach ( $rows as $row ) {
            $result[] = Error::fromRow( $row );
        }
        return $result;
    }
}
