<?php

namespace Viraloka\Core\Adapter\Contracts;

/**
 * Event adapter for event dispatching and listening.
 */
interface EventAdapterInterface
{
    /**
     * Dispatch an event.
     *
     * @param object|string $event Event object or event name
     * @param array $data Optional data array (when using string event names)
     * @return object|array The event (possibly modified by listeners)
     */
    public function dispatch(object|string $event, array $data = []): object|array;

    /**
     * Register an event listener.
     *
     * @param string $eventClass Fully qualified event class name
     * @param callable $handler Event handler
     * @param int $priority Priority (lower = earlier, default 10)
     */
    public function listen(string $eventClass, callable $handler, int $priority = 10): void;

    /**
     * Remove an event listener.
     *
     * @param string $eventClass Event class name
     * @param callable $handler Handler to remove
     */
    public function remove(string $eventClass, callable $handler): void;

    /**
     * Check if an event has listeners.
     *
     * @param string $eventClass Event class name
     * @return bool
     */
    public function hasListeners(string $eventClass): bool;

    /**
     * Get all listeners for an event.
     *
     * @param string $eventClass Event class name
     * @return array<callable> List of handlers
     */
    public function getListeners(string $eventClass): array;
}
