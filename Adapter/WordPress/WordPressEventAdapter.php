<?php

namespace Viraloka\Adapter\WordPress;

use Viraloka\Core\Adapter\Contracts\EventAdapterInterface;

/**
 * WordPress implementation of the Event Adapter.
 * 
 * Bridges Core events to WordPress hooks (add_action, do_action).
 * Converts event class names to WordPress hook names.
 */
class WordPressEventAdapter implements EventAdapterInterface
{
    /**
     * Track registered listeners for removal.
     *
     * @var array<string, array<int, array{handler: callable, priority: int}>>
     */
    private array $listeners = [];

    /**
     * Dispatch an event.
     *
     * @param object|string $event Event object or event name
     * @param array $data Optional data array (when using string event names)
     * @return object|array The event (possibly modified by listeners)
     */
    public function dispatch(object|string $event, array $data = []): object|array
    {
        // Handle string event names (WordPress-style)
        if (is_string($event)) {
            $hookName = $event;
            
            // Special handling for admin menu registration
            if ($hookName === 'viraloka_register_admin_menu' && isset($data['pages'])) {
                $this->registerAdminMenuPages($data['pages']);
                return $data;
            }
            
            // Call WordPress action with data
            do_action($hookName, $data);
            return $data;
        }
        
        // Handle event objects
        $eventClass = get_class($event);
        $hookName = $this->classToHookName($eventClass);

        // Call WordPress action
        do_action($hookName, $event);

        return $event;
    }
    
    /**
     * Register admin menu pages via WordPress functions.
     *
     * @param array $pages Array of page configurations
     */
    private function registerAdminMenuPages(array $pages): void
    {
        foreach ($pages as $page) {
            if ($page['type'] === 'menu') {
                add_menu_page(
                    $page['page_title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['menu_slug'],
                    $page['callback'],
                    $page['icon_url'] ?? '',
                    $page['position'] ?? null
                );
            } elseif ($page['type'] === 'submenu') {
                add_submenu_page(
                    $page['parent_slug'],
                    $page['page_title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['menu_slug'],
                    $page['callback']
                );
            }
        }
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
        $hookName = $this->classToHookName($eventClass);

        // Track listener for removal
        if (!isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = [];
        }
        
        $this->listeners[$eventClass][] = [
            'handler' => $handler,
            'priority' => $priority,
        ];

        add_action($hookName, $handler, $priority);
    }

    /**
     * Remove an event listener.
     *
     * @param string $eventClass Event class name
     * @param callable $handler Handler to remove
     */
    public function remove(string $eventClass, callable $handler): void
    {
        $hookName = $this->classToHookName($eventClass);

        if (isset($this->listeners[$eventClass])) {
            foreach ($this->listeners[$eventClass] as $key => $listener) {
                if ($listener['handler'] === $handler) {
                    remove_action($hookName, $handler, $listener['priority']);
                    unset($this->listeners[$eventClass][$key]);
                    break;
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
        $hookName = $this->classToHookName($eventClass);
        return has_action($hookName) !== false;
    }

    /**
     * Get all listeners for an event.
     *
     * @param string $eventClass Event class name
     * @return array<callable> List of handlers
     */
    public function getListeners(string $eventClass): array
    {
        return array_column($this->listeners[$eventClass] ?? [], 'handler');
    }

    /**
     * Convert event class name to WordPress hook name.
     *
     * Converts class names like "Viraloka\Core\Events\UserLoggedIn"
     * to hook names like "viraloka_core_events_user_logged_in".
     *
     * @param string $class Fully qualified class name
     * @return string WordPress hook name
     */
    private function classToHookName(string $class): string
    {
        // Convert namespace separators to underscores
        $name = str_replace('\\', '_', $class);
        
        // Convert CamelCase to snake_case
        $name = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name);
        
        // Convert to lowercase
        return strtolower($name);
    }
}
