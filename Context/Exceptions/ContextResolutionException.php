<?php

namespace Viraloka\Core\Context\Exceptions;

use Exception;
use Viraloka\Core\Context\ContextStack;

/**
 * Context Resolution Exception
 * 
 * Exception thrown when context resolution encounters errors.
 * Contains information about failed sources and the fallback stack used.
 * 
 * Validates: Requirements 8.1, 8.2
 */
class ContextResolutionException extends Exception
{
    /**
     * Array of failed source names or error messages
     * 
     * @var array<string>
     */
    private array $failedSources;
    
    /**
     * The fallback context stack used when resolution failed
     * 
     * @var ContextStack|null
     */
    private ?ContextStack $fallbackStack;
    
    /**
     * Create a new context resolution exception
     * 
     * @param string $message Exception message
     * @param array<string> $failedSources Array of failed source names
     * @param ContextStack|null $fallbackStack The fallback stack used
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = "",
        array $failedSources = [],
        ?ContextStack $fallbackStack = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->failedSources = $failedSources;
        $this->fallbackStack = $fallbackStack;
    }
    
    /**
     * Get the array of failed source names
     * 
     * @return array<string> Array of failed source names or error messages
     */
    public function getFailedSources(): array
    {
        return $this->failedSources;
    }
    
    /**
     * Get the fallback context stack
     * 
     * @return ContextStack|null The fallback stack or null if none was created
     */
    public function getFallbackStack(): ?ContextStack
    {
        return $this->fallbackStack;
    }
}
