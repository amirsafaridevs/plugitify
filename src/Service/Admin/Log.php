<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;

/**
 * Admin Log service (uses LogRepository when bound).
 */
class Log extends AbstractService
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
