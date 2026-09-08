<?php
namespace Plugitify\muPlugin;

// Plugin classes (Plugitify\*) are PSR-4 autoloaded; the mu-plugin runs
// before the main plugin loads, so load the autoloader here.
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

require_once __DIR__ . '/core/registerRoute.php';
require_once __DIR__ . '/core/registerController.php';
require_once __DIR__ . '/core/view.php';
require_once __DIR__ . '/core/pluginWorkspace.php';
require_once __DIR__ . '/core/agentTools.php';

use Plugitify\muPlugin\Core\RegisterRoute;
use Plugitify\muPlugin\Core\RegisterController;

class MuPlugin
{
    private static ?MuPlugin $instance = null;

    public static function getInstance(): MuPlugin
    {
        if (self::$instance === null) {
            self::$instance = new MuPlugin();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->init();
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }

    private function init(): void
    {
        RegisterController::load_all();
        $this->register_routes();
        $this->register_dispatcher();
    }

    private function register_routes(): void
    {
        require_once __DIR__ . '/route/route.php';
    }

    private function register_dispatcher(): void
    {
        if (RegisterRoute::dispatch()) {
            exit;
        }
    }
}
