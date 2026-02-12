<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Repository\ApiRequestRepository;
use Plugifity\Repository\ChangeRepository;
use Plugifity\Repository\LogRepository;

/**
 * Admin Dashboard service.
 */
class Dashboard extends AbstractService
{
    /**
     * Boot the service (menu, assets, routes, etc.).
     * Use $this->getContainer() when you need the container.
     *
     * @return void
     */
    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueDashboardAssets']);
    }

    /**
     * Enqueue dashboard assets (CSS, JS) only on dashboard page. Icons are inline SVG in the view (no CDN).
     *
     * @param string $hook_suffix Current admin page hook.
     * @return void
     */
    public function enqueueDashboardAssets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_plugifity') {
            return;
        }

        $app = $this->getApplication();
        $app->enqueueStyle('plugitify-dashboard', 'admin/dashboard.css', [], 'admin_page:plugifity');
        $app->enqueueScript('plugitify-dashboard', 'admin/dashboard.js', [], true, 'admin_page:plugifity');
    }

    /**
     * Register the main Plugifity menu in admin.
     *
     * @return void
     */
    public function registerMenu(): void
    {
        add_menu_page(
            __('Plugifity', 'plugitify'),
            __('Plugifity', 'plugitify'),
            'manage_options',
            'plugifity',
            [$this, 'renderDashboard'],
            'dashicons-admin-generic',
            30
        );
    }

    /**
     * Render the dashboard page (4 stat boxes + last 20 logs table).
     * Uses Application::view().
     *
     * @return void
     */
    public function renderDashboard(): void
    {
        $app = $this->getApplication();
        $logRepo = $this->container->get(LogRepository::class);
        $apiRequestRepo = $this->container->get(ApiRequestRepository::class);
        $changeRepo = $this->container->get(ChangeRepository::class);

        $stats = [
            'total_logs' => $logRepo->query()->count(),
            'total_api_requests' => $apiRequestRepo->query()->count(),
            'total_changes' => $changeRepo->query()->count(),
            'total_errors' => 0, // Placeholder until ErrorsRepository exists
        ];

        $logs = $logRepo->query()
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();

        $app->view('Dashboard/index', [
            'stats' => $stats,
            'logs' => $logs,
        ]);
    }
}
