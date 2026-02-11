<?php

namespace Viraloka\Core\Admin;

use Viraloka\Core\Application;
use Viraloka\Core\Modules\Contracts\ModuleRegistryContract;

/**
 * Core Admin Page
 * 
 * Provides the main admin interface for Viraloka Core.
 * Displays system status, loaded modules, and configuration options.
 */
class CoreAdminPage
{
    /**
     * Application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * Event adapter instance
     * 
     * @var \Viraloka\Core\Adapter\Contracts\EventAdapterInterface
     */
    protected \Viraloka\Core\Adapter\Contracts\EventAdapterInterface $eventAdapter;
    
    /**
     * Response adapter instance
     * 
     * @var \Viraloka\Core\Adapter\Contracts\ResponseAdapterInterface
     */
    protected \Viraloka\Core\Adapter\Contracts\ResponseAdapterInterface $responseAdapter;
    
    /**
     * Runtime adapter instance
     * 
     * @var \Viraloka\Core\Adapter\Contracts\RuntimeAdapterInterface
     */
    protected \Viraloka\Core\Adapter\Contracts\RuntimeAdapterInterface $runtimeAdapter;
    
    /**
     * Create a new core admin page instance
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        
        // Get adapters from AdapterRegistry
        $adapterRegistry = $app->make(\Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface::class);
        $this->eventAdapter = $adapterRegistry->event();
        $this->responseAdapter = $adapterRegistry->response();
        $this->runtimeAdapter = $adapterRegistry->runtime();
    }
    
    /**
     * Register the admin page
     * 
     * @return void
     */
    public function register(): void
    {
        // Use EventAdapter instead of add_action
        $this->eventAdapter->listen('admin_menu', [$this, 'addAdminMenu']);
    }
    
    /**
     * Add admin menu
     * 
     * @return void
     */
    public function addAdminMenu(): void
    {
        // Dispatch event to allow host adapter to register menu
        $this->eventAdapter->dispatch('viraloka_register_admin_menu', [
            'pages' => [
                [
                    'type' => 'menu',
                    'page_title' => 'Viraloka Core',
                    'menu_title' => 'Viraloka',
                    'capability' => 'manage_options',
                    'menu_slug' => 'viraloka-core',
                    'callback' => [$this, 'renderPage'],
                    'icon_url' => $this->getIconUrl(),
                    'position' => 3,
                ],
                [
                    'type' => 'submenu',
                    'parent_slug' => 'viraloka-core',
                    'page_title' => 'Dashboard',
                    'menu_title' => 'Dashboard',
                    'capability' => 'manage_options',
                    'menu_slug' => 'viraloka-core',
                    'callback' => [$this, 'renderPage'],
                ],
                [
                    'type' => 'submenu',
                    'parent_slug' => 'viraloka-core',
                    'page_title' => 'Modules',
                    'menu_title' => 'Modules',
                    'capability' => 'manage_options',
                    'menu_slug' => 'viraloka-modules',
                    'callback' => [$this, 'renderModulesPage'],
                ],
                [
                    'type' => 'submenu',
                    'parent_slug' => 'viraloka-core',
                    'page_title' => 'System Status',
                    'menu_title' => 'System',
                    'capability' => 'manage_options',
                    'menu_slug' => 'viraloka-status',
                    'callback' => [$this, 'renderStatusPage'],
                ],
            ],
        ]);
    }
    
    /**
     * Get icon URL
     * 
     * @return string
     */
    protected function getIconUrl(): string
    {
        // Use plugin URL constant for WordPress menu icon
        return VIRALOKA_CORE_URL . 'src/Img/vira.png';
    }
    
    /**
     * Render main dashboard page
     * 
     * @return void
     */
    public function renderPage(): void
    {
        $pageTitle = 'Viraloka Core';
        
        $html = '<div class="wrap">';
        $html .= '<h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
        $html .= '<div class="viraloka-dashboard">';
        $html .= '<div class="viraloka-card">';
        $html .= '<h2>Welcome to Viraloka Core</h2>';
        $html .= '<p>Viraloka Core is a modular framework for building applications with dynamic module discovery and management.</p>';
        $html .= '<h3>Quick Stats</h3>';
        $html .= $this->renderQuickStats();
        $html .= '</div>';
        $html .= '<div class="viraloka-card">';
        $html .= '<h3>System Information</h3>';
        $html .= $this->renderSystemInfo();
        $html .= '</div>';
        $html .= '</div>';
        $html .= $this->getStyles();
        $html .= '</div>';
        
        echo $html;
    }
    
