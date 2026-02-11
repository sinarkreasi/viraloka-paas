<?php

namespace Viraloka\Core\Container\Contracts;

/**
 * Interface for determining the current workspace.
 */
interface WorkspaceResolverInterface
{
    /**
     * Get the current workspace identifier.
     * Returns null if no workspace is active.
     *
     * @return string|null The current workspace identifier or null
     */
    public function getCurrentWorkspace(): ?string;
}
