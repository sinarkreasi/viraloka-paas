<?php

namespace Viraloka\Core\Bootstrap;

use Viraloka\Core\Application;
use Viraloka\Core\Modules\Logger;

/**
 * PerformanceGuard
 * 
 * Optimizes bootstrap performance and prevents heavy operations.
 * Provides cache adapter detection, lazy loading, hook deferral, and heavy operation prevention.
 */
class PerformanceGuard
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * Logger instance
     * 
     * @var Logger
     */
    protected Logger $logger;
    
    /**
     * Storage adapter instance
     * 
     * @var \Viraloka\Core\Adapter\Contracts\StorageAdapterInterface
     */
    protected \Viraloka\Core\Adapter\Contracts\StorageAdapterInterface $storageAdapter;
    
    /**
     * Event adapter instance
     * 
     * @var \Viraloka\Core\Adapter\Contracts\EventAdapterInterface
     */
    protected \Viraloka\Core\Adapter\Contracts\EventAdapterInterface $eventAdapter;
    
    /**
     * Detected cache adapter
     * 
     * @var string
     */
    protected string $cacheAdapter = 'none';
    
    /**
     * Deferred hooks
     * 
     * @var array
     */
    protected array $deferredHooks = [];
    
    /**
     * Heavy operations that are blocked during bootstrap
     * 
     * @var array
     */
    protected array $blockedOperations = [
        'render_ui',
        'load_assets',
        'execute_business_logic',
        'run_cron',
    ];
    
    /**
     * Cache strategy configuration
     * 
     * @var array
     */
    protected array $cacheStrategy = [
        'manifests' => [
            'enabled' => true,
            'ttl' => 3600,
        ],
        'config' => [
            'enabled' => true,
            'ttl' => 3600,
        ],
    ];
    
    /**
     * Indicates if the guard is active
     * 
     * @var bool
     */
    protected bool $active = false;
    
    /**
     * Create a new PerformanceGuard instance
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->logger = $app->make(Logger::class);
        
        // Get adapters from AdapterRegistry
        $adapterRegistry = $app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        $this->storageAdapter = $adapterRegistry->storage();
        $this->eventAdapter = $adapterRegistry->event();
    }
    
    /**
     * Activate the performance guard
     * 
     * Called during the boot phase to initialize performance optimizations.
     * 
     * @return void
     */
    public function activate(): void
    {
        // Detect available cache adapter
        $this->cacheAdapter = $this->detectCacheAdapter();
        
        // Mark as active
        $this->active = true;
    }
    
    /**
     * Detect available cache adapter
     * 
     * Checks for object cache, transients, or file cache availability.
     * 
     * @return string The detected cache adapter: 'object_cache', 'transients', 'file_cache', or 'none'
     */
    public function detectCacheAdapter(): string
    {
        // Check storage adapter capabilities
        $capabilities = $this->storageAdapter->getCapabilities();
        
        // Check for object cache (e.g., Redis, Memcached)
        if (isset($capabilities['persistent']) && $capabilities['persistent']) {
            return 'object_cache';
        }
        
        // Check for transients
        if (isset($capabilities['transient']) && $capabilities['transient']) {
            return 'transients';
        }
        
        // Fallback to file cache
        if (is_writable(sys_get_temp_dir())) {
            return 'file_cache';
        }
        
        return 'none';
    }
    
    /**
     * Defer a non-essential hook to later execution
     * 
     * @param string $hook The hook name
     * @param callable $callback The callback to execute
     * @param int $priority The hook priority (default: 10)
     * @return void
     */
    public function deferHook(string $hook, callable $callback, int $priority = 10): void
    {
        if (!isset($this->deferredHooks[$hook])) {
            $this->deferredHooks[$hook] = [];
        }
        
        $this->deferredHooks[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
        
        $this->logger->info("Hook deferred: {$hook}");
    }
    
    /**
     * Execute all deferred hooks
     * 
     * Should be called after bootstrap is complete.
     * 
     * @return void
     */
    public function executeDeferredHooks(): void
    {
        foreach ($this->deferredHooks as $hook => $callbacks) {
            foreach ($callbacks as $callbackData) {
                // Use EventAdapter instead of direct add_action()
                $this->eventAdapter->listen($hook, $callbackData['callback'], $callbackData['priority']);
            }
        }
        
        // Clear deferred hooks
        $this->deferredHooks = [];
    }
    
    /**
     * Prevent a heavy operation from executing during bootstrap
     * 
     * @param string $operation The operation identifier
     * @return void
     * @throws \RuntimeException If the operation is blocked
     */
    public function preventHeavyOperation(string $operation): void
    {
        if (!$this->active) {
            return;
        }
        
        if (in_array($operation, $this->blockedOperations, true)) {
            $this->logger->warning("Heavy operation blocked during bootstrap: {$operation}");
            throw new \RuntimeException("Heavy operation '{$operation}' is not allowed during bootstrap");
        }
    }
    
    /**
     * Check if an operation is blocked
     * 
     * @param string $operation The operation identifier
     * @return bool
     */
    public function isOperationBlocked(string $operation): bool
    {
        return $this->active && in_array($operation, $this->blockedOperations, true);
    }
    
    /**
     * Get the detected cache adapter
     * 
     * @return string
     */
    public function getCacheAdapter(): string
    {
        return $this->cacheAdapter;
    }
    
    /**
     * Get cache strategy configuration
     * 
     * @return array
     */
    public function getCacheStrategy(): array
    {
        return $this->cacheStrategy;
    }
    
    /**
     * Configure cache strategy for a specific type
     * 
     * @param string $type The cache type ('manifests' or 'config')
     * @param bool $enabled Whether caching is enabled
     * @param int $ttl Time to live in seconds
     * @return void
     */
    public function configureCacheStrategy(string $type, bool $enabled, int $ttl): void
    {
        if (isset($this->cacheStrategy[$type])) {
            $this->cacheStrategy[$type] = [
                'enabled' => $enabled,
                'ttl' => $ttl,
            ];
            
            $this->logger->info("Cache strategy configured for {$type}: enabled={$enabled}, ttl={$ttl}");
        }
    }
    
    /**
     * Get all deferred hooks
     * 
     * @return array
     */
    public function getDeferredHooks(): array
    {
        return $this->deferredHooks;
    }
    
    /**
     * Check if the guard is active
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }
    
    /**
     * Add a blocked operation
     * 
     * @param string $operation
     * @return void
     */
    public function addBlockedOperation(string $operation): void
    {
        if (!in_array($operation, $this->blockedOperations, true)) {
            $this->blockedOperations[] = $operation;
        }
    }
    
    /**
     * Get all blocked operations
     * 
     * @return array
     */
    public function getBlockedOperations(): array
    {
        return $this->blockedOperations;
    }
}
