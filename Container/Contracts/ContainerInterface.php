<?php

namespace Viraloka\Core\Container\Contracts;

use Viraloka\Core\Container\Exceptions\ContainerException;
use Viraloka\Core\Container\Exceptions\ServiceNotFoundException;

/**
 * Container interface for dependency injection and service resolution.
 */
interface ContainerInterface
{
    /**
     * Register a factory binding.
     *
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @param array $options Additional options (tags, lazy, etc.)
     * @return void
     */
    public function bind(string $id, callable|string $resolver, array $options = []): void;

    /**
     * Register a singleton binding.
     *
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @return void
     */
    public function singleton(string $id, callable|string $resolver): void;

    /**
     * Register a scoped binding (context/workspace aware).
     *
     * @param string $id The service identifier
     * @param callable $resolver The resolver callable
     * @return void
     */
    public function scoped(string $id, callable $resolver): void;

    /**
     * Check if a binding exists.
     *
     * @param string $id The service identifier
     * @return bool True if the binding exists, false otherwise
     */
    public function has(string $id): bool;

    /**
     * Resolve a service.
     *
     * @param string $id The service identifier
     * @return mixed The resolved service instance
     * @throws ServiceNotFoundException If the service is not found
     * @throws ContainerException If the service cannot be resolved
     */
    public function get(string $id): mixed;

    /**
     * Register an existing instance.
     *
     * @param string $id The service identifier
     * @param object $instance The instance to register
     * @return void
     */
    public function instance(string $id, object $instance): void;

    /**
     * Create an alias for a binding.
     *
     * @param string $alias The alias identifier
     * @param string $id The original service identifier
     * @return void
     */
    public function alias(string $alias, string $id): void;

    /**
     * Get all services with a given tag.
     *
     * @param string $tag The tag name
     * @return array Array of resolved service instances
     * @throws ContainerException If any tagged service cannot be resolved
     */
    public function tagged(string $tag): array;
}
