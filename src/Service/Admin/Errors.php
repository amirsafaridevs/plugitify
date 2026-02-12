<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;

/**
 * Admin Errors service (uses ErrorsRepository when bound).
 */
class Errors extends AbstractService
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
