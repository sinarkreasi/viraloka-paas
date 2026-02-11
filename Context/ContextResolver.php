<?php

namespace Viraloka\Core\Context;

use Viraloka\Core\Workspace\WorkspaceResolver;
use Viraloka\Core\Theme\ThemeIntegration;
use Viraloka\Core\Modules\Logger;
use Viraloka\Core\Context\Contracts\ContextResolverContract;
use Viraloka\Core\Modules\Manifest;
use Viraloka\Core\Modules\Module;
use Illuminate\Support\Collection;

/**
 * Context Resolver
 * 
 * The main resolver class that determines the active context for each request.
 * Orchestrates the resolution flow from multiple sources (workspace, theme, system default)
 * and builds an immutable context stack.
 * 
 * Resolution Flow:
 * 1. Resolve the workspace
 * 2. Load context from all available sources
 * 3. Normalize context identifiers
 * 4. Build an immutable context stack with priority ordering
 * 5. Determine the primary context
 * 6. Expose the context to the container and modules
 * 
 * Validates: Requirements 1.1, 1.2, 1.7, 5.1, 8.1, 8.2, 8.3, 8.4
 */
class ContextResolver implements ContextResolverContract
{
    /**
     * Workspace resolver for determining active workspace
     * 
     * @var WorkspaceResolver
     */
    private WorkspaceResolver $workspaceResolver;
    
    /**
     * Theme integration for loading theme context
     * 
     * @var ThemeIntegration
     */
    private ThemeIntegration $themeProvider;
    
    /**
     * Context stack builder for creating the context stack
     * 
     * @var ContextStackBuilder
     */
    private ContextStackBuilder $stackBuilder;
    
    /**
     * Recommendation graph for context-based recommendations
     * 
     * @var RecommendationGraph
     */
    private RecommendationGraph $recommendationGraph;
    
    /**
     * Logger for error and warning messages
     * 
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Cached resolved context stack
     * 
     * @var ContextStack|null
     */
    private ?ContextStack $contextStack = null;
    
    /**
     * Create a new context resolver instance
     * 
     * @param WorkspaceResolver $workspaceResolver Resolver for workspace context
     * @param ThemeIntegration $themeProvider Provider for theme context
     * @param ContextStackBuilder $stackBuilder Builder for context stack
     * @param RecommendationGraph $recommendationGraph Graph for recommendations
     * @param Logger|null $logger Logger for error and warning messages
     */
    public function __construct(
        WorkspaceResolver $workspaceResolver,
        ThemeIntegration $themeProvider,
        ContextStackBuilder $stackBuilder,
        RecommendationGraph $recommendationGraph,
        ?Logger $logger = null
    ) {
        $this->workspaceResolver = $workspaceResolver;
        $this->themeProvider = $themeProvider;
        $this->stackBuilder = $stackBuilder;
        $this->recommendationGraph = $recommendationGraph;
        $this->logger = $logger ?? new Logger();
    }
    
