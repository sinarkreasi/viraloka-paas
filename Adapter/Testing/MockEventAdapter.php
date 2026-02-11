<?php

namespace Viraloka\Adapter\Testing;

use Viraloka\Core\Adapter\Contracts\EventAdapterInterface;

/**
 * Mock event adapter for testing Core in isolation.
 * 
 * This adapter provides in-memory event listener tracking without any external dependencies,
 * allowing Core to be tested without WordPress or other host environments.
 */
class MockEventAdapter implements EventAdapterInterface
{
    /**
     * @var array<string, array<int, array<callable>>>
     */
    private array $listeners = [];

    /**
     * @var array<object|array>
     */
    private array $dispatchedEvents = [];

    /**
     * Dispatch an event.
     *
     * @param object|string $event Event object or event name
     * @param array $data Optional data array (when using string event names)
     * @return object|array The event (possibly modified by listeners)
     */
    public function dispatch(object|string $event, array $data = []): object|array
    {
        // Handle string event names
        if (is_string($event)) {
            $eventClass = $event;
            $this->dispatchedEvents[] = ['name' => $event, 'data' => $data];
            
            if (!isset($this->listeners[$eventClass])) {
                return $data;
            }

            // Sort listeners by priority (lower = earlier)
            ksort($this->listeners[$eventClass]);

            foreach ($this->listeners[$eventClass] as $priority => $handlers) {
                foreach ($handlers as $handler) {
                    $result = $handler($data);
                    // If handler returns data, use it
                    if ($result !== null) {
                        $data = $result;
                    }
                }
            }

            return $data;
        }
        
        // Handle event objects
        $eventClass = get_class($event);
        $this->dispatchedEvents[] = $event;

        if (!isset($this->listeners[$eventClass])) {
            return $event;
        }

        // Sort listeners by priority (lower = earlier)
        ksort($this->listeners[$eventClass]);

        foreach ($this->listeners[$eventClass] as $priority => $handlers) {
            foreach ($handlers as $handler) {
                // Check if event has stopPropagation method and if it's been called
                if (method_exists($event, 'isPropagationStopped') && $event->isPropagationStopped()) {
                    return $event;
                }

                $handler($event);
            }
        }

        return $event;
    }

    /**
     * Register an event listener.
     *
     * @param string $eventClass Fully qualified event class name
     * @param callable $handler Event handler
     * @param int $priority Priority (lower = earlier, default 10)
     */
    public function listen(string $eventClass, callable $handler, int $priority = 10): void
    {
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }

        if (!isset($this->listeners[$eventClass][$priority])) {
            $this->listeners[$eventClass][$priority] = [];
        }

        $this->listeners[$eventClass][$priority][] = $handler;
    }

    /**
     * Remove an event listener.
     *
     * @param string $eventClass Event class name
     * @param callable $handler Handler to remove
     */
    public function remove(string $eventClass, callable $handler): void
    {
        if (!isset($this->listeners[$eventClass])) {
            return;
        }

        foreach ($this->listeners[$eventClass] as $priority => $handlers) {
            foreach ($handlers as $key => $registeredHandler) {
                if ($registeredHandler === $handler) {
                    unset($this->listeners[$eventClass][$priority][$key]);
                    
                    // Clean up empty priority arrays
                    if (empty($this->listeners[$eventClass][$priority])) {
                        unset($this->listeners[$eventClass][$priority]);
                    }
                    
                    // Clean up empty event class arrays
                    if (empty($this->listeners[$eventClass])) {
                        unset($this->listeners[$eventClass]);
                    }
                    
                    return;
                }
            }
        }
    }

    /**
     * Check if an event has listeners.
     *
     * @param string $eventClass Event class name
     * @return bool
     */
    public function hasListeners(string $eventClass): bool
    {
        return isset($this->listeners[$eventClass]) && !empty($this->listeners[$eventClass]);
    }

    /**
     * Get all listeners for an event.
     *
     * @param string $eventClass Event class name
     * @return array<callable> List of handlers
     */
    public function getListeners(string $eventClass): array
    {
        if (!isset($this->listeners[$eventClass])) {
            return [];
        }

        $allHandlers = [];
        ksort($this->listeners[$eventClass]);
        
        foreach ($this->listeners[$eventClass] as $handlers) {
            $allHandlers = array_merge($allHandlers, $handlers);
        }

        return $allHandlers;
    }

    /**
     * Get all dispatched events (for testing purposes).
     *
     * @return array<object|array>
     */
    public function getDispatchedEvents(): array
    {
        return $this->dispatchedEvents;
    }

    /**
     * Clear all dispatched events (for testing purposes).
     */
    public function clearDispatchedEvents(): void
    {
        $this->dispatchedEvents = [];
    }

    /**
     * Clear all listeners (for testing purposes).
     */
    public function clearListeners(): void
    {
        $this->listeners = [];
    }
}

