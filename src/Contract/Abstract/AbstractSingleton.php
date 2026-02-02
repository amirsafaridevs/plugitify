<?php

namespace Plugifity\Contract\Abstract;

/**
 * Singleton Abstract Class
 * 
 * Base class for implementing singleton pattern
 */
abstract class AbstractSingleton
{
    /**
     * Singleton instance
     *
     * @var static|null
     */
    protected static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return static
     */
    public static function get(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Prevent cloning
     *
     * @return void
     */
    protected function __clone()
    {
        // Prevent cloning
    }

    /**
     * Prevent unserialization
     *
     * @return void
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}