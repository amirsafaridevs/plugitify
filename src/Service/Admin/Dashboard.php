<?php

namespace Plugifity\Service\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use Plugifity\Contract\Abstract\AbstractService;
use Plugifity\Core\DB;
use Plugifity\Repository\ApiRequestRepository;
use Plugifity\Repository\ChangeRepository;
use Plugifity\Repository\ErrorsRepository;
use Plugifity\Repository\LogRepository;

/**
 * Admin Dashboard service.
 */
class Dashboard extends AbstractService
{
    public const PER_PAGE = 15;

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
     * Enqueue dashboard assets (CSS, JS) on dashboard and list pages. Icons are inline SVG in the view (no CDN).
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
     * Register the main Plugifity menu (single page: dashboard + list views via ?view=).
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

        add_submenu_page(
            'plugifity',
            __('Dashboard', 'plugitify'),
            __('Dashboard', 'plugitify'),
            'manage_options',
            'plugifity',
            [$this, 'renderDashboard']
        );
    }

    /**
     * Render the dashboard page or list view (4 stat boxes + last 20 logs, or list by view=).
     * Uses Application::view(). Same page slug (plugifity), list views via ?view=logs|api_requests|changes|errors.
     *
     * @return void
     */
    public function renderDashboard(): void
    {
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : '';

        $valid_views = ['logs', 'api_requests', 'changes', 'errors'];
        if ($view !== '' && in_array($view, $valid_views, true)) {
            $this->renderListView($view);
            return;
        }

        $app = $this->getApplication();
        $logRepo = $this->container->get(LogRepository::class);
        $apiRequestRepo = $this->container->get(ApiRequestRepository::class);
        $changeRepo = $this->container->get(ChangeRepository::class);
        $errorsRepo = $this->container->get(ErrorsRepository::class);

        $stats = [
            'total_logs' => $logRepo->query()->count(),
            'total_api_requests' => $apiRequestRepo->query()->count(),
            'total_changes' => $changeRepo->query()->count(),
            'total_errors' => $errorsRepo->query()->count(),
        ];

        $logs = $logRepo->query()
            ->orderBy('created_at', 'DESC')
            ->limit(20)
            ->get();

        $view_more_logs = admin_url('admin.php?page=plugifity&view=logs');
        $view_more_api = admin_url('admin.php?page=plugifity&view=api_requests');
        $view_more_changes = admin_url('admin.php?page=plugifity&view=changes');
        $view_more_errors = admin_url('admin.php?page=plugifity&view=errors');

        $app->view('Dashboard/index', [
            'stats' => $stats,
            'logs' => $logs,
            'view_more_logs' => $view_more_logs,
            'view_more_api' => $view_more_api,
            'view_more_changes' => $view_more_changes,
            'view_more_errors' => $view_more_errors,
        ]);
    }

    /**
     * Render list view by section (logs, api_requests, changes, errors).
     *
     * @param string $view One of: logs, api_requests, changes, errors.
     * @return void
     */
    protected function renderListView(string $view): void
    {
        $titles = [
            'logs' => __('Total Logs', 'plugitify'),
            'api_requests' => __('API Requests', 'plugitify'),
            'changes' => __('Changes', 'plugitify'),
            'errors' => __('Errors', 'plugitify'),
        ];
        $search_columns = [
            'logs' => 'message',
            'api_requests' => 'title',
            'changes' => 'details',
            'errors' => 'message',
        ];
        $repos = [
            'logs' => LogRepository::class,
            'api_requests' => ApiRequestRepository::class,
            'changes' => ChangeRepository::class,
            'errors' => ErrorsRepository::class,
        ];

        $repo = $this->container->get($repos[ $view ]);
        $this->renderListPage($view, $titles[ $view ], $repo, $search_columns[ $view ]);
    }

    /**
     * Generic list page: pagination, search, single view.
     *
     * @param string $section Section key (logs, api_requests, changes, errors).
     * @param string $pageTitle Page title.
     * @param \Plugifity\Contract\Abstract\AbstractRepository $repo Repository instance.
     * @param string $searchColumn Column to search in (LIKE).
     * @return void
     */
    protected function renderListPage(string $section, string $pageTitle, $repo, string $searchColumn): void
    {
        $app = $this->getApplication();
        $page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';

        $query = $repo->query()->orderBy('created_at', 'DESC');

        if ($search !== '') {
            $like = '%' . DB::connection()->esc_like($search) . '%';
            $query->where($searchColumn, 'LIKE', $like);
        }

        $paginator = $query->paginate(self::PER_PAGE, $page);

        $app->view('List/index', [
            'section' => $section,
            'pageTitle' => $pageTitle,
            'items' => $paginator->items,
            'paginator' => $paginator,
            'search' => $search,
        ]);
    }
}
