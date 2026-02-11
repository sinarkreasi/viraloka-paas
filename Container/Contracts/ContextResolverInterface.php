<?php

namespace Viraloka\Core\Container\Contracts;

/**
 * Interface for determining the current operational context.
 */
interface ContextResolverInterface
{
    /**
     * Get the current context identifier.
     * Returns 'default' if no specific context is active.
     *
     * @return string The current context identifier
     */
    public function getCurrentContext(): string;
}
