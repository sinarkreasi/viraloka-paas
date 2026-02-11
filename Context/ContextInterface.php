<?php

namespace Viraloka\Core\Context;

/**
 * Context Interface
 * 
 * The core contract that all context objects must implement.
 * Provides semantic information about the use-case context.
 * 
 * Validates: Requirements 3.1, 3.2, 3.3
 */
interface ContextInterface
{
    /**
     * Get the context key (e.g., 'marketplace', 'ai-service', 'link-in-bio')
     * 
     * The context key is a semantic identifier representing use-case semantics,
     * NOT a business type, module, or theme.
     * 
     * @return string The context key identifier
     */
    public function getKey(): string;
    
    /**
     * Get the priority of this context source
     * 
     * Higher values indicate higher priority.
     * Priority determines ordering in the context stack.
     * 
     * Standard priorities:
     * - Workspace: 100 (highest)
     * - Theme: 50 (medium)
     * - System Default: 10 (lowest)
     * 
     * @return int The priority value
     */
    public function getPriority(): int;
    
    /**
     * Get additional metadata about this context
     * 
     * Metadata can include information such as:
     * - source: The source of the context (workspace, theme, system)
     * - workspace_id: The workspace identifier
     * - configured_at: Timestamp of configuration
     * - Any other context-specific data
     * 
     * @return array<string, mixed> The metadata array
     */
    public function getMetadata(): array;
}