    /**
     * Resolve context for the current request
     * 
     * Orchestrates the complete resolution flow:
     * 1. Resolve workspace first
     * 2. Load workspace context if available
     * 3. Load theme context if available
     * 4. Always add system default context
     * 5. Pass sources to ContextStackBuilder
     * 6. Cache and return the built ContextStack
     * 
     * The resolved context stack is cached for the duration of the request.
     * 
     * Error Handling:
     * - Workspace resolution failures are caught and logged
     * - Theme loading failures are caught and logged
     * - If all sources fail, system default context is ensured
     * - Never throws exceptions - always returns a valid context stack
     * 
     * Validates: Requirements 1.1, 1.2, 1.3, 1.5, 1.6, 1.7, 8.1, 8.2, 8.3, 8.4
     * 
     * @return ContextStack The resolved and frozen context stack
     */
    public function resolve(): ContextStack
    {
        // Return cached stack if already resolved
        if ($this->contextStack !== null) {
            return $this->contextStack;
        }
        
        // Initialize sources array and track failures
        $sources = [];
        $failedSources = [];
        
        // Step 1 & 2: Resolve workspace and load workspace context if available
        try {
            $workspace = $this->workspaceResolver->resolve();
            
            // Use the new Workspace entity's activeContext property
            if (!empty($workspace->activeContext) && $workspace->activeContext !== 'default') {
                $sources[] = new WorkspaceContext(
                    $workspace->activeContext,
                    [
                        'workspace_id' => $workspace->workspaceId,
                        'workspace_name' => $workspace->name,
                        'tenant_id' => $workspace->tenantId,
                        'slug' => $workspace->slug,
                        'status' => $workspace->status,
                        'custom_domain' => $workspace->customDomain,
                        'subdomain' => $workspace->subdomain,
                        'is_default' => $workspace->isDefault
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Log workspace resolution failure
            $failedSources[] = 'workspace';
            $this->logger->error(
                "Workspace context resolution failed: {$e->getMessage()}",
                null,
                'Context Resolution'
            );
        }
        
        // Step 3: Load theme context if available
        try {
            $themeHints = $this->themeProvider->getUIHints();
            if (!empty($themeHints['context'])) {
                $sources[] = new ThemeContext(
                    $themeHints['context'],
                    [
                        'theme_hints' => $themeHints
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Log theme context loading failure
            $failedSources[] = 'theme';
            $this->logger->error(
                "Theme context loading failed: {$e->getMessage()}",
                null,
                'Context Resolution'
            );
        }
        
        // Step 4: Always add system default context
        // This ensures we always have at least one context source
        $sources[] = new SystemDefaultContext();
        
        // Log warning if we're using fallback
        if (!empty($failedSources)) {
            $this->logger->warning(
                "Context resolution using fallback. Failed sources: " . implode(', ', $failedSources),
                null
            );
        }
        
        // Step 5: Pass sources to ContextStackBuilder
        $this->contextStack = $this->stackBuilder->build($sources);
        
        // Step 6: Return the built and frozen ContextStack
        return $this->contextStack;
    }
    
    /**
     * Get the current context stack
     * 
     * Returns the cached context stack. If resolve() has not been called yet,
     * this method will trigger resolution.
     * 
     * Validates: Requirements 1.7, 5.1
     * 
     * @return ContextStack The current context stack
     */
    public function getContextStack(): ContextStack
    {
        if ($this->contextStack === null) {
            $this->resolve();
        }
        
        return $this->contextStack;
    }
    
    /**
     * Get the primary (highest priority) context
     * 
     * Returns the primary context from the context stack.
     * The primary context is the highest-priority context in the stack.
     * 
     * Validates: Requirements 1.7, 5.1
     * 
     * @return ContextInterface|null The primary context or null if stack is empty
     */
    public function getPrimaryContext(): ?ContextInterface
    {
        $stack = $this->getContextStack();
        return $stack->getPrimary();
    }
    
    /**
     * Get recommendations for the current context
     * 
     * Queries the RecommendationGraph using the primary context key
     * to retrieve context-appropriate recommendations for modules,
     * integrations, and UI hints.
     * 
     * Validates: Requirements 1.7, 5.1
     * 
     * @return array Recommendations array containing:
     *               - recommended_modules: array of module names
     *               - optional_integrations: array of integration names
     *               - ui_hints: array of UI configuration hints
     */
    public function getRecommendations(): array
    {
        $primaryContext = $this->getPrimaryContext();
        
        if ($primaryContext === null) {
            return [];
        }
        
        return $this->recommendationGraph->getRecommendations($primaryContext->getKey());
    }
    
    // ========================================================================
    // Module Interaction Methods
    // ========================================================================
    
    /**
     * Check if a specific context is active in the context stack
     * 
     * Allows modules to check if a specific context is present in the
     * current context stack. This enables context-aware behavior without
     * coupling modules to specific context values.
     * 
     * Modules cannot modify the context - they can only query it.
     * The context stack is immutable and read-only.
     * 
     * Validates: Requirements 5.1, 5.3
     * 
     * @param string $contextKey The context key to check for
     * @return bool True if the context is active, false otherwise
     */
    public function isContextActive(string $contextKey): bool
    {
        $stack = $this->getContextStack();
        return $stack->hasContext($contextKey);
    }
    
    /**
     * Get all active contexts for module querying
     * 
     * Returns an array of all active context keys in priority order.
     * This allows modules to query the complete context stack without
     * being able to modify it.
     * 
     * Modules should not assume a single context exists - they should
     * handle multiple contexts appropriately.
     * 
     * Validates: Requirements 5.1, 5.3, 5.4
     * 
     * @return array<string> Array of context keys in priority order
     */
    public function getActiveContexts(): array
    {
        $stack = $this->getContextStack();
        $contexts = $stack->getAll();
        
        return array_map(function (ContextInterface $context) {
            return $context->getKey();
        }, $contexts);
    }
    
    /**
     * Get context metadata for a specific context
     * 
     * Allows modules to access metadata associated with a specific context.
     * Returns null if the context is not active.
     * 
     * Modules cannot modify the context or its metadata - this is read-only access.
     * 
     * Validates: Requirements 5.1, 5.3
     * 
     * @param string $contextKey The context key to get metadata for
     * @return array|null Context metadata or null if context not found
     */
    public function getContextMetadata(string $contextKey): ?array
    {
        $stack = $this->getContextStack();
        $context = $stack->getContext($contextKey);
        
        if ($context === null) {
            return null;
        }
        
        return $context->getMetadata();
    }
    
    // ========================================================================
    // Theme Interaction Methods
    // ========================================================================
    
    /**
     * Register theme context declaration
     * 
     * Allows themes to declare their context during loading. This context
     * will be included in the context resolution process with medium priority.
     * 
     * IMPORTANT: Themes cannot access modules directly. They can only:
     * - Declare their context
     * - Contribute to the recommendation graph
     * - Provide UI hints
     * 
     * Themes cannot:
     * - Modify core system behavior
     * - Access module instances directly
     * - Change the context stack after resolution
     * 
     * This method should be called during theme initialization, before
     * context resolution occurs.
     * 
     * Validates: Requirements 6.1, 6.2, 6.3
     * 
     * @param string $contextKey The context key the theme declares
     * @param array $metadata Optional metadata about the theme context
     * @return bool True if registration succeeded, false if context already resolved
     */
    public function registerThemeContext(string $contextKey, array $metadata = []): bool
    {
        // Prevent registration after context has been resolved
        if ($this->contextStack !== null) {
            $this->logger->warning(
                "Theme attempted to register context after resolution. Context: {$contextKey}",
                null
            );
            return false;
        }
        
        // Store theme context for later resolution
        // This will be picked up during the resolve() method
        $this->themeProvider->registerContext($contextKey);
        
        return true;
    }
    
    /**
     * Register theme recommendations to the recommendation graph
     * 
     * Allows themes to contribute recommendations for modules, integrations,
     * and UI hints based on their context. This enables themes to suggest
     * appropriate functionality without directly accessing modules.
     * 
     * IMPORTANT: Themes cannot access modules directly. The recommendation
     * graph only provides hints - it does not auto-activate modules or
     * create hard dependencies.
     * 
     * Themes should use this method to:
     * - Suggest modules that work well with their design
     * - Recommend optional integrations
     * - Provide UI configuration hints
     * 
     * Validates: Requirements 6.1, 6.2, 6.3
     * 
     * @param string $contextKey The context key for these recommendations
     * @param array $recommendations Recommendations structure containing:
     *                               - recommended_modules: array of module names
     *                               - optional_integrations: array of integration names
     *                               - ui_hints: array of UI configuration hints
     * @return void
     */
    public function registerThemeRecommendations(string $contextKey, array $recommendations): void
    {
        // Validate recommendations structure
        $validatedRecommendations = [
            'recommended_modules' => $recommendations['recommended_modules'] ?? [],
            'optional_integrations' => $recommendations['optional_integrations'] ?? [],
            'ui_hints' => $recommendations['ui_hints'] ?? []
        ];
        
        // Register with the recommendation graph
        $this->recommendationGraph->register($contextKey, $validatedRecommendations);
        
        $this->logger->info(
            "Theme registered recommendations for context: {$contextKey}",
            null
        );
    }
    
    /**
     * Get theme-safe context information
     * 
     * Provides read-only access to context information for themes.
     * Themes can use this to adapt their UI based on the active context,
     * but they cannot modify the context or access modules directly.
     * 
     * Validates: Requirements 6.3, 6.4
     * 
     * @return array Array containing:
     *               - primary_context: string|null - The primary context key
     *               - all_contexts: array - All active context keys
     *               - recommendations: array - Recommendations for primary context
     */
    public function getThemeContextInfo(): array
    {
        $primaryContext = $this->getPrimaryContext();
        
        return [
            'primary_context' => $primaryContext ? $primaryContext->getKey() : null,
            'all_contexts' => $this->getActiveContexts(),
            'recommendations' => $this->getRecommendations()
        ];
    }
    
    /**
     * Rebuild context stack (for workspace changes)
     * 
     * Clears the cached context stack and forces re-resolution.
     * This should be called when the workspace changes during a request.
     * 
     * Validates: Requirements 8.5
     * 
     * @return void
     */
    public function rebuildContextStack(): void
    {
        $this->contextStack = null;
        $this->resolve();
    }
    
    // ========================================================================
    // Legacy ContextResolverContract Implementation
    // ========================================================================
    
    /**
     * Get current context (legacy contract method)
     * 
     * Returns the primary context key as a string.
     * 
     * @return string The current primary context key
     */
    public function getCurrentContext(): string
    {
        $primaryContext = $this->getPrimaryContext();
        return $primaryContext ? $primaryContext->getKey() : 'default';
    }
    
    /**
     * Check if module supports context (legacy contract method)
     * 
     * Checks if the module's manifest declares support for the given context.
     * 
     * @param Manifest $manifest The module manifest
     * @param string $context The context key to check
     * @return bool True if the module supports the context
     */
    public function supportsContext(Manifest $manifest, string $context): bool
    {
        // Use the manifest's built-in supportsContext method
        return $manifest->supportsContext($context);
    }
    
    /**
     * Get modules for context ordered by priority (legacy contract method)
     * 
     * This is a stub implementation for backward compatibility.
     * The new context resolver doesn't manage module loading directly.
     * 
     * @param string $context The context key
     * @return Collection<Module> Empty collection
     */
    public function getModulesForContext(string $context): Collection
    {
        // Return empty collection - module loading is handled elsewhere
        return new Collection([]);
    }
    
    /**
     * Build recommendation graph for module suggestions (legacy contract method)
     * 
     * Returns the recommendations for the current primary context.
     * 
     * @return array Recommendations array
     */
    public function buildRecommendationGraph(): array
    {
        return $this->getRecommendations();
    }
    
    /**
     * Inject context into Service Container (legacy contract method)
     * 
     * Resolves the context and makes it available in the container.
     * This is called during bootstrap to initialize the context system.
     * 
     * @return void
     */
    public function injectIntoContainer(): void
    {
        // Simply resolve the context - it's already cached in this singleton
        $this->resolve();
    }
    
    /**
     * Detect if current context indicates physical marketplace use-case (legacy contract method)
     * 
     * Checks if the current context stack contains marketplace-related contexts.
     * 
     * @return bool True if physical marketplace context is detected
     */
    public function isPhysicalMarketplace(): bool
    {
        $stack = $this->getContextStack();
        
        // Check for marketplace-related contexts
        $marketplaceContexts = ['marketplace', 'physical-marketplace', 'retail'];
        
        foreach ($marketplaceContexts as $context) {
            if ($stack->hasContext($context)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get contextual recommendations for out-of-scope use-cases (legacy contract method)
     * 
     * Analyzes the current context to detect out-of-scope use cases
     * and provides recommendations.
     * 
     * @return array{detected: bool, use_case: string|null, recommendations: array}
     */
    public function getOutOfScopeRecommendations(): array
    {
        $primaryContext = $this->getPrimaryContext();
        
        if (!$primaryContext) {
            return [
                'detected' => false,
                'use_case' => null,
                'recommendations' => []
            ];
        }
        
        $contextKey = $primaryContext->getKey();
        
        // Define out-of-scope contexts
        $outOfScopeContexts = [
            'physical-marketplace' => [
                'use_case' => 'Physical Marketplace',
                'recommendations' => [
                    'This appears to be a physical marketplace use case.',
                    'Consider using a dedicated POS or inventory management system.',
                    'Viraloka is optimized for digital services and SaaS platforms.'
                ]
            ]
        ];
        
        if (isset($outOfScopeContexts[$contextKey])) {
            return [
                'detected' => true,
                'use_case' => $outOfScopeContexts[$contextKey]['use_case'],
                'recommendations' => $outOfScopeContexts[$contextKey]['recommendations']
            ];
        }
        
        return [
            'detected' => false,
            'use_case' => null,
            'recommendations' => []
        ];
    }
}
