<?php

namespace Viraloka\Core\Context\Contracts;

use Illuminate\Support\Collection;
use Viraloka\Core\Modules\Manifest;
use Viraloka\Core\Modules\Module;

/**
 * Context Resolver Contract
 * 
 * Matches modules to contexts and determines priority ordering.
 */
interface ContextResolverContract
{
    /**
     * Get current context
     * 
     * @return string
     */
    public function getCurrentContext(): string;
    
    /**
     * Check if module supports context
     * 
     * @param Manifest $manifest
     * @param string $context
     * @return bool
     */
    public function supportsContext(Manifest $manifest, string $context): bool;
    
    /**
     * Get modules for context ordered by priority
     * 
     * @param string $context
     * @return Collection<Module>
     */
    public function getModulesForContext(string $context): Collection;
    
    /**
     * Build recommendation graph for module suggestions
     * 
     * @return array
     */
    public function buildRecommendationGraph(): array;
    
    /**
     * Inject context into Service Container
     * 
     * @return void
     */
    public function injectIntoContainer(): void;
    
    /**
     * Detect if current context indicates physical marketplace use-case
     * 
     * @return bool
     */
    public function isPhysicalMarketplace(): bool;
    
    /**
     * Get contextual recommendations for out-of-scope use-cases
     * 
     * @return array{detected: bool, use_case: string|null, recommendations: array}
     */
    public function getOutOfScopeRecommendations(): array;
}
