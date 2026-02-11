<?php

namespace Viraloka\Core\Context;

/**
 * Context Stack
 * 
 * Immutable ordered array of context identifiers with priority ordering.
 * The context stack is created once per request and remains constant
 * throughout request processing.
 * 
 * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
 */
class ContextStack
{
    /**
     * @var array<ContextInterface> Ordered array of contexts
     */
    private array $contexts;
    
    /**
     * @var bool Whether the stack is frozen (immutable)
     */
    private bool $frozen = false;
    
    /**
     * Create a new context stack
     * 
     * @param array<ContextInterface> $contexts Array of context objects
     */
    public function __construct(array $contexts)
    {
        $this->contexts = $contexts;
    }
    
    /**
     * Get all contexts in priority order
     * 
     * Returns the complete array of contexts ordered by priority
     * (highest priority first).
     * 
     * @return array<ContextInterface> All contexts in the stack
     */
    public function getAll(): array
    {
        return $this->contexts;
    }
    
    /**
     * Get the primary (first) context
     * 
     * The primary context is the highest-priority context in the stack.
     * Returns null if the stack is empty.
     * 
     * @return ContextInterface|null The primary context or null if empty
     */
    public function getPrimary(): ?ContextInterface
    {
        if (empty($this->contexts)) {
            return null;
        }
        
        return $this->contexts[0];
    }
    
    /**
     * Check if a specific context exists in the stack
     * 
     * @param string $key The context key to search for
     * @return bool True if the context exists, false otherwise
     */
    public function hasContext(string $key): bool
    {
        foreach ($this->contexts as $context) {
            if ($context->getKey() === $key) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get a specific context by key
     * 
     * Returns the first context matching the given key.
     * Returns null if no matching context is found.
     * 
     * @param string $key The context key to search for
     * @return ContextInterface|null The matching context or null
     */
    public function getContext(string $key): ?ContextInterface
    {
        foreach ($this->contexts as $context) {
            if ($context->getKey() === $key) {
                return $context;
            }
        }
        
        return null;
    }
    
    /**
     * Freeze the stack (make immutable)
     * 
     * Once frozen, the stack cannot be modified.
     * This ensures context remains constant throughout request processing.
     * 
     * @return void
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }
    
    /**
     * Check if stack is frozen
     * 
     * @return bool True if the stack is frozen, false otherwise
     */
    public function isFrozen(): bool
    {
        return $this->frozen;
    }
}
