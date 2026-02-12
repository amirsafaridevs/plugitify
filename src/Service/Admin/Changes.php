<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;

/**
 * Admin Changes service (uses ChangesRepository when bound).
 */
class Changes extends AbstractService
{
    /**
     * Boot the service.
     * Use $this->getContainer() when you need the container.
     *
     * @return void
     */
    public function boot(): void
    {
    }
}
