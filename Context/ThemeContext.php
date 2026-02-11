<?php

namespace Viraloka\Core\Context;

/**
 * Theme Context
 * 
 * Represents context provided by a theme.
 * Has medium priority (50) in the context resolution hierarchy.
 * 
 * Validates: Requirements 10.3
 */
class ThemeContext extends ContextSource
{
    /**
     * Create a new theme context
     * 
     * @param string $key The context key identifier
     * @param array<string, mixed> $metadata Optional metadata about this context
     */
    public function __construct(string $key, array $metadata = [])
    {
        $this->key = $key;
        $this->priority = 50; // Medium priority
        $this->metadata = array_merge([
            'source' => 'theme'
        ], $metadata);
    }
}