    /**
     * Get admin page styles
     * 
     * @return string
     */
    protected function getStyles(): string
    {
        return '<style>
            .viraloka-dashboard {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }
            .viraloka-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .viraloka-card h2, .viraloka-card h3 {
                margin-top: 0;
            }
            .viraloka-stat {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            .viraloka-stat:last-child {
                border-bottom: none;
            }
        </style>';
    }
    
    /**
     * Render quick stats
     * 
     * @return string
     */
    protected function renderQuickStats(): string
    {
        $registry = $this->app->make(ModuleRegistryContract::class);
        $modules = $registry->all();
        
        $coreVersion = defined('VIRALOKA_CORE_VERSION') ? VIRALOKA_CORE_VERSION : '1.0.0';
        
        $html = '<div class="viraloka-stat">';
        $html .= '<strong>Loaded Modules:</strong>';
        $html .= '<span>' . count($modules) . '</span>';
        $html .= '</div>';
        $html .= '<div class="viraloka-stat">';
        $html .= '<strong>Core Version:</strong>';
        $html .= '<span>' . htmlspecialchars($coreVersion, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
        $html .= '<div class="viraloka-stat">';
        $html .= '<strong>Bootstrap Status:</strong>';
        $html .= '<span style="color: #46b450;">✓ Active</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render system information
     * 
     * @return string
     */
    protected function renderSystemInfo(): string
    {
        // Get system info from runtime adapter
        $environment = $this->runtimeAdapter->environment();
        $phpVersion = PHP_VERSION;
        $memoryLimit = ini_get('memory_limit');
        
        $html = '<div class="viraloka-stat">';
        $html .= '<strong>PHP Version:</strong>';
        $html .= '<span>' . htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
        $html .= '<div class="viraloka-stat">';
        $html .= '<strong>Environment:</strong>';
        $html .= '<span>' . htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
        $html .= '<div class="viraloka-stat">';
        $html .= '<strong>Memory Limit:</strong>';
        $html .= '<span>' . htmlspecialchars($memoryLimit, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render modules page
     * 
     * @return void
     */
    public function renderModulesPage(): void
    {
        $registry = $this->app->make(ModuleRegistryContract::class);
        $modules = $registry->all();
        
        $pageTitle = 'Modules';
        
        $html = '<div class="wrap">';
        $html .= '<h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr>';
        $html .= '<th>Module</th>';
        $html .= '<th>Version</th>';
        $html .= '<th>Description</th>';
        $html .= '<th>Status</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        
        foreach ($modules as $module) {
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($module->manifest->name, ENT_QUOTES, 'UTF-8') . '</strong></td>';
            $html .= '<td>' . htmlspecialchars($module->manifest->version, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($module->manifest->description, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td><span style="color: #46b450;">Active</span></td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        echo $html;
    }
    
    /**
     * Render status page
     * 
     * @return void
     */
    public function renderStatusPage(): void
    {
        $pageTitle = 'System Status';
        
        $html = '<div class="wrap">';
        $html .= '<h1>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</h1>';
        
        $html .= '<h2>Bootstrap Status</h2>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<tbody>';
        $html .= '<tr><td><strong>Security Guard:</strong></td><td><span style="color: #46b450;">✓ Active</span></td></tr>';
        $html .= '<tr><td><strong>Performance Guard:</strong></td><td><span style="color: #46b450;">✓ Active</span></td></tr>';
        $html .= '<tr><td><strong>Workspace Resolver:</strong></td><td><span style="color: #46b450;">✓ Active</span></td></tr>';
        $html .= '<tr><td><strong>Context Resolver:</strong></td><td><span style="color: #46b450;">✓ Active</span></td></tr>';
        $html .= '<tr><td><strong>Module Registry:</strong></td><td><span style="color: #46b450;">✓ Active</span></td></tr>';
        $html .= '</tbody>';
        $html .= '</table>';
        
        $html .= '<h2>System Requirements</h2>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<tbody>';
        
        $phpVersion = PHP_VERSION;
        $phpCheck = version_compare($phpVersion, '8.0', '>=');
        $phpStatus = $phpCheck 
            ? '<span style="color: #46b450;">✓ ' . htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') . '</span>'
            : '<span style="color: #dc3232;">✗ ' . htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') . '</span>';
        
        $html .= '<tr><td><strong>PHP Version (>= 8.0):</strong></td><td>' . $phpStatus . '</td></tr>';
        
        $environment = $this->runtimeAdapter->environment();
        $html .= '<tr><td><strong>Environment:</strong></td><td>' . htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        echo $html;
    }
}
