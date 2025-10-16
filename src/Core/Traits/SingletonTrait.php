<?php

namespace MantraBrain\UltimateWatermark\Core\Traits;

/**
 * Singleton Trait
 * 
 * Provides singleton functionality for classes that need it
 *
 * @package UltimateWatermark
 * @since 2.0.0
 */
trait SingletonTrait
{
    /**
     * Instance of the class
     *
     * @var static|null
     */
    private static $instance = null;

    /**
     * Get instance of the class
     *
     * @return static
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __clone()
    {
        // Prevent cloning
    }

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
