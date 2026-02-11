<?php

namespace Viraloka\Core\Container;

use Viraloka\Core\Container\Contracts\ContainerInterface;
use Viraloka\Core\Container\Contracts\ContextResolverInterface;
use Viraloka\Core\Container\Contracts\WorkspaceResolverInterface;
use Viraloka\Core\Container\Contracts\ServiceProviderInterface;
use Viraloka\Core\Container\Exceptions\ServiceNotFoundException;
use Viraloka\Core\Container\Exceptions\CircularDependencyException;
use Viraloka\Core\Events\EventDispatcher;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service container implementation for dependency injection.
 */
class Container implements ContainerInterface
{
    /** @var array<string, Binding> Global bindings */
    private array $bindings = [];

    /** @var array<string, array<string, Binding>> Context-specific bindings [context => [id => Binding]] */
    private array $contextBindings = [];

    /** @var array<string, array<string, array<string, Binding>>> Workspace bindings [context => [workspace => [id => Binding]]] */
    private array $workspaceBindings = [];

    /** @var array<string, object> Cached singleton instances */
    private array $instances = [];

    /** @var array<string, array<string, object>> Scoped instances [scopeKey => [id => instance]] */
    private array $scopedInstances = [];

    /** @var array<string, string> Aliases [alias => originalId] */
    private array $aliases = [];

    /** @var array<string, array<string>> Tags [tag => [id, id, ...]] */
    private array $tags = [];

    /** @var array<string> Current resolution stack for circular dependency detection */
    private array $resolutionStack = [];

    /** @var bool Silent mode for graceful degradation */
    private bool $silentMode = false;

    /** @var LoggerInterface Logger for silent mode */
    private LoggerInterface $logger;

    /** @var array<ServiceProviderInterface> Registered service providers */
    private array $providers = [];

    private ?ContextResolverInterface $contextResolver = null;
    private ?WorkspaceResolverInterface $workspaceResolver = null;
    private ?EventDispatcher $eventDispatcher = null;

    /**
     * Constructor.
     *
     * @param ContextResolverInterface|null $contextResolver Optional context resolver
     * @param WorkspaceResolverInterface|null $workspaceResolver Optional workspace resolver
     * @param EventDispatcher|null $eventDispatcher Optional event dispatcher for binding events
     */
    public function __construct(
        ?ContextResolverInterface $contextResolver = null,
        ?WorkspaceResolverInterface $workspaceResolver = null,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->contextResolver = $contextResolver;
        $this->workspaceResolver = $workspaceResolver;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = new NullLogger();
        
        // Register the container as a self-service so it can be injected
        $this->instance(ContainerInterface::class, $this);
        $this->instance(self::class, $this);
    }

