<?php

namespace Viraloka\Core\Container\Exceptions;

/**
 * Exception thrown when a circular dependency is detected during service resolution.
 */
class CircularDependencyException extends ContainerException
{
    /**
     * Create a new CircularDependencyException with the dependency chain.
     *
     * @param array<string> $chain The dependency chain that forms the circular reference
     */
    public static function forChain(array $chain): self
    {
        $chainStr = implode(' -> ', $chain);
        return new self("Circular dependency detected: {$chainStr}");
    }
}
