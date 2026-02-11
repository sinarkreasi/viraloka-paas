<?php

namespace Viraloka\Core\Context;

/**
 * Workspace Context
 * 
 * Represents context defined by a workspace.
 * Has the highest priority (100) in the context resolution hierarchy.
 * 
 * Validates: Requirements 10.2
 */
class WorkspaceContext extends ContextSource
{
    /**
     * Create a new workspace context
     * 
     * @param string $key The context key identifier
     * @param array<string, mixed> $metadata Optional metadata about this context
     */
    public function __construct(string $key, array $metadata = [])
    {
        $this->key = $key;
        $this->priority = 100; // Highest priority
        $this->metadata = array_merge([
            'source' => 'workspace'
        ], $metadata);
    }
}
