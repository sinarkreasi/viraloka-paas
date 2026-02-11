<?php

namespace Viraloka\Core\Container;

use Viraloka\Core\Container\Contracts\WorkspaceResolverInterface;

/**
 * Default workspace resolver that always returns null.
 * Used when no workspace resolution is needed.
 */
class DefaultWorkspaceResolver implements WorkspaceResolverInterface
{
    /**
     * Get the current workspace identifier.
     * Always returns null.
     *
     * @return string|null Always returns null
     */
    public function getCurrentWorkspace(): ?string
    {
        return null;
    }
}
