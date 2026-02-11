<?php

namespace Viraloka\Core\Events;

use Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface;

/**
 * Event Dispatcher
 * 
 * Manages event dispatching with listener execution order tracking
 * and non-blocking execution support.
 * 
 * This class delegates to the EventAdapter to remain host-agnostic:
 * - Listener registration order tracking
 * - Execution order guarantees
 * - Non-blocking listener execution
 * 
 * Requirements: 9.1, 9.7
 */
class EventDispatcher
{
    /**
     * Registered listeners with their registration order
     * 
     * @var array<string, array<int, array{callback: callable, priority: int, order: int}>>
     */
    protected array $listeners = [];
    
    /**
     * Global registration counter for tracking order
     * 
     * @var int
     */
    protected int $registrationCounter = 0;
    
    /**
     * Adapter registry for host-agnostic event handling
     * 
     * @var AdapterRegistryInterface|null
     */
    protected ?AdapterRegistryInterface $adapters = null;
    
    /**
     * Create a new EventDispatcher instance
     * 
     * @param AdapterRegistryInterface|null $adapters Optional adapter registry for host-agnostic event handling
     */
    public function __construct(?AdapterRegistryInterface $adapters = null)
    {
        $this->adapters = $adapters;
    }
    
    /**
     * Set the adapter registry
     * 
     * Allows late binding of adapters after EventDispatcher is created.
     * 
     * @param AdapterRegistryInterface $adapters
     * @return void
     */
    public function setAdapters(AdapterRegistryInterface $adapters): void
    {
        $this->adapters = $adapters;
    }
    
    /**
     * Register a listener for an event
     * 
     * @param string $event Event name
     * @param callable $callback Listener callback
     * @param int $priority WordPress priority (lower = earlier)
     * @return void
     */
    public function listen(string $event, callable $callback, int $priority = 10): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        // Track registration order
        $this->listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
            'order' => $this->registrationCounter++
        ];
        
        // Register with EventAdapter if available (Requirement 9.1, 9.7)
        if ($this->adapters !== null) {
            try {
                $this->adapters->event()->listen($event, $callback, $priority);
            } catch (\Throwable $e) {
                // Fallback to direct WordPress if adapter fails
                if (function_exists('add_action')) {
                    add_action($event, $callback, $priority);
                }
            }
        } elseif (function_exists('add_action')) {
            // Fallback to direct WordPress if no adapter available
            add_action($event, $callback, $priority);
        }
    }
    
    /**
     * Dispatch an event to all registered listeners
     * 
     * Executes listeners in registration order within each priority level.
     * Uses non-blocking execution to prevent listeners from halting the system.
     * 
     * @param string $event Event name
     * @param mixed ...$args Arguments to pass to listeners
     * @return void
     */
    public function dispatch(string $event, ...$args): void
    {
        if (!isset($this->listeners[$event])) {
            // Still dispatch via EventAdapter for external listeners (Requirement 9.1, 9.7)
            if ($this->adapters !== null) {
                try {
                    // Create a simple event object for the adapter
                    $eventObject = new class($event, $args) {
                        public function __construct(
                            public readonly string $name,
                            public readonly array $args
                        ) {}
                    };
                    $this->adapters->event()->dispatch($eventObject);
                } catch (\Throwable $e) {
                    // Fallback to direct WordPress if adapter fails
                    if (function_exists('do_action')) {
                        do_action($event, ...$args);
                    }
                }
            } elseif (function_exists('do_action')) {
                // Fallback to direct WordPress if no adapter available
                do_action($event, ...$args);
            }
            return;
        }
        
        // Sort listeners by priority, then by registration order
        $listeners = $this->listeners[$event];
        usort($listeners, function ($a, $b) {
            // First sort by priority (lower = earlier)
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            // Then by registration order
            return $a['order'] <=> $b['order'];
        });
        
        // Execute listeners in non-blocking manner
        foreach ($listeners as $listener) {
            try {
                // Execute listener with error handling to prevent blocking
                call_user_func($listener['callback'], ...$args);
            } catch (\Throwable $e) {
                // Log error but continue with other listeners (non-blocking)
                if (function_exists('error_log')) {
                    error_log(sprintf(
                        'Event listener error for "%s": %s',
                        $event,
                        $e->getMessage()
                    ));
                }
            }
        }
        
        // Also dispatch via EventAdapter for external listeners (Requirement 9.1, 9.7)
        if ($this->adapters !== null) {
            try {
                // Create a simple event object for the adapter
                $eventObject = new class($event, $args) {
                    public function __construct(
                        public readonly string $name,
                        public readonly array $args
                    ) {}
                };
                $this->adapters->event()->dispatch($eventObject);
            } catch (\Throwable $e) {
                // Fallback to direct WordPress if adapter fails
                if (function_exists('do_action')) {
                    do_action($event, ...$args);
                }
            }
        } elseif (function_exists('do_action')) {
            // Fallback to direct WordPress if no adapter available
            do_action($event, ...$args);
        }
    }
    
    /**
     * Get registered listeners for an event
     * 
     * Returns listeners sorted by execution order (priority, then registration order)
     * 
     * @param string $event Event name
     * @return array<array{callback: callable, priority: int, order: int}>
     */
    public function getListeners(string $event): array
    {
        if (!isset($this->listeners[$event])) {
            return [];
        }
        
        $listeners = $this->listeners[$event];
        
        // Sort by priority, then by registration order
        usort($listeners, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return $a['order'] <=> $b['order'];
        });
        
        return $listeners;
    }
    
    /**
     * Check if an event has any listeners
     * 
     * @param string $event Event name
     * @return bool
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && count($this->listeners[$event]) > 0;
    }
    
    /**
     * Remove all listeners for an event
     * 
     * @param string $event Event name
     * @return void
     */
    public function removeListeners(string $event): void
    {
        unset($this->listeners[$event]);
    }
    
    /**
     * Get the total number of registered listeners across all events
     * 
     * @return int
     */
    public function getListenerCount(): int
    {
        $count = 0;
        foreach ($this->listeners as $eventListeners) {
            $count += count($eventListeners);
        }
        return $count;
    }
}
