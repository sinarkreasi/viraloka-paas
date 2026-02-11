<?php

namespace Viraloka\Core\Frontend;

use Viraloka\Core\Application;
use Viraloka\Core\Frontend\Contracts\FrontendAppShellContract;
use Viraloka\Core\Context\ContextResolver;
use Viraloka\Core\Modules\Contracts\ModuleRegistryContract;
use Viraloka\Core\Adapter\Contracts\EventAdapterInterface;
use Viraloka\Core\Adapter\Contracts\RequestAdapterInterface;
use Viraloka\Core\Adapter\AdapterRegistry;

/**
 * Frontend App Shell
 * 
 * Provides the frontend application shell for dashboard rendering, routing,
 * and seamless module UI integration. Theme-independent and context-aware.
 */
class FrontendAppShell implements FrontendAppShellContract
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * The router instance
     * 
     * @var Router
     */
    protected Router $router;
    
    /**
     * The dashboard renderer instance
     * 
     * @var DashboardRenderer
     */
    protected DashboardRenderer $dashboardRenderer;
    
    /**
     * The context resolver instance
     * 
     * @var ContextResolver
     */
    protected ContextResolver $contextResolver;
    
    /**
     * The module registry instance
     * 
     * @var ModuleRegistryContract
     */
    protected ModuleRegistryContract $moduleRegistry;
    
    /**
     * The event adapter instance
     * 
     * @var EventAdapterInterface
     */
    protected EventAdapterInterface $eventAdapter;
    
    /**
     * The request adapter instance
     * 
     * @var RequestAdapterInterface
     */
    protected RequestAdapterInterface $requestAdapter;
    
    /**
     * Indicates if the app shell has been activated
     * 
     * @var bool
     */
    protected bool $activated = false;
    
    /**
     * Create a new FrontendAppShell instance
     * 
     * @param Application $app
     * @param Router $router
     * @param DashboardRenderer $dashboardRenderer
     * @param ContextResolver $contextResolver
     * @param ModuleRegistryContract $moduleRegistry
     * @param AdapterRegistry $adapterRegistry
     */
    public function __construct(
        Application $app,
        Router $router,
        DashboardRenderer $dashboardRenderer,
        ContextResolver $contextResolver,
        ModuleRegistryContract $moduleRegistry,
        AdapterRegistry $adapterRegistry
    ) {
        $this->app = $app;
        $this->router = $router;
        $this->dashboardRenderer = $dashboardRenderer;
        $this->contextResolver = $contextResolver;
        $this->moduleRegistry = $moduleRegistry;
        $this->eventAdapter = $adapterRegistry->event();
        $this->requestAdapter = $adapterRegistry->request();
    }
    
    /**
     * Activate the app shell
     * 
     * Registers WordPress hooks and initializes the frontend system.
     * 
     * @return void
     */
    public function activate(): void
    {
        if ($this->activated) {
            return;
        }
        
        // Register default routes
        $this->registerDefaultRoutes();
        
        // Hook into template system
        $this->registerTemplateHooks();
        
        // Allow modules to register their routes and UI
        $this->eventAdapter->dispatch('viraloka_frontend_init', ['appShell' => $this]);
        
        $this->activated = true;
    }
    
    /**
     * Register default routes
     * 
     * @return void
     */
    protected function registerDefaultRoutes(): void
    {
        // Dashboard route
        $this->router->register('dashboard', function ($path, $app) {
            $this->render();
        }, ['priority' => 10]);
        
        // Home route (context-aware)
        $this->router->register('', function ($path, $app) {
            $this->render();
        }, ['priority' => 5]);
    }
    
    /**
     * Register template hooks
     * 
     * Integrates with template system to render the app shell
     * when appropriate.
     * 
     * @return void
     */
    protected function registerTemplateHooks(): void
    {
        // Hook into template_redirect to handle SaaS routes
        $this->eventAdapter->listen('template_redirect', function () {
            $this->handleTemplateRedirect();
        }, 5);
        
        // Add body class for SaaS pages
        $this->eventAdapter->listen('body_class', function ($classes) {
            if ($this->isSaaSPage()) {
                $classes[] = 'viraloka-saas-page';
                $classes[] = 'viraloka-context-' . $this->contextResolver->getCurrentContext();
            }
            return $classes;
        });
    }
    
    /**
     * Handle template redirect
     * 
     * Checks if the current request should be handled by the app shell
     * and routes it accordingly.
     * 
     * @return void
     */
    protected function handleTemplateRedirect(): void
    {
        // Check if this is a SaaS page request
        if (!$this->isSaaSPage()) {
            return;
        }
        
        // Get the current path
        $path = $this->getCurrentPath();
        
        // Route the request
        $result = $this->route($path);
        
        // If route was handled, prevent WordPress from loading default template
        if ($result !== null) {
            exit;
        }
    }
    
    /**
     * Check if the current page is a SaaS page
     * 
     * @return bool
     */
    protected function isSaaSPage(): bool
    {
        // Get current path
        $path = $this->getCurrentPath();
        
        // Check for dashboard page
        if ($path === 'dashboard' || strpos($path, 'dashboard/') === 0) {
            return true;
        }
        
        // Allow modules to define SaaS pages via event
        $result = $this->eventAdapter->dispatch('viraloka_is_saas_page', ['path' => $path, 'isSaas' => false]);
        return $result['isSaas'] ?? false;
    }
    
    /**
     * Get the current request path
     * 
     * @return string
     */
    protected function getCurrentPath(): string
    {
        return trim($this->requestAdapter->getPath(), '/');
    }
    
    /**
     * Render the dashboard
     * 
     * @return void
     */
    public function render(): void
    {
        // Set up WordPress environment for rendering
        $this->setupRenderEnvironment();
        
        // Render the dashboard
        $this->dashboardRenderer->render();
    }
    
    /**
     * Set up the render environment
     * 
     * Prepares environment for dashboard rendering.
     * 
     * @return void
     */
    protected function setupRenderEnvironment(): void
    {
        // Enqueue styles and scripts
        $this->enqueueAssets();
        
        // Set page title via event
        $this->eventAdapter->listen('wp_title', function ($title) {
            $siteName = $this->eventAdapter->dispatch('get_bloginfo', ['show' => 'name'])['value'] ?? 'Site';
            return 'Dashboard - ' . $siteName;
        });
        
        // Disable admin bar for cleaner UI via event
        $this->eventAdapter->listen('show_admin_bar', function () {
            return false;
        });
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    protected function enqueueAssets(): void
    {
        // Allow themes to enqueue their own styles
        $result = $this->eventAdapter->dispatch('viraloka_enqueue_dashboard_assets', ['enqueued' => false]);
        
        // Enqueue default styles if theme doesn't provide them
        if (!($result['enqueued'] ?? false)) {
            // Add inline styles for basic layout
            $this->eventAdapter->listen('wp_head', function () {
                ?>
                <style>
                    .viraloka-dashboard {
                        max-width: 1200px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .viraloka-dashboard-body {
                        display: flex;
                        gap: 20px;
                    }
                    .viraloka-dashboard-sidebar {
                        flex: 0 0 250px;
                    }
                    .viraloka-dashboard-main {
                        flex: 1;
                    }
                    .viraloka-module-ui {
                        margin-bottom: 20px;
                        padding: 20px;
                        background: #fff;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                    }
                    .viraloka-module-title {
                        margin-top: 0;
                        margin-bottom: 15px;
                        font-size: 18px;
                        font-weight: 600;
                    }
                </style>
                <?php
            });
        }
    }
    
    /**
     * Route a request to the appropriate handler
     * 
     * @param string $path The request path
     * @return mixed The route handler result
     */
    public function route(string $path)
    {
        // Resolve the route using the router
        return $this->router->resolve($path);
    }
    
    /**
     * Register a module UI component
     * 
     * @param string $moduleId The module identifier
     * @param array $uiConfig The UI configuration
     * @return void
     */
    public function registerModuleUI(string $moduleId, array $uiConfig): void
    {
        // Delegate to dashboard renderer
        $this->dashboardRenderer->registerModuleUI($moduleId, $uiConfig);
    }
    
    /**
     * Get the router instance
     * 
     * @return Router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }
    
    /**
     * Get the dashboard renderer instance
     * 
     * @return DashboardRenderer
     */
    public function getDashboardRenderer(): DashboardRenderer
    {
        return $this->dashboardRenderer;
    }
    
    /**
     * Check if the app shell has been activated
     * 
     * @return bool
     */
    public function isActivated(): bool
    {
        return $this->activated;
    }
}
