<?php

namespace Viraloka\Core\Bootstrap;

use Viraloka\Core\Application;
use Viraloka\Core\Events\EventDispatcher;

/**
 * Kernel
 * 
 * Orchestrates the bootstrap lifecycle through three distinct phases:
 * - register: Register all core services in the container
 * - boot: Initialize services that require configuration
 * - ready: Finalize system initialization
 * 
 * Handles bootstrap failures gracefully with error logging and admin notices.
 */
class Kernel
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * The event dispatcher instance
     * 
     * @var EventDispatcher
     */
    protected EventDispatcher $events;
    
    /**
     * Indicates if the kernel has been bootstrapped
     * 
     * @var bool
     */
    protected bool $bootstrapped = false;
    
    /**
     * Indicates if the system is ready
     * 
     * @var bool
     */
    protected bool $ready = false;
    
    /**
     * Indicates if bootstrap failed
     * 
     * @var bool
     */
    protected bool $bootstrapFailed = false;
    
    /**
     * Bootstrap failure error message
     * 
     * @var string|null
     */
    protected ?string $bootstrapError = null;
    
    /**
     * The lifecycle phases
     * 
     * @var array
     */
    protected array $lifecyclePhases = ['register', 'boot', 'ready'];
    
    /**
     * The current lifecycle phase
     * 
     * @var string
     */
    protected string $currentPhase = '';
    
    /**
     * Create a new Kernel instance
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->events = new EventDispatcher();
        
        // Register admin notice hook for bootstrap failures
        $this->registerAdminNoticeHook();
    }
    
    /**
     * Bootstrap the application
     * 
     * Executes the three lifecycle phases in order:
     * 1. register - Register core services
     * 2. boot - Initialize services
     * 3. ready - Finalize system
     * 
     * Handles errors gracefully and prevents module loading on failure.
     * 
     * @return void
     */
    public function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }
        
        try {
            $this->registerPhase();
            $this->bootPhase();
            $this->readyPhase();
            
            $this->bootstrapped = true;
        } catch (\Throwable $e) {
            $this->handleBootstrapFailure($e);
        }
    }
    
    /**
     * Execute the register phase
     * 
     * Registers all core services in the container and dispatches
     * the viraloka.bootstrap.register event.
     * 
     * @return void
     */
    protected function registerPhase(): void
    {
        $this->currentPhase = 'register';
        
        // Dispatch register event
        $this->dispatchEvent('viraloka.bootstrap.register');
        
        // Register core services
        $this->registerCoreServices();
        
        // Register admin notice hook after adapters are available (Requirement 10.4)
        $this->registerAdminNoticeHookWithAdapter();
    }
    
    /**
     * Register core services in the container
     * 
     * @return void
     */
    protected function registerCoreServices(): void
    {
        // Register AdapterRegistry FIRST (Requirements 10.1, 10.2)
        $this->registerAdapterRegistry();
        
        // Determine log file path
        $logFile = $this->app->basePath() . '/viraloka-errors.log';
        
        // Get RuntimeAdapter to check debug mode
        $adapters = $this->app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        $debugMode = $adapters->runtime()->isDebugMode();
        
        // Register Logger with persistent log file
        $this->app->singleton(\Viraloka\Core\Modules\Logger::class, function ($app) use ($logFile, $debugMode) {
            return new \Viraloka\Core\Modules\Logger($logFile, $debugMode);
        });
        
        // Register SchemaValidator
        $this->app->singleton(\Viraloka\Core\Modules\SchemaValidator::class, function ($app) {
            return new \Viraloka\Core\Modules\SchemaValidator();
        });
        
        // Register ManifestParser
        $this->app->singleton(\Viraloka\Core\Modules\ManifestParser::class, function ($app) {
            return new \Viraloka\Core\Modules\ManifestParser(
                $app->make(\Viraloka\Core\Modules\SchemaValidator::class)
            );
        });
        
        // Register ModuleRegistry
        $this->app->singleton(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class, function ($app) {
            return new \Viraloka\Core\Modules\ModuleRegistry();
        });
        
        // Register DependencyResolver
        $this->app->singleton(\Viraloka\Core\Modules\DependencyResolver::class, function ($app) {
            return new \Viraloka\Core\Modules\DependencyResolver(
                $app->make(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class)
            );
        });
        
        // Register ContextStackBuilder
        $this->app->singleton(\Viraloka\Core\Context\ContextStackBuilder::class, function ($app) {
            return new \Viraloka\Core\Context\ContextStackBuilder();
        });
        
        // Register RecommendationGraph
        $this->app->singleton(\Viraloka\Core\Context\RecommendationGraph::class, function ($app) {
            return new \Viraloka\Core\Context\RecommendationGraph();
        });
        
        // Register ManifestCache
        $this->app->singleton(\Viraloka\Core\Modules\ManifestCache::class, function ($app) {
            return new \Viraloka\Core\Modules\ManifestCache();
        });
        
        // Register ModuleLoader
        $this->app->singleton(\Viraloka\Core\Modules\Contracts\ModuleLoaderContract::class, function ($app) {
            return new \Viraloka\Core\Modules\ModuleLoader(
                $app->make(\Viraloka\Core\Modules\ManifestParser::class),
                $app->make(\Viraloka\Core\Modules\DependencyResolver::class),
                $app->make(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class),
                $app->make(\Viraloka\Core\Context\ContextResolver::class),
                $app->modulesPath(),
                $app->make(\Viraloka\Core\Modules\ManifestCache::class),
                $app->make(\Viraloka\Core\Modules\Logger::class)
            );
        });
        
        // Register ModuleBootstrapper
        $this->app->singleton(\Viraloka\Core\Modules\Contracts\ModuleBootstrapperContract::class, function ($app) {
            return new \Viraloka\Core\Modules\ModuleBootstrapper(
                $app,
                $app->make(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class),
                $app->make(\Viraloka\Core\Modules\Logger::class)
            );
        });
        
        // Register PolicyEngine
        $this->app->singleton(\Viraloka\Core\Policy\Contracts\PolicyEngineContract::class, function ($app) {
            return new \Viraloka\Core\Policy\PolicyEngine($app);
        });
        
        // Register IdentityEngine (Requirement 11.4)
        $this->app->singleton(\Viraloka\Core\Identity\Contracts\IdentityEngineInterface::class, function ($app) {
            return new \Viraloka\Core\Identity\IdentityEngine($app);
        });
        
        // Register MembershipEngine (Requirement 11.4)
        $this->app->singleton(\Viraloka\Core\Membership\Contracts\MembershipEngineInterface::class, function ($app) {
            return new \Viraloka\Core\Membership\MembershipEngine($app);
        });
        
        // Register GrantEngine (Requirement 11.4)
        $this->app->singleton(\Viraloka\Core\Grant\Contracts\GrantEngineInterface::class, function ($app) {
            return new \Viraloka\Core\Grant\GrantEngine($app);
        });
        
        // Register RoleRegistry (Requirement 11.4)
        $this->app->singleton(\Viraloka\Core\Membership\Contracts\RoleRegistryInterface::class, function ($app) {
            return new \Viraloka\Core\Membership\RoleRegistry();
        });
        
        // Register IdentityEngine (legacy contract - for backward compatibility)
        $this->app->singleton(\Viraloka\Core\Identity\Contracts\IdentityEngineContract::class, function ($app) {
            return $app->make(\Viraloka\Core\Identity\Contracts\IdentityEngineInterface::class);
        });
        
        // Register AccountEngine
        $this->app->singleton(\Viraloka\Core\Account\Contracts\AccountEngineContract::class, function ($app) {
            return new \Viraloka\Core\Account\AccountEngine($app);
        });
        
        // Register SubscriptionEngine
        $this->app->singleton(\Viraloka\Core\Subscription\Contracts\SubscriptionEngineContract::class, function ($app) {
            return new \Viraloka\Core\Subscription\SubscriptionEngine($app);
        });
        
        // Register UsageEngine
        $this->app->singleton(\Viraloka\Core\Usage\Contracts\UsageEngineContract::class, function ($app) {
            return new \Viraloka\Core\Usage\UsageEngine($app);
        });
        
        // Register SecurityGuard
        $this->app->singleton(SecurityGuard::class, function ($app) {
            return new SecurityGuard($app);
        });
        
        // Register PerformanceGuard
        $this->app->singleton(PerformanceGuard::class, function ($app) {
            return new PerformanceGuard($app);
        });
        
        // Register WorkspaceResolver
        $this->app->singleton(\Viraloka\Core\Workspace\WorkspaceResolver::class, function ($app) {
            return new \Viraloka\Core\Workspace\WorkspaceResolver($app);
        });
        
        // Register ThemeIntegration
        $this->app->singleton(\Viraloka\Core\Theme\ThemeIntegration::class, function ($app) {
            return new \Viraloka\Core\Theme\ThemeIntegration($app);
        });
        
        // Register ContextResolver (after WorkspaceResolver and ThemeIntegration)
        $this->app->singleton(\Viraloka\Core\Context\ContextResolver::class, function ($app) {
            return new \Viraloka\Core\Context\ContextResolver(
                $app->make(\Viraloka\Core\Workspace\WorkspaceResolver::class),
                $app->make(\Viraloka\Core\Theme\ThemeIntegration::class),
                $app->make(\Viraloka\Core\Context\ContextStackBuilder::class),
                $app->make(\Viraloka\Core\Context\RecommendationGraph::class),
                $app->make(\Viraloka\Core\Modules\Logger::class)
            );
        });
        
        // Register AdminMenuBuilder
        $this->app->singleton(\Viraloka\Core\Modules\Contracts\AdminMenuBuilderContract::class, function ($app) {
            return new \Viraloka\Core\Modules\AdminMenuBuilder();
        });
        
        // Register CoreAdminPage
        $this->app->singleton(\Viraloka\Core\Admin\CoreAdminPage::class, function ($app) {
            return new \Viraloka\Core\Admin\CoreAdminPage($app);
        });
        
        // Register Router
        $this->app->singleton(\Viraloka\Core\Frontend\Router::class, function ($app) {
            return new \Viraloka\Core\Frontend\Router(
                $app,
                $app->make(\Viraloka\Core\Context\ContextResolver::class)
            );
        });
        
        // Register DashboardRenderer
        $this->app->singleton(\Viraloka\Core\Frontend\DashboardRenderer::class, function ($app) {
            return new \Viraloka\Core\Frontend\DashboardRenderer(
                $app,
                $app->make(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class),
                $app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class)
            );
        });
        
        // Register FrontendAppShell
        $this->app->singleton(\Viraloka\Core\Frontend\Contracts\FrontendAppShellContract::class, function ($app) {
            return new \Viraloka\Core\Frontend\FrontendAppShell(
                $app,
                $app->make(\Viraloka\Core\Frontend\Router::class),
                $app->make(\Viraloka\Core\Frontend\DashboardRenderer::class),
                $app->make(\Viraloka\Core\Context\ContextResolver::class),
                $app->make(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class),
                $app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class)
            );
        });
        
        // Register WordPressHooks
        $this->app->singleton(\Viraloka\Core\Integration\WordPressHooks::class, function ($app) {
            return new \Viraloka\Core\Integration\WordPressHooks($app);
        });
        
        // Register ScopeValidator
        $this->app->singleton(ScopeValidator::class, function ($app) {
            return new ScopeValidator();
        });
    }
    
    /**
     * Register the Adapter Registry and detect host environment
     * 
     * Detects the host environment (WordPress, Laravel, or custom) and
     * registers the appropriate adapter implementations.
     * 
     * Validates: Requirements 10.1, 10.2
     * 
     * @return void
     * @throws \Viraloka\Core\Adapter\Exceptions\RuntimeAdapterException If adapters cannot be registered
     */
    protected function registerAdapterRegistry(): void
    {
        // Create and register the AdapterRegistry
        $this->app->singleton(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class, function ($app) {
            $registry = new \Viraloka\Core\Adapter\AdapterRegistry();
            
            // Detect host environment and register appropriate adapters
            $this->detectAndRegisterAdapters($registry);
            
            return $registry;
        });
        
        // Also bind the concrete class for convenience
        $this->app->singleton(\Viraloka\Core\Adapter\AdapterRegistry::class, function ($app) {
            return $app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        });
        
        // Inject adapters into EventDispatcher (Requirement 9.1, 9.7)
        $adapters = $this->app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        $this->events->setAdapters($adapters);
    }
    
    /**
     * Detect host environment and register appropriate adapters
     * 
     * @param \Viraloka\Core\Adapter\AdapterRegistry $registry
     * @return void
     * @throws \Viraloka\Core\Adapter\Exceptions\RuntimeAdapterException If host environment cannot be detected
     */
    protected function detectAndRegisterAdapters(\Viraloka\Core\Adapter\AdapterRegistry $registry): void
    {
        // Detect WordPress environment
        if (function_exists('add_action') && function_exists('wp_get_current_user')) {
            try {
                // Register WordPress adapters
                $registry->registerRuntime(new \Viraloka\Adapter\WordPress\WordPressRuntimeAdapter());
                $registry->registerRequest(new \Viraloka\Adapter\WordPress\WordPressRequestAdapter());
                $registry->registerResponse(new \Viraloka\Adapter\WordPress\WordPressResponseAdapter());
                $registry->registerAuth(new \Viraloka\Adapter\WordPress\WordPressAuthAdapter());
                $registry->registerStorage(new \Viraloka\Adapter\WordPress\WordPressStorageAdapter());
                $registry->registerEvent(new \Viraloka\Adapter\WordPress\WordPressEventAdapter());
                
                return;
            } catch (\Throwable $e) {
                // Use adapter exception instead of generic exception (Requirement 10.6)
                throw new \Viraloka\Core\Adapter\Exceptions\RuntimeAdapterException(
                    "Failed to register WordPress adapters: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
        
        // If no host environment detected, throw adapter exception (Requirement 10.6)
        throw new \Viraloka\Core\Adapter\Exceptions\RuntimeAdapterException(
            'Unable to detect host environment. Viraloka Core requires a supported host (WordPress, Laravel, or custom adapters).'
        );
    }
    
    /**
     * Execute the boot phase
     * 
     * Initializes services that require configuration and dispatches
     * the viraloka.bootstrap.boot event.
     * 
     * @return void
     */
    protected function bootPhase(): void
    {
        $this->currentPhase = 'boot';
        
        // Get adapter registry (Requirements 10.3, 10.4, 10.5)
        $adapters = $this->app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        
        // Dispatch boot event using EventAdapter
        $this->dispatchEvent('viraloka.bootstrap.boot');
        
        // Clear manifest cache to ensure fresh data (temporary for debugging)
        // Use StorageAdapter instead of direct transient calls (Requirement 10.5)
        $adapters->storage()->delete('manifest_viraloka.sample');
        $adapters->storage()->delete('manifest_viraloka.linkinbio');
        
        // Activate Security Guard
        $securityGuard = $this->app->make(SecurityGuard::class);
        $securityGuard->activate();
        
        // Activate Performance Guard
        $performanceGuard = $this->app->make(PerformanceGuard::class);
        $performanceGuard->activate();
        
        // Resolve Workspace
        $workspaceResolver = $this->app->make(\Viraloka\Core\Workspace\WorkspaceResolver::class);
        $workspace = $workspaceResolver->resolve();
        
        // Inject workspace into container
        $this->app->instance(\Viraloka\Core\Workspace\Workspace::class, $workspace);
        
        // Resolve Context
        $contextResolver = $this->app->make(\Viraloka\Core\Context\ContextResolver::class);
        $contextResolver->resolve();
        
        // Register WordPress hooks for engines
        $wordpressHooks = $this->app->make(\Viraloka\Core\Integration\WordPressHooks::class);
        $wordpressHooks->register();
        
        // Validate scope boundaries (Requirements 13.2)
        $this->validateScopeBoundaries();
        
        // Initialize Module Registry (scan and validate manifests)
        $moduleLoader = $this->app->make(\Viraloka\Core\Modules\Contracts\ModuleLoaderContract::class);
        // Module discovery happens here but no module code is executed
    }
    
    /**
     * Execute the ready phase
     * 
     * Finalizes system initialization and dispatches
     * the viraloka.bootstrap.ready event followed by
     * the viraloka.ready event to signal system readiness.
     * 
     * Loads and bootstraps modules if bootstrap was successful.
     * 
     * @return void
     */
    protected function readyPhase(): void
    {
        $this->currentPhase = 'ready';
        
        // Get adapter registry (Requirement 10.3)
        $adapters = $this->app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        
        // Dispatch ready phase event
        $this->dispatchEvent('viraloka.bootstrap.ready');
        
        // Only load modules if bootstrap hasn't failed
        if (!$this->bootstrapFailed) {
            $this->loadAndBootstrapModules();
            
            // Register Core Admin Page
            // Use RuntimeAdapter instead of is_admin() (Requirement 10.3)
            if ($adapters->runtime()->isAdmin()) {
                $coreAdminPage = $this->app->make(\Viraloka\Core\Admin\CoreAdminPage::class);
                $coreAdminPage->register();
            }
            
            // Build admin menus for all loaded modules
            if ($adapters->runtime()->isAdmin()) {
                $this->buildAdminMenus();
            }
            
            // Activate Theme Integration
            $themeIntegration = $this->app->make(\Viraloka\Core\Theme\ThemeIntegration::class);
            $themeIntegration->activate();
            
            // Activate Frontend App Shell (only on frontend)
            if (!$adapters->runtime()->isAdmin()) {
                $frontendAppShell = $this->app->make(\Viraloka\Core\Frontend\Contracts\FrontendAppShellContract::class);
                $frontendAppShell->activate();
            }
        }
        
        // Set ready state flag
        $this->ready = true;
        
        // Dispatch system ready event
        $this->dispatchEvent('viraloka.ready');
    }
    
    /**
     * Validate scope boundaries
     * 
     * Ensures the core stays within defined scope boundaries and
     * contains no out-of-scope functionality (inventory, shipping, etc.)
     * 
     * Validates: Requirements 13.2
     * 
     * @return void
     */
    protected function validateScopeBoundaries(): void
    {
        try {
            $scopeValidator = $this->app->make(ScopeValidator::class);
            $result = $scopeValidator->validateCoreScope();
            
            if (!$result['valid']) {
                $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
                
                foreach ($result['violations'] as $violation) {
                    $logger->error(
                        "Scope violation detected: {$violation}",
                        'kernel',
                        'Scope Validation Error'
                    );
                }
                
                // In debug mode, throw exception to halt bootstrap
                if ($logger->isDebugMode()) {
                    throw new \RuntimeException(
                        'Scope validation failed: ' . implode(', ', $result['violations'])
                    );
                }
            }
        } catch (\Throwable $e) {
            // Log validation failure but don't halt bootstrap in production
            $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
            $logger->error(
                "Scope validation error: {$e->getMessage()}",
                'kernel',
                'Scope Validation Error'
            );
        }
    }
    
    /**
     * Load and bootstrap modules
     * 
     * @return void
     */
    protected function loadAndBootstrapModules(): void
    {
        try {
            // Load all modules
            $loader = $this->app->make(\Viraloka\Core\Modules\Contracts\ModuleLoaderContract::class);
            $modules = $loader->loadModules();
            
            // Bootstrap each loaded module
            $bootstrapper = $this->app->make(\Viraloka\Core\Modules\Contracts\ModuleBootstrapperContract::class);
            foreach ($modules as $module) {
                try {
                    $bootstrapper->bootstrap($module);
                } catch (\Throwable $e) {
                    // Log error but continue bootstrapping other modules
                    $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
                    $logger->bootstrapError(
                        $module->getId(),
                        "Failed to bootstrap module: {$e->getMessage()}",
                        $e
                    );
                }
            }
        } catch (\Throwable $e) {
            // Log module loading failure
            $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
            $logger->error(
                "Failed to load modules: {$e->getMessage()}",
                'kernel',
                'Module Loading Error'
            );
        }
    }
    
    /**
     * Build admin menus for all loaded modules
     * 
     * @return void
     */
    protected function buildAdminMenus(): void
    {
        try {
            $registry = $this->app->make(\Viraloka\Core\Modules\Contracts\ModuleRegistryContract::class);
            $menuBuilder = $this->app->make(\Viraloka\Core\Modules\Contracts\AdminMenuBuilderContract::class);
            $adapters = $this->app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
            
            // Get all registered modules
            $modules = $registry->all();
            
            // Build menus on admin_menu hook with priority 10
            // Use EventAdapter instead of add_action() (Requirement 10.4)
            $adapters->event()->listen('admin_menu', function () use ($modules, $menuBuilder) {
                foreach ($modules as $module) {
                    $menuBuilder->buildMenu($module);
                }
            }, 10);
        } catch (\Throwable $e) {
            // Log menu building failure
            $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
            $logger->error(
                "Failed to build admin menus: {$e->getMessage()}",
                'kernel',
                'Admin Menu Error'
            );
        }
    }
    
    /**
     * Dispatch a lifecycle event
     * 
     * Uses the EventDispatcher to dispatch events with listener execution
     * order tracking and non-blocking execution.
     * 
     * @param string $event
     * @return void
     */
    protected function dispatchEvent(string $event): void
    {
        $this->events->dispatch($event, $this->app);
    }
    
    /**
     * Get the event dispatcher instance
     * 
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->events;
    }
    
    /**
     * Check if the kernel has been bootstrapped
     * 
     * @return bool
     */
    public function isBootstrapped(): bool
    {
        return $this->bootstrapped;
    }
    
    /**
     * Check if the system is ready
     * 
     * External code can use this method to query if the bootstrap
     * process has completed and the system is ready for operation.
     * 
     * @return bool
     */
    public function isReady(): bool
    {
        return $this->ready;
    }
    
    /**
     * Get the current lifecycle phase
     * 
     * @return string
     */
    public function getCurrentPhase(): string
    {
        return $this->currentPhase;
    }
    
    /**
     * Check if bootstrap failed
     * 
     * @return bool
     */
    public function hasBootstrapFailed(): bool
    {
        return $this->bootstrapFailed;
    }
    
    /**
     * Get the bootstrap error message
     * 
     * @return string|null
     */
    public function getBootstrapError(): ?string
    {
        return $this->bootstrapError;
    }
    
    /**
     * Handle bootstrap failure
     * 
     * Logs the error with full context and prevents module loading.
     * 
     * @param \Throwable $exception
     * @return void
     */
    protected function handleBootstrapFailure(\Throwable $exception): void
    {
        $this->bootstrapFailed = true;
        $this->bootstrapError = $exception->getMessage();
        
        // Log error with full context
        $this->logBootstrapError($exception);
        
        // Mark as bootstrapped to prevent retry
        $this->bootstrapped = true;
    }
    
    /**
     * Log bootstrap error with full context
     * 
     * @param \Throwable $exception
     * @return void
     */
    protected function logBootstrapError(\Throwable $exception): void
    {
        // Try to get logger from container
        try {
            if ($this->app->bound(\Viraloka\Core\Modules\Logger::class)) {
                $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
                
                $errorMessage = sprintf(
                    'Bootstrap failed in %s phase: %s',
                    $this->currentPhase ?: 'unknown',
                    $exception->getMessage()
                );
                
                $logger->error($errorMessage, 'kernel', 'Bootstrap Error');
                
                // In debug mode, log full exception details
                if ($logger->isDebugMode()) {
                    $logger->error(
                        sprintf(
                            "Exception: %s\nFile: %s:%d\nTrace:\n%s",
                            get_class($exception),
                            $exception->getFile(),
                            $exception->getLine(),
                            $exception->getTraceAsString()
                        ),
                        'kernel',
                        'Debug Trace'
                    );
                }
            }
        } catch (\Throwable $e) {
            // If logging fails, use PHP error_log as fallback
            // No longer check for WordPress-specific functions (Requirement 10.6)
            error_log(sprintf(
                '[Viraloka Core] Bootstrap Error: %s in %s phase',
                $exception->getMessage(),
                $this->currentPhase ?: 'unknown'
            ));
        }
    }
    
    /**
     * Register admin notice hook for bootstrap failures
     * 
     * @return void
     */
    protected function registerAdminNoticeHook(): void
    {
        // We need to defer this until adapters are registered
        // This will be called after adapter registration in registerPhase
    }
    
    /**
     * Register admin notice hook using EventAdapter
     * 
     * Uses EventAdapter instead of direct add_action() call (Requirement 10.4)
     * 
     * @return void
     */
    protected function registerAdminNoticeHookWithAdapter(): void
    {
        try {
            $adapters = $this->app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
            
            $adapters->event()->listen('admin_notices', function() {
                if ($this->bootstrapFailed) {
                    $this->displayBootstrapFailureNotice();
                }
            });
        } catch (\Throwable $e) {
            // If adapter registration fails, we can't show admin notices
            // This is acceptable as the bootstrap will fail anyway
        }
    }
    
    /**
     * Display admin notice for bootstrap failure
     * 
     * @return void
     */
    protected function displayBootstrapFailureNotice(): void
    {
        $message = sprintf(
            '<strong>Viraloka Core Bootstrap Failed:</strong> %s',
            esc_html($this->bootstrapError ?? 'Unknown error')
        );
        
        // Add debug information if available
        if ($this->app->bound(\Viraloka\Core\Modules\Logger::class)) {
            try {
                $logger = $this->app->make(\Viraloka\Core\Modules\Logger::class);
                if ($logger->isDebugMode()) {
                    $message .= '<br><small>Check the error log for detailed trace information.</small>';
                }
            } catch (\Throwable $e) {
                // Ignore logger errors in notice display
            }
        }
        
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            $message
        );
    }
}