    /**
     * Register a factory binding.
     *
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @param array $options Additional options (tags, lazy, etc.)
     * @return void
     */
    public function bind(string $id, callable|string $resolver, array $options = []): void
    {
        // Check if this is an override
        $isOverride = isset($this->bindings[$id]);

        // If overriding an existing binding, clear any cached instances
        if ($isOverride) {
            $this->clearCachedInstance($id);
        }

        $binding = new Binding(
            id: $id,
            resolver: $resolver,
            type: BindingType::FACTORY,
            lazy: $options['lazy'] ?? false,
            tags: $options['tags'] ?? [],
        );

        $this->bindings[$id] = $binding;

        // Register tags
        foreach ($binding->tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            if (!in_array($id, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $id;
            }
        }

        // Emit binding event
        $this->emitBindingEvent($id, $binding, $isOverride);
    }

    /**
     * Resolve a service.
     *
     * @param string $id The service identifier
     * @return mixed The resolved service instance, or null in silent mode on failure
     * @throws ServiceNotFoundException If the service is not found (when not in silent mode)
     * @throws CircularDependencyException If a circular dependency is detected (when not in silent mode)
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolveService($id);
        } catch (\Throwable $e) {
            if ($this->silentMode) {
                // Log the error and return null
                $this->logger->error("Failed to resolve service '{$id}': {$e->getMessage()}", [
                    'service_id' => $id,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return null;
            }
            
            // Re-throw the exception if not in silent mode
            throw $e;
        }
    }

    /**
     * Internal method to resolve a service (extracted for silent mode handling).
     *
     * @param string $id The service identifier
     * @return mixed The resolved service instance
     * @throws ServiceNotFoundException If the service is not found
     * @throws CircularDependencyException If a circular dependency is detected
     */
    private function resolveService(string $id): mixed
    {
        // Resolve any alias chain to the original ID
        $originalId = $this->resolveAlias($id);

        // Check for circular dependency
        if (in_array($originalId, $this->resolutionStack, true)) {
            // Add the current service to complete the cycle
            $chain = array_merge($this->resolutionStack, [$originalId]);
            throw CircularDependencyException::forChain($chain);
        }

        // Find the binding using resolution order precedence
        $binding = $this->findBinding($originalId);

        // If no binding found, try auto-wiring if it's a class name
        if ($binding === null) {
            if (class_exists($originalId)) {
                // Push to resolution stack before auto-wiring
                $this->resolutionStack[] = $originalId;
                
                try {
                    return $this->autoWire($originalId);
                } finally {
                    // Pop from resolution stack after auto-wiring
                    array_pop($this->resolutionStack);
                }
            }
            
            throw ServiceNotFoundException::forService($id);
        }

        // For singleton bindings, check cache first
        if ($binding->type === BindingType::SINGLETON) {
            if (isset($this->instances[$originalId])) {
                return $this->instances[$originalId];
            }

            // Push to resolution stack before resolving
            $this->resolutionStack[] = $originalId;
            
            try {
                $instance = $this->resolve($binding);
                $this->instances[$originalId] = $instance;
                return $instance;
            } finally {
                // Pop from resolution stack after resolving
                array_pop($this->resolutionStack);
            }
        }

        // For scoped bindings, check scoped cache first
        if ($binding->type === BindingType::SCOPED) {
            $scopeKey = $this->generateScopeKey();
            
            if (isset($this->scopedInstances[$scopeKey][$originalId])) {
                return $this->scopedInstances[$scopeKey][$originalId];
            }

            // Push to resolution stack before resolving
            $this->resolutionStack[] = $originalId;
            
            try {
                $instance = $this->resolve($binding);
                
                // Initialize scope array if it doesn't exist
                if (!isset($this->scopedInstances[$scopeKey])) {
                    $this->scopedInstances[$scopeKey] = [];
                }
                
                $this->scopedInstances[$scopeKey][$originalId] = $instance;
                return $instance;
            } finally {
                // Pop from resolution stack after resolving
                array_pop($this->resolutionStack);
            }
        }

        // For factory bindings
        if ($binding->type === BindingType::FACTORY) {
            // If the binding is marked as lazy, cache it like a singleton after first access
            if ($binding->lazy) {
                // Check if we already have a cached instance
                if (isset($this->instances[$originalId])) {
                    return $this->instances[$originalId];
                }

                // Push to resolution stack before resolving
                $this->resolutionStack[] = $originalId;
                
                try {
                    $instance = $this->resolve($binding);
                    // Cache the instance for subsequent requests
                    $this->instances[$originalId] = $instance;
                    return $instance;
                } finally {
                    // Pop from resolution stack after resolving
                    array_pop($this->resolutionStack);
                }
            }

            // For non-lazy factory bindings, always create a new instance
            // Push to resolution stack before resolving
            $this->resolutionStack[] = $originalId;
            
            try {
                return $this->resolve($binding);
            } finally {
                // Pop from resolution stack after resolving
                array_pop($this->resolutionStack);
            }
        }

        // This shouldn't happen, but adding for completeness
        throw ServiceNotFoundException::forService($id);
    }

    /**
     * Generate a scope key from the current context and workspace.
     *
     * @return string The scope key
     */
    private function generateScopeKey(): string
    {
        $context = $this->contextResolver?->getCurrentContext() ?? 'default';
        $workspace = $this->workspaceResolver?->getCurrentWorkspace();
        
        // If no workspace, scope is just the context
        if ($workspace === null) {
            return $context;
        }
        
        // Combine context and workspace for the scope key
        return "{$context}:{$workspace}";
    }

    /**
     * Find a binding using resolution order precedence.
     * Order: Context+Workspace > Context > Global
     *
     * @param string $id The service identifier
     * @return Binding|null The binding or null if not found
     */
    private function findBinding(string $id): ?Binding
    {
        // Get current context and workspace if resolvers are available
        $context = $this->contextResolver?->getCurrentContext();
        $workspace = $this->workspaceResolver?->getCurrentWorkspace();

        // 1. Check context+workspace binding (highest priority)
        if ($context !== null && $workspace !== null) {
            if (isset($this->workspaceBindings[$context][$workspace][$id])) {
                return $this->workspaceBindings[$context][$workspace][$id];
            }
        }

        // 2. Check context binding (medium priority)
        if ($context !== null) {
            if (isset($this->contextBindings[$context][$id])) {
                return $this->contextBindings[$context][$id];
            }
        }

        // 3. Check global binding (lowest priority)
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id];
        }

