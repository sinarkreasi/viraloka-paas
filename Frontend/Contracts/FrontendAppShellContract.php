<?php

namespace Viraloka\Core\Frontend\Contracts;

/**
 * Frontend App Shell Contract
 * 
 * Defines the interface for the frontend application shell that provides
 * dashboard rendering, routing, and module UI integration.
 */
interface FrontendAppShellContract
{
    /**
     * Render the dashboard
     * 
     * Renders the main dashboard layout with integrated module UI components.
     * 
     * @return void
     */
    public function render(): void;
    
    /**
     * Route a request to the appropriate handler
     * 
     * Handles URL routing for SaaS applications with context-aware resolution.
     * 
     * @param string $path The request path
     * @return mixed The route handler result
     */
    public function route(string $path);
    
    /**
     * Register a module UI component
     * 
     * Allows modules to register their UI components for seamless integration
     * into the app shell.
     * 
     * @param string $moduleId The module identifier
     * @param array $uiConfig The UI configuration
     * @return void
     */
    public function registerModuleUI(string $moduleId, array $uiConfig): void;
}
