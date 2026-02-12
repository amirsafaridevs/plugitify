<?php

namespace Plugifity\Core\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Simple paginator result (Laravel-style).
 *
 * @template T
 */
class Paginator
{
    /**
     * @param array<int, T> $items
     * @param int           $total
     * @param int           $currentPage
     * @param int           $perPage
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $currentPage,
        public int $perPage,
    ) {
    }

    /**
     * Last page number.
     */
    public function lastPage(): int
    {
        return $this->perPage > 0 ? (int) ceil( $this->total / $this->perPage ) : 1;
    }

    /**
     * Whether there are more pages.
     */
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage();
    }

    /**
     * Whether there is a previous page.
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Previous page number.
     */
    public function previousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * Next page number.
     */
    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    /**
     * From item index (1-based for display).
     */
    public function from(): int
    {
        return $this->total === 0 ? 0 : ( $this->currentPage - 1 ) * $this->perPage + 1;
    }

    /**
     * To item index (1-based for display).
     */
    public function to(): int
    {
        return (int) min( $this->currentPage * $this->perPage, $this->total );
    }
}
