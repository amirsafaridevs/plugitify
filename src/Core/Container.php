<?php

namespace Plugifity\Core;

use Closure;
use Plugifity\Contract\Interface\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;

/**
 * Dependency Injection Container
 * 
 * A simple yet powerful DI container for managing class dependencies
 * and resolving them automatically.
 */
class Container implements ContainerInterface
{
    /**
     * Container instance (Singleton)
     *
     * @var Container|null
     */
    private static ?Container $instance = null;

    /**
     * Registered bindings
     *
     * @var array
     */
    private array $bindings = [];
    /**
     * Current abstract
     *
     * @var string
     */
    private string $currentAbstract = '';
    /**
     * Current abstract
     *
     * @var array
     */
    private array $currentArguments = [];
    /**
     * Resolved instances (singletons)
     *
     * @var array
     */
    private array $instances = [];

    /**
     * Aliases for bindings
     *
     * @var array
     */
    private array $aliases = [];

    /**
     * Get the container instance (Singleton)
     *
     * @return Container
     */
    public static function getInstance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Bind a class or interface to a concrete implementation
     *
     * @param string $abstract The abstract class or interface
     * @param string|Closure|null $concrete The concrete implementation or closure
     * @param bool $singleton Whether to treat as singleton
     * @return void
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->currentArguments = [];
        $this->currentAbstract = $abstract;

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton,
        ];
        
    }

    /**
     * Bind a singleton instance
     *
     * @param string $abstract The abstract class or interface
     * @param string|Closure|null $concrete The concrete implementation or closure
     * @return void
     */
    public function singleton(string $abstract, $concrete = null): self
    {
        $this->bind($abstract, $concrete, true);
       
        return $this;
    }
    public function addArgument(string $key, $value): self
    {
        $this->currentArguments[$key] = $value;
        $this->bindings[$this->currentAbstract]['arguments'] = $this->currentArguments;
        return $this;
    }

    /**
     * Register an existing instance
     *
     * @param string $abstract The abstract class or interface
     * @param object $instance The instance to register
     * @return void
     */
    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register an alias for a binding
     *
     * @param string $alias The alias name
     * @param string $abstract The abstract class or interface
     * @return void
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }


    /**
     * Get an instance from the container
     *
     * @param string $abstract The abstract class or interface
     * @return object
     */
    public function get(string $abstract): object
    {
        $parameters = $this->bindings[$abstract]['arguments'] ?? [];
        return $this->make($abstract, $parameters);
    }
    /**
     * Resolve a class from the container
     *
     * @param string $abstract The abstract class or interface to resolve
     * @param array $parameters Optional parameters to pass to the constructor
     * @return object
     * @throws \Exception
     */
    public function make(string $abstract, array $parameters = []): object
    {
        $abstract = $this->getAlias($abstract);
        
        // Return existing instance if it's a singleton
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Get the concrete implementation
        $concrete = $this->getConcrete($abstract);

        // If it's a closure, execute it
        if ($concrete instanceof Closure) {
            $object = $concrete($this, $parameters);
        } else {
            // Resolve the class
            $object = $this->build($concrete, $parameters);
        }

        // Auto-inject container into services that have setContainer(ContainerInterface)
        $this->injectContainer($object);

        // Store as singleton if needed
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['singleton']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Inject container into object if it has setContainer(ContainerInterface)
     *
     * @param object $object
     * @return void
     */
    private function injectContainer(object $object): void
    {
        if (!method_exists($object, 'setContainer')) {
            return;
        }

        $reflection = new \ReflectionMethod($object, 'setContainer');
        $params = $reflection->getParameters();

        if ($params === [] || !$reflection->getParameters()[0]->getType()) {
            return;
        }

        $type = $params[0]->getType();
        if ($type && !$type->isBuiltin() && is_a($type->getName(), ContainerInterface::class, true)) {
            $object->setContainer($this);
        }
    }

    /**
     * Get an alias if it exists
     *
     * @param string $abstract
     * @return string
     */
    private function getAlias(string $abstract): string
    {
        return $this->aliases[$abstract] ?? $abstract;
    }

    /**
     * Get the concrete implementation for an abstract
     *
     * @param string $abstract
     * @return string|Closure
     */
    private function getConcrete(string $abstract)
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Build an instance of the given class
     *
     * @param string $concrete
     * @param array $parameters
     * @return object
     * @throws \Exception
     */
    private function build(string $concrete, array $parameters = []): object
    {
        if (!class_exists($concrete)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message
            throw new \Exception("Class {$concrete} does not exist.");
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message
            throw new \Exception("Class {$concrete} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve dependencies for a method
     *
     * @param ReflectionParameter[] $parameters
     * @param array $providedParameters
     * @return array
     * @throws \Exception
     */
    private function resolveDependencies(array $parameters, array $providedParameters = []): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            // If parameter is provided, use it
            if (isset($providedParameters[$parameter->getName()])) {
                $dependencies[] = $providedParameters[$parameter->getName()];
                continue;
            }

            // If parameter has a type hint, try to resolve it
            $type = $parameter->getType();

            if ($type !== null && !$type->isBuiltin()) {
                $typeName = $type->getName();
                try {
                    $dependencies[] = $this->make($typeName);
                    continue;
                } catch (\Exception $e) {
                    // If it's not optional, throw exception
                    if (!$parameter->isOptional()) {
                        throw new \Exception(
                            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message, not user-facing output
                            "Unable to resolve dependency [{$typeName}] for parameter [{$parameter->getName()}]"
                        );
                    }
                }
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
                continue;
            }

            // If parameter is optional, use null
            if ($parameter->isOptional()) {
                $dependencies[] = null;
                continue;
            }

            throw new \Exception(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception message, not user-facing output
                "Unable to resolve dependency [{$parameter->getName()}]"
            );
        }

        return $dependencies;
    }

    /**
     * Check if a binding exists
     *
     * @param string $abstract
     * @return bool
     */
    public function bound(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Clear all bindings and instances
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
    }

    /**
     * Get all bindings
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get all instances
     *
     * @return array
     */
    public function getInstances(): array
    {
        return $this->instances;
    }
}

