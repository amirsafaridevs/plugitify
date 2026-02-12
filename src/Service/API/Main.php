<?php

namespace Plugifity\Service\API;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\Http\ApiRouter;
use Plugifity\Core\Http\Request;
use Plugifity\Core\Http\Response;
use Plugifity\Core\Settings;

/**
 * API Main service.
 */
class Main extends AbstractService
{
    /**
     * Boot the service.
     *
     * @return void
     */
    public function boot(): void
    {
        ApiRouter::get('/ping', [self::class, 'ping'])->name('api.ping');
        ApiRouter::post('/license-check', [self::class, 'licenseCheck'])->name('api.license.check');
    }

    /**
     * Ping endpoint (GET). Returns pong for health check.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: array}
     */
    public function ping(Request $request): array
    {
        return Response::success(__('Ping successful', 'plugitify'));
    }

    /**
     * License check endpoint (POST). Expects "license" in body/query; returns whether it matches stored license.
     *
     * @param Request $request
     * @return array{success: bool, message: string, data: array}
     */
    public function licenseCheck(Request $request): array
    {
        $license = $request->str('license', '');
        if ($license === '') {
            return Response::error(__('License key is required.', 'plugitify'));
        }

        $storedLicense = Settings::get('license_key', '');
        $valid = trim($license) === trim($storedLicense);

        $message = $valid
            ? __('License is correct and matches the one registered in settings.', 'plugitify')
            : __('License does not match the one registered in settings.', 'plugitify');

        return $valid
            ? Response::success($message)
            : Response::error($message);
    }
}
