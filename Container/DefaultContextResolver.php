<?php

namespace Viraloka\Core\Container;

use Viraloka\Core\Container\Contracts\ContextResolverInterface;

/**
 * Default context resolver that always returns 'default'.
 * Used when no specific context resolution is needed.
 */
class DefaultContextResolver implements ContextResolverInterface
{
    /**
     * Get the current context identifier.
     * Always returns 'default'.
     *
     * @return string The default context identifier
     */
    public function getCurrentContext(): string
    {
        return 'default';
    }
}
