<?php

namespace Viraloka\Core\Context;

/**
 * Abstract Context Source
 * 
 * Base class for all context source implementations.
 * Provides common functionality for storing and retrieving
 * context key, priority, and metadata.
 * 
 * Validates: Requirements 3.1, 3.2, 3.3
 */
abstract class ContextSource implements ContextInterface
{
    /**
     * The context key identifier
     * 
     * @var string
     */
    protected string $key;
    
    /**
     * The priority of this context source
     * 
     * @var int
     */
    protected int $priority;
    
    /**
     * Additional metadata about this context
     * 
     * @var array<string, mixed>
     */
    protected array $metadata;
    
    /**
     * Get the context key
     * 
     * @return string The context key identifier
     */
    public function getKey(): string
    {
        return $this->key;
    }
    
    /**
     * Get the priority of this context source
     * 
     * @return int The priority value
     */
    public function getPriority(): int
    {
        return $this->priority;
    }
    
    /**
     * Get additional metadata about this context
     * 
     * @return array<string, mixed> The metadata array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
