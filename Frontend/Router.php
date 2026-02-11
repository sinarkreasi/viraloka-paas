<?php

namespace Viraloka\Core\Frontend;

use Viraloka\Core\Application;
use Viraloka\Core\Context\ContextResolver;

/**
 * Router
 * 
 * Handles URL routing for SaaS applications with support for dynamic routes
 * from modules and context-aware route resolution.
 */
class Router
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * The context resolver instance
     * 
     * @var ContextResolver
     */
    protected ContextResolver $contextResolver;
    
    /**
     * Registered routes
     * 
     * @var array
     */
    protected array $routes = [];
    
    /**
     * Current context
     * 
     * @var string
     */
    protected string $currentContext;
    
    /**
     * Create a new Router instance
     * 
     * @param Application $app
     * @param ContextResolver $contextResolver
     */
    public function __construct(Application $app, ContextResolver $contextResolver)
    {
        $this->app = $app;
        $this->contextResolver = $contextResolver;
        $this->currentContext = $contextResolver->getCurrentContext();
    }
    
    /**
     * Register a route
     * 
     * @param string $path The route path pattern
     * @param callable $handler The route handler
     * @param array $options Route options (context, priority, etc.)
     * @return void
     */
    public function register(string $path, callable $handler, array $options = []): void
    {
        $this->routes[$path] = [
            'handler' => $handler,
            'context' => $options['context'] ?? null,
            'priority' => $options['priority'] ?? 10,
            'module' => $options['module'] ?? null,
        ];
    }
    
    /**
     * Resolve a route for the given path
     * 
     * Performs context-aware route resolution, matching routes that are
     * compatible with the current context.
     * 
     * @param string $path The request path
     * @return mixed The route handler result or null if no match
     */
    public function resolve(string $path)
    {
        // Normalize path
        $path = $this->normalizePath($path);
        
        // Find matching routes
        $matchingRoutes = $this->findMatchingRoutes($path);
        
        if (empty($matchingRoutes)) {
            return null;
        }
        
        // Filter by context compatibility
        $contextCompatibleRoutes = $this->filterByContext($matchingRoutes);
        
        if (empty($contextCompatibleRoutes)) {
            return null;
        }
        
        // Sort by priority (higher priority first)
        usort($contextCompatibleRoutes, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });
        
        // Execute the highest priority route
        $route = $contextCompatibleRoutes[0];
        return call_user_func($route['handler'], $path, $this->app);
    }
    
    /**
     * Normalize a path
     * 
     * @param string $path
     * @return string
     */
    protected function normalizePath(string $path): string
    {
        // Remove leading and trailing slashes
        $path = trim($path, '/');
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return $path;
    }
    
    /**
     * Find routes matching the given path
     * 
     * Supports exact matches and pattern matching with wildcards.
     * 
     * @param string $path
     * @return array
     */
    protected function findMatchingRoutes(string $path): array
    {
        $matches = [];
        
        foreach ($this->routes as $pattern => $route) {
            if ($this->matchesPattern($path, $pattern)) {
                $matches[] = $route;
            }
        }
        
        return $matches;
    }
    
    /**
     * Check if a path matches a route pattern
     * 
     * Supports wildcards (*) and parameter placeholders ({param}).
     * 
     * @param string $path
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }
        
        // Convert pattern to regex
        $regex = $this->patternToRegex($pattern);
        
        return preg_match($regex, $path) === 1;
    }
    
    /**
     * Convert a route pattern to a regex
     * 
     * @param string $pattern
     * @return string
     */
    protected function patternToRegex(string $pattern): string
    {
        // Escape special regex characters except * and {}
        $pattern = preg_quote($pattern, '/');
        
        // Replace \* with .*
        $pattern = str_replace('\*', '.*', $pattern);
        
        // Replace \{param\} with named capture groups
        $pattern = preg_replace('/\\\{([a-zA-Z0-9_]+)\\\}/', '(?P<$1>[^/]+)', $pattern);
        
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Filter routes by context compatibility
     * 
     * Routes without a context requirement match all contexts.
     * Routes with a context requirement only match if the current context matches.
     * 
     * @param array $routes
     * @return array
     */
    protected function filterByContext(array $routes): array
    {
        return array_filter($routes, function ($route) {
            // No context requirement = matches all contexts
            if ($route['context'] === null) {
                return true;
            }
            
            // Check if current context matches route context
            if (is_array($route['context'])) {
                return in_array($this->currentContext, $route['context']);
            }
            
            return $route['context'] === $this->currentContext;
        });
    }
    
    /**
     * Get all registered routes
     * 
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
    
    /**
     * Get routes for a specific context
     * 
     * @param string $context
     * @return array
     */
    public function getRoutesForContext(string $context): array
    {
        return array_filter($this->routes, function ($route) use ($context) {
            if ($route['context'] === null) {
                return true;
            }
            
            if (is_array($route['context'])) {
                return in_array($context, $route['context']);
            }
            
            return $route['context'] === $context;
        });
    }
}
