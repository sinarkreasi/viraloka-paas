<?php

declare(strict_types=1);

namespace Viraloka\Core\Context;

/**
 * RecommendationGraph
 * 
 * Provides context-based recommendations for modules, integrations, and UI hints.
 * Does not enforce dependencies or auto-activate modules.
 * 
 * Requirements: 4.1, 4.2, 4.3, 4.6
 */
class RecommendationGraph
{
    /**
     * @var array<string, array> Recommendations indexed by context key
     */
    private array $recommendations = [];

    /**
     * Register recommendations for a context
     * 
     * @param string $contextKey The context identifier
     * @param array $recommendations Recommendations structure containing:
     *                               - recommended_modules: array of module names
     *                               - optional_integrations: array of integration names
     *                               - ui_hints: array of UI configuration hints
     */
    public function register(string $contextKey, array $recommendations): void
    {
        $this->recommendations[$contextKey] = $recommendations;
    }

    /**
     * Get all recommendations for a context
     * 
     * @param string $contextKey The context identifier
     * @return array Recommendations structure or empty array if context not found
     */
    public function getRecommendations(string $contextKey): array
    {
        return $this->recommendations[$contextKey] ?? [];
    }

    /**
     * Get recommended modules for a context
     * 
     * @param string $contextKey The context identifier
     * @return array List of recommended module names
     */
    public function getRecommendedModules(string $contextKey): array
    {
        $recommendations = $this->getRecommendations($contextKey);
        return $recommendations['recommended_modules'] ?? [];
    }

    /**
     * Get optional integrations for a context
     * 
     * @param string $contextKey The context identifier
     * @return array List of optional integration names
     */
    public function getOptionalIntegrations(string $contextKey): array
    {
        $recommendations = $this->getRecommendations($contextKey);
        return $recommendations['optional_integrations'] ?? [];
    }

    /**
     * Get UI hints for a context
     * 
     * @param string $contextKey The context identifier
     * @return array UI configuration hints
     */
    public function getUIHints(string $contextKey): array
    {
        $recommendations = $this->getRecommendations($contextKey);
        return $recommendations['ui_hints'] ?? [];
    }
}
