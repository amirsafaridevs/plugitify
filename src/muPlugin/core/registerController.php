<?php
namespace Plugitify\muPlugin\Core;

/**
 * Auto-loads every controller file under src/muPlugin/controller/
 * so new controllers don't need a manual require_once in muPlugin.php.
 */
class RegisterController
{
    public static function load_all(): void
    {
        $controller_dir = dirname(__DIR__) . '/controller';

        foreach (glob($controller_dir . '/*.php') ?: [] as $file) {
            require_once $file;
        }
    }
}