        // No binding found
        return null;
    }

    /**
     * Resolve a binding by invoking its resolver.
     *
     * @param Binding $binding The binding to resolve
     * @return mixed The resolved instance
     * @throws Exceptions\ContainerException If the resolver throws an exception
     */
    private function resolve(Binding $binding): mixed
    {
        $resolver = $binding->resolver;

        try {
            if (is_callable($resolver)) {
                return $resolver($this);
            }

            // If it's a class name string, use auto-wiring
            if (is_string($resolver) && class_exists($resolver)) {
                return $this->autoWire($resolver);
            }

            throw new \RuntimeException("Cannot resolve binding for '{$binding->id}'");
        } catch (\Throwable $e) {
            // If it's already a ContainerException, just re-throw it
            if ($e instanceof Exceptions\ContainerException) {
                throw $e;
            }
            
            // Wrap any other exception in a ContainerException
            throw Exceptions\ContainerException::forResolverFailure($binding->id, $e);
        }
    }

    /**
     * Auto-wire a class by resolving its constructor dependencies.
     *
     * @param string $className The class name to instantiate
     * @return object The instantiated object
     * @throws Exceptions\ContainerException If dependencies cannot be resolved
     */
    private function autoWire(string $className): object
    {
        try {
            $reflection = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            throw new Exceptions\ContainerException(
                "Cannot auto-wire '{$className}': class does not exist"
            );
        }

        // If the class has no constructor, just instantiate it
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $className();
        }

        // Resolve constructor parameters
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter, $className);
        }

        // Instantiate the class with resolved dependencies
        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a constructor parameter.
     *
     * @param \ReflectionParameter $parameter The parameter to resolve
     * @param string $className The class name being auto-wired (for error messages)
     * @return mixed The resolved parameter value
     * @throws Exceptions\ContainerException If the parameter cannot be resolved
     */
    private function resolveParameter(\ReflectionParameter $parameter, string $className): mixed
    {
        $type = $parameter->getType();

        // If no type hint, check if parameter has a default value
        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new Exceptions\ContainerException(
                "Cannot auto-wire '{$className}': parameter '\${$parameter->getName()}' has no type hint and no default value"
            );
        }

        // Handle union types (PHP 8.0+)
        if ($type instanceof \ReflectionUnionType) {
            throw new Exceptions\ContainerException(
                "Cannot auto-wire '{$className}': parameter '\${$parameter->getName()}' has a union type which is not supported"
            );
        }

        // Handle named types (single type hint)
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            // If it's a built-in type (int, string, bool, etc.), check for default value
            if ($type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                throw new Exceptions\ContainerException(
                    "Cannot auto-wire '{$className}': parameter '\${$parameter->getName()}' is a built-in type '{$typeName}' and cannot be resolved from the container"
                );
            }

            // It's a class/interface type - try to resolve from container
            try {
                return $this->get($typeName);
            } catch (ServiceNotFoundException $e) {
                // If the parameter is optional (nullable or has default), use the default
                if ($parameter->allowsNull()) {
                    return null;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                // Cannot resolve and no default available
                throw new Exceptions\ContainerException(
                    "Cannot auto-wire '{$className}': parameter '\${$parameter->getName()}' of type '{$typeName}' cannot be resolved from the container"
                );
            }
        }

        // Fallback for other type scenarios
        throw new Exceptions\ContainerException(
            "Cannot auto-wire '{$className}': parameter '\${$parameter->getName()}' has an unsupported type"
        );
    }

    /**
     * Register a singleton binding.
     *
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @param array $options Additional options (lazy, etc.)
     * @return void
     */
    public function singleton(string $id, callable|string $resolver, array $options = []): void
    {
        // Check if this is an override
        $isOverride = isset($this->bindings[$id]);

        // If overriding an existing binding, clear any cached instances
        if ($isOverride) {
            $this->clearCachedInstance($id);
        }

        $binding = new Binding(
            id: $id,
            resolver: $resolver,
            type: BindingType::SINGLETON,
            lazy: $options['lazy'] ?? false,
            tags: [],
        );

        $this->bindings[$id] = $binding;

        // Emit binding event
        $this->emitBindingEvent($id, $binding, $isOverride);
    }

    /**
     * Register a scoped binding (context/workspace aware).
     *
     * @param string $id The service identifier
     * @param callable $resolver The resolver callable
     * @param array $options Additional options (lazy, etc.)
     * @return void
     */
    public function scoped(string $id, callable $resolver, array $options = []): void
    {
        // Check if this is an override
        $isOverride = isset($this->bindings[$id]);

        // If overriding an existing binding, clear any cached instances
        if ($isOverride) {
            $this->clearCachedInstance($id);
        }

        $binding = new Binding(
            id: $id,
            resolver: $resolver,
            type: BindingType::SCOPED,
            lazy: $options['lazy'] ?? false,
            tags: [],
        );

        $this->bindings[$id] = $binding;

        // Emit binding event
        $this->emitBindingEvent($id, $binding, $isOverride);
    }

    /**
     * Register a context-specific binding.
     *
     * @param string $context The context identifier
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @param array $options Additional options (tags, lazy, etc.)
     * @return void
     */
    public function bindForContext(string $context, string $id, callable|string $resolver, array $options = []): void
    {
        // Check if this is an override
        $isOverride = isset($this->contextBindings[$context][$id]);

        // If overriding an existing context binding, clear any cached instances for this context
        if ($isOverride) {
            $this->clearCachedInstanceForContext($id, $context);
        }

        $binding = new Binding(
            id: $id,
            resolver: $resolver,
            type: $options['type'] ?? BindingType::FACTORY,
            lazy: $options['lazy'] ?? false,
            tags: $options['tags'] ?? [],
        );

        // Initialize context array if it doesn't exist
        if (!isset($this->contextBindings[$context])) {
            $this->contextBindings[$context] = [];
        }

        $this->contextBindings[$context][$id] = $binding;

        // Register tags
        foreach ($binding->tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            if (!in_array($id, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $id;
            }
        }

        // Emit binding event with context information
        $this->emitBindingEvent($id, $binding, $isOverride, $context);
    }

    /**
     * Register a workspace-specific binding.
     *
     * @param string $context The context identifier
     * @param string $workspace The workspace identifier
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @param array $options Additional options (tags, lazy, etc.)
     * @return void
     */
    public function bindForWorkspace(string $context, string $workspace, string $id, callable|string $resolver, array $options = []): void
    {
        // Check if this is an override
        $isOverride = isset($this->workspaceBindings[$context][$workspace][$id]);

        // If overriding an existing workspace binding, clear any cached instances for this workspace
        if ($isOverride) {
            $this->clearCachedInstanceForWorkspace($id, $context, $workspace);
        }

        $binding = new Binding(
            id: $id,
            resolver: $resolver,
            type: $options['type'] ?? BindingType::FACTORY,
            lazy: $options['lazy'] ?? false,
            tags: $options['tags'] ?? [],
        );

        // Initialize context array if it doesn't exist
        if (!isset($this->workspaceBindings[$context])) {
            $this->workspaceBindings[$context] = [];
        }

        // Initialize workspace array if it doesn't exist
        if (!isset($this->workspaceBindings[$context][$workspace])) {
            $this->workspaceBindings[$context][$workspace] = [];
        }

        $this->workspaceBindings[$context][$workspace][$id] = $binding;

        // Register tags
        foreach ($binding->tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            if (!in_array($id, $this->tags[$tag], true)) {
                $this->tags[$tag][] = $id;
            }
        }

        // Emit binding event with context and workspace information
        $this->emitBindingEvent($id, $binding, $isOverride, $context, $workspace);
    }

    /**
     * Check if a binding exists.
     *
     * @param string $id The service identifier
     * @return bool True if the binding exists, false otherwise
     */
    public function has(string $id): bool
    {
        // Resolve any alias chain to the original ID
        $originalId = $this->resolveAlias($id);

        // Use findBinding to check with resolution order precedence
        return $this->findBinding($originalId) !== null;
    }

    /**
     * Register an existing instance.
     *
     * @param string $id The service identifier
     * @param object $instance The instance to register
     * @return void
     */
    public function instance(string $id, object $instance): void
    {
        // Store the instance directly in the instances cache
        $this->instances[$id] = $instance;

        // Create a singleton binding that returns the stored instance
        $binding = new Binding(
            id: $id,
            resolver: fn() => $this->instances[$id],
            type: BindingType::SINGLETON,
            lazy: false,
            tags: [],
        );

        $this->bindings[$id] = $binding;
    }

    /**
     * Create an alias for a binding.
     *
     * @param string $alias The alias identifier
     * @param string $id The original service identifier
     * @return void
     */
    public function alias(string $alias, string $id): void
    {
        // Resolve chained aliases - if $id is itself an alias, resolve to the original
        $originalId = $this->resolveAlias($id);
        
        // Store the alias mapping to the original ID
        $this->aliases[$alias] = $originalId;
    }

    /**
     * Resolve an alias to its original service ID, following the chain.
     *
     * @param string $id The service identifier (may be an alias)
     * @return string The original service identifier
     */
    private function resolveAlias(string $id): string
    {
        // Follow the alias chain until we find the original ID
        $visited = [];
        $current = $id;

        while (isset($this->aliases[$current])) {
            // Prevent infinite loops in case of circular aliases
            if (in_array($current, $visited, true)) {
                return $current;
            }
            $visited[] = $current;
            $current = $this->aliases[$current];
        }

        return $current;
    }

    /**
     * Get all services with a given tag.
     *
     * @param string $tag The tag name
     * @return array Array of resolved service instances
     */
    public function tagged(string $tag): array
    {
        // If the tag doesn't exist, return empty array
        if (!isset($this->tags[$tag])) {
            return [];
        }

        // Resolve all services with this tag
        $services = [];
        foreach ($this->tags[$tag] as $serviceId) {
            $services[] = $this->get($serviceId);
        }

        return $services;
    }

    /**
     * Register a service provider.
     * Calls register() immediately and stores the provider for later boot.
     *
     * @param ServiceProviderInterface $provider The service provider to register
     * @return void
     */
    public function registerProvider(ServiceProviderInterface $provider): void
    {
        // Call register() immediately
        $provider->register($this);

        // Store provider reference for boot phase
        $this->providers[] = $provider;
    }

    /**
     * Boot all registered service providers.
     * Calls boot() on each provider in registration order.
     *
     * @return void
     */
    public function bootProviders(): void
    {
        // Call boot() on each provider in registration order
        foreach ($this->providers as $provider) {
            $provider->boot($this);
        }
    }

    /**
     * Enable silent mode for graceful error handling.
     * When enabled, resolution failures will be logged and return null instead of throwing exceptions.
     *
     * @param LoggerInterface $logger The logger to use for error logging
     * @return void
     */
    public function enableSilentMode(LoggerInterface $logger): void
    {
        $this->silentMode = true;
        $this->logger = $logger;
    }

    /**
     * Clear cached instances for a service ID.
     * This is called when a binding is overridden.
     *
     * @param string $id The service identifier
     * @return void
     */
    private function clearCachedInstance(string $id): void
    {
        // Clear singleton instance cache
        if (isset($this->instances[$id])) {
            unset($this->instances[$id]);
        }

        // Clear scoped instance caches across all scopes
        foreach ($this->scopedInstances as $scopeKey => $scopedServices) {
            if (isset($scopedServices[$id])) {
                unset($this->scopedInstances[$scopeKey][$id]);
            }
        }
    }

    /**
     * Clear cached instances for a service ID within a specific context.
     *
     * @param string $id The service identifier
     * @param string $context The context identifier
     * @return void
     */
    private function clearCachedInstanceForContext(string $id, string $context): void
    {
        // Clear singleton instance cache (context overrides affect global cache)
        if (isset($this->instances[$id])) {
            unset($this->instances[$id]);
        }

        // Clear scoped instance caches for this context (all workspaces)
        foreach ($this->scopedInstances as $scopeKey => $scopedServices) {
            // Check if this scope key starts with the context
            if (str_starts_with($scopeKey, $context . ':') || $scopeKey === $context) {
                if (isset($scopedServices[$id])) {
                    unset($this->scopedInstances[$scopeKey][$id]);
                }
            }
        }
    }

    /**
     * Clear cached instances for a service ID within a specific workspace.
     *
     * @param string $id The service identifier
     * @param string $context The context identifier
     * @param string $workspace The workspace identifier
     * @return void
     */
    private function clearCachedInstanceForWorkspace(string $id, string $context, string $workspace): void
    {
        // Clear singleton instance cache (workspace overrides affect global cache)
        if (isset($this->instances[$id])) {
            unset($this->instances[$id]);
        }

        // Clear scoped instance cache for this specific workspace
        $scopeKey = "{$context}:{$workspace}";
        if (isset($this->scopedInstances[$scopeKey][$id])) {
            unset($this->scopedInstances[$scopeKey][$id]);
        }
    }

    /**
     * Emit a binding event when a service is registered or overridden.
     *
     * @param string $id The service identifier
     * @param Binding $binding The binding that was registered
     * @param bool $isOverride Whether this is an override of an existing binding
     * @param string|null $context Optional context for context-scoped bindings
     * @param string|null $workspace Optional workspace for workspace-scoped bindings
     * @return void
     */
    private function emitBindingEvent(
        string $id,
        Binding $binding,
        bool $isOverride,
        ?string $context = null,
        ?string $workspace = null
    ): void {
        // Only emit events if an event dispatcher is configured
        if ($this->eventDispatcher === null) {
            return;
        }

        // Determine the event name based on whether this is an override
        $eventName = $isOverride ? 'container.binding.overridden' : 'container.binding.registered';

        // Prepare event data
        $eventData = [
            'service_id' => $id,
            'binding_type' => $binding->type->value,
            'is_lazy' => $binding->lazy,
            'tags' => $binding->tags,
            'context' => $context,
            'workspace' => $workspace,
            'is_override' => $isOverride,
        ];

        // Dispatch the event
        $this->eventDispatcher->dispatch($eventName, $eventData);
    }
}
