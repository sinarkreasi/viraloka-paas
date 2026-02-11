<?php

namespace Viraloka\Core\Context;

/**
 * System Default Context
 * 
 * Represents the fallback system default context.
 * Has the lowest priority (10) in the context resolution hierarchy.
 * 
 * Validates: Requirements 10.4
 */
class SystemDefaultContext extends ContextSource
{
    /**
     * Create a new system default context
     * 
     * @param string $key The context key identifier (defaults to 'default')
     * @param array<string, mixed> $metadata Optional metadata about this context
     */
    public function __construct(string $key = 'default', array $metadata = [])
    {
        $this->key = $key;
        $this->priority = 10; // Lowest priority
        $this->metadata = array_merge([
            'source' => 'system'
        ], $metadata);
    }
}
