<?php

namespace Viraloka\Core\Context;

use Viraloka\Core\Container\Container;
use Viraloka\Core\Container\Contracts\ContainerInterface;

/**
 * Context-Aware Container
 * 
 * A wrapper around the base Container that provides simplified context-aware
 * service binding and resolution. This class uses the ContextResolver to
 * determine the active context and automatically binds services based on
 * the current context.
 * 
 * Usage Example:
 * ```php
 * $contextContainer = new ContextAwareContainer($contextResolver, $container);
 * 
 * // Register context-specific bindings with fallback
 * $contextContainer->bindContextual(
 *     PaymentGatewayInterface::class,
 *     [
 *         'marketplace' => fn($c) => new StripeGateway(),
 *         'subscription' => fn($c) => new RecurringGateway(),
 *     ],
 *     fn($c) => new DefaultGateway() // fallback
 * );
 * 
 * // Resolve based on current context
 * $gateway = $contextContainer->resolve(PaymentGatewayInterface::class);
 * ```
 * 
 * Validates: Requirements 7.1, 7.2, 7.3, 7.4
 */
class ContextAwareContainer
{
    /**
     * Context resolver for determining active context
     * 
     * @var ContextResolver
     */
    private ContextResolver $contextResolver;
    
    /**
     * Base container for service resolution
     * 
     * @var Container
     */
    private Container $container;
    
    /**
     * Context-specific bindings storage
     * Format: [abstract => [context => callable, ...], ...]
     * 
     * @var array<string, array<string, callable>>
     */
    private array $contextualBindings = [];
    
    /**
     * Fallback bindings for when no context match exists
     * Format: [abstract => callable, ...]
     * 
     * @var array<string, callable>
     */
    private array $fallbackBindings = [];
    
    /**
     * Create a new context-aware container instance
     * 
     * @param ContextResolver $contextResolver Resolver for determining active context
     * @param Container $container Base container for service resolution
     */
    public function __construct(
        ContextResolver $contextResolver,
        Container $container
    ) {
        $this->contextResolver = $contextResolver;
        $this->container = $container;
    }
    
    /**
     * Bind a service with context-specific implementations
     * 
     * Registers multiple context-specific bindings for a single service interface,
     * along with a fallback binding that is used when the current context doesn't
     * match any of the specified contexts.
     * 
     * Example:
     * ```php
     * $container->bindContextual(
     *     'PaymentService',
     *     [
     *         'marketplace' => fn($c) => new MarketplacePayment(),
     *         'subscription' => fn($c) => new SubscriptionPayment(),
     *     ],
     *     fn($c) => new DefaultPayment()
     * );
     * ```
     * 
     * Validates: Requirements 7.1, 7.2, 7.4
     * 
     * @param string $abstract The service identifier (interface or abstract class)
     * @param array<string, callable> $contextBindings Map of context keys to resolver callables
     * @param callable $fallback Fallback resolver when no context matches
     * @return void
     */
    public function bindContextual(
        string $abstract,
        array $contextBindings,
        callable $fallback
    ): void {
        // Store the context-specific bindings
        $this->contextualBindings[$abstract] = $contextBindings;
        
        // Store the fallback binding
        $this->fallbackBindings[$abstract] = $fallback;
        
        // Register each context-specific binding with the base container
        foreach ($contextBindings as $contextKey => $resolver) {
            $this->container->bindForContext($contextKey, $abstract, $resolver);
        }
        
        // Register the fallback binding as a global binding
        $this->container->bind($abstract, $fallback);
    }
    
    /**
     * Resolve a service based on current context
     * 
     * Gets the current context from the ContextResolver and resolves the service
     * using the appropriate context-specific binding. If no context-specific
     * binding exists for the current context, falls back to the default binding.
     * 
     * Resolution Order:
     * 1. Check if context-specific binding exists for current primary context
     * 2. Use context-specific binding if available
     * 3. Fall back to default binding if no context match
     * 
     * Validates: Requirements 7.2, 7.3, 7.4
     * 
     * @param string $abstract The service identifier to resolve
     * @return mixed The resolved service instance
     * @throws \Viraloka\Core\Container\Exceptions\ServiceNotFoundException If service not found
     */
    public function resolve(string $abstract): mixed
    {
        // Get the current primary context
        $primaryContext = $this->contextResolver->getPrimaryContext();
        
        // If we have a primary context and context-specific bindings exist
        if ($primaryContext !== null && isset($this->contextualBindings[$abstract])) {
            $contextKey = $primaryContext->getKey();
            
            // Check if a binding exists for this specific context
            if (isset($this->contextualBindings[$abstract][$contextKey])) {
                // The container will automatically use the context-specific binding
                // because we registered it with bindForContext()
                return $this->container->get($abstract);
            }
        }
        
        // Fall back to default binding
        // The container will use the global binding we registered
        return $this->container->get($abstract);
    }
    
    /**
     * Get the underlying container instance
     * 
     * Provides access to the base container for advanced operations
     * that are not context-aware.
     * 
     * @return Container The base container instance
     */
    public function getContainer(): Container
    {
        return $this->container;
    }
    
    /**
     * Get the context resolver instance
     * 
     * Provides access to the context resolver for querying context information.
     * 
     * @return ContextResolver The context resolver instance
     */
    public function getContextResolver(): ContextResolver
    {
        return $this->contextResolver;
    }
}
