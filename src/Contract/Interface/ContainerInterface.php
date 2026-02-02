<?php

namespace Plugifity\Contract\Interface;

use Closure;

/**
 * Container Interface
 * 
 * Contract for dependency injection container implementations
 */
interface ContainerInterface
{
    /**
     * Bind a class or interface to a concrete implementation
     *
     * @param string $abstract The abstract class or interface
     * @param string|Closure|null $concrete The concrete implementation or closure
     * @param bool $singleton Whether to treat as singleton
     * @return void
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void;

    /**
     * Bind a singleton instance
     *
     * @param string $abstract The abstract class or interface
     * @param string|Closure|null $concrete The concrete implementation or closure
     * @return void
     */
    public function singleton(string $abstract, $concrete = null): self;


    /**
     * Add an argument to the current abstract
     *
     * @param string $key The key of the argument
     * @param mixed $value The value of the argument
     * @return self
     */
    public function addArgument(string $key, $value): self;
    /**
     * Register an existing instance
     *
     * @param string $abstract The abstract class or interface
     * @param object $instance The instance to register
     * @return void
     */
    public function instance(string $abstract, object $instance): void;

    /**
     * Register an alias for a binding
     *
     * @param string $alias The alias name
     * @param string $abstract The abstract class or interface
     * @return void
     */
    public function alias(string $alias, string $abstract): void;


    /**
     * Get an instance from the container
     *
     * @param string $abstract The abstract class or interface
     * @return object
     */
    public function get(string $abstract): object;
    /**
     * Resolve a class from the container
     *
     * @param string $abstract The abstract class or interface to resolve
     * @param array $parameters Optional parameters to pass to the constructor
     * @return object
     * @throws \Exception
     */
    public function make(string $abstract, array $parameters = []): object;

    /**
     * Check if a binding exists
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool;

    /**
     * Clear all bindings and instances
     *
     * @return void
     */
    public function flush(): void;

    /**
     * Get all bindings
     *
     * @return array
     */
    public function getBindings(): array;

    /**
     * Get all instances
     *
     * @return array
     */
    public function getInstances(): array;
}

