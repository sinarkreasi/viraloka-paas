<?php

namespace Viraloka\Core\Frontend;

use Viraloka\Core\Application;
use Viraloka\Core\Modules\Contracts\ModuleRegistryContract;
use Viraloka\Core\Adapter\Contracts\EventAdapterInterface;
use Viraloka\Core\Adapter\Contracts\ResponseAdapterInterface;
use Viraloka\Core\Adapter\AdapterRegistry;

/**
 * Dashboard Renderer
 * 
 * Renders the dashboard layout with integrated module UI components
 * and support for theme customization.
 */
class DashboardRenderer
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
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
     * The response adapter instance
     * 
     * @var ResponseAdapterInterface
     */
    protected ResponseAdapterInterface $responseAdapter;
    
    /**
     * Registered module UI components
     * 
     * @var array
     */
    protected array $moduleUIs = [];
    
    /**
     * Dashboard sections
     * 
     * @var array
     */
    protected array $sections = [
        'header' => [],
        'sidebar' => [],
        'main' => [],
        'footer' => [],
    ];
    
    /**
     * Theme customization options
     * 
     * @var array
     */
    protected array $themeOptions = [];
    
    /**
     * Create a new DashboardRenderer instance
     * 
     * @param Application $app
     * @param ModuleRegistryContract $moduleRegistry
     * @param AdapterRegistry $adapterRegistry
     */
    public function __construct(
        Application $app, 
        ModuleRegistryContract $moduleRegistry,
        AdapterRegistry $adapterRegistry
    ) {
        $this->app = $app;
        $this->moduleRegistry = $moduleRegistry;
        $this->eventAdapter = $adapterRegistry->event();
        $this->responseAdapter = $adapterRegistry->response();
    }
    
    /**
     * Register a module UI component
     * 
     * @param string $moduleId
     * @param array $uiConfig
     * @return void
     */
    public function registerModuleUI(string $moduleId, array $uiConfig): void
    {
        $this->moduleUIs[$moduleId] = $uiConfig;
        
        // Add to appropriate sections
        $section = $uiConfig['section'] ?? 'main';
        $priority = $uiConfig['priority'] ?? 10;
        
        if (!isset($this->sections[$section])) {
            $this->sections[$section] = [];
        }
        
        $this->sections[$section][] = [
            'moduleId' => $moduleId,
            'config' => $uiConfig,
            'priority' => $priority,
        ];
        
        // Sort by priority (higher priority first)
        usort($this->sections[$section], function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
    }
    
    /**
     * Set theme customization options
     * 
     * @param array $options
     * @return void
     */
    public function setThemeOptions(array $options): void
    {
        $this->themeOptions = array_merge($this->themeOptions, $options);
    }
    
    /**
     * Render the dashboard
     * 
     * @return void
     */
    public function render(): void
    {
        // Allow themes to customize rendering
        $this->eventAdapter->dispatch('viraloka_before_dashboard_render', ['renderer' => $this]);
        
        // Start output buffering
        ob_start();
        
        // Render dashboard structure
        $this->renderDashboardStructure();
        
        // Get buffered content
        $content = ob_get_clean();
        
        // Allow themes to filter content
        $result = $this->eventAdapter->dispatch('viraloka_dashboard_content', [
            'content' => $content,
            'renderer' => $this
        ]);
        $content = $result['content'] ?? $content;
        
        // Output final content
        echo $content;
        
        // After render hook
        $this->eventAdapter->dispatch('viraloka_after_dashboard_render', ['renderer' => $this]);
    }
    
    /**
     * Render the dashboard structure
     * 
     * @return void
     */
    protected function renderDashboardStructure(): void
    {
        $theme = htmlspecialchars($this->getThemeOption('theme', 'default'), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="viraloka-dashboard" data-theme="<?php echo $theme; ?>">
            <?php $this->renderSection('header'); ?>
            
            <div class="viraloka-dashboard-body">
                <?php $this->renderSection('sidebar'); ?>
                
                <main class="viraloka-dashboard-main">
                    <?php $this->renderSection('main'); ?>
                </main>
            </div>
            
            <?php $this->renderSection('footer'); ?>
        </div>
        <?php
    }
    
    /**
     * Render a dashboard section
     * 
     * @param string $section
     * @return void
     */
    protected function renderSection(string $section): void
    {
        if (!isset($this->sections[$section]) || empty($this->sections[$section])) {
            return;
        }
        
        // Allow themes to customize section rendering
        $this->eventAdapter->dispatch("viraloka_before_{$section}_section", ['renderer' => $this]);
        
        $sectionClass = htmlspecialchars($section, ENT_QUOTES, 'UTF-8');
        echo '<div class="viraloka-dashboard-' . $sectionClass . '">';
        
        foreach ($this->sections[$section] as $component) {
            $this->renderComponent($component);
        }
        
        echo '</div>';
        
        // After section hook
        $this->eventAdapter->dispatch("viraloka_after_{$section}_section", ['renderer' => $this]);
    }
    
    /**
     * Render a UI component
     * 
     * @param array $component
     * @return void
     */
    protected function renderComponent(array $component): void
    {
        $moduleId = $component['moduleId'];
        $config = $component['config'];
        
        // Allow modules to render their own UI
        $result = $this->eventAdapter->dispatch("viraloka_render_module_ui_{$moduleId}", [
            'config' => $config,
            'renderer' => $this,
            'rendered' => false
        ]);
        
        // Default rendering if module doesn't provide custom rendering
        if (!($result['rendered'] ?? false)) {
            $this->renderDefaultComponent($moduleId, $config);
        }
    }
    
    /**
     * Render a default component
     * 
     * @param string $moduleId
     * @param array $config
     * @return void
     */
    protected function renderDefaultComponent(string $moduleId, array $config): void
    {
        $title = $config['title'] ?? $moduleId;
        $content = $config['content'] ?? '';
        
        $moduleIdEscaped = htmlspecialchars($moduleId, ENT_QUOTES, 'UTF-8');
        $titleEscaped = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        
        ?>
        <div class="viraloka-module-ui" data-module="<?php echo $moduleIdEscaped; ?>">
            <?php if (!empty($title)): ?>
                <h3 class="viraloka-module-title"><?php echo $titleEscaped; ?></h3>
            <?php endif; ?>
            
            <div class="viraloka-module-content">
                <?php
                if (is_callable($content)) {
                    call_user_func($content, $this->app);
                } else {
                    echo $this->responseAdapter->sanitizeHtml($content);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get a theme option
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getThemeOption(string $key, $default = null)
    {
        return $this->themeOptions[$key] ?? $default;
    }
    
    /**
     * Get all registered module UIs
     * 
     * @return array
     */
    public function getModuleUIs(): array
    {
        return $this->moduleUIs;
    }
    
    /**
     * Get components for a specific section
     * 
     * @param string $section
     * @return array
     */
    public function getSectionComponents(string $section): array
    {
        return $this->sections[$section] ?? [];
    }
}
