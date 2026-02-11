<?php

namespace Viraloka\Core\Container\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all container-related errors.
 */
class ContainerException extends Exception
{
    /**
     * Create a ContainerException that wraps another exception.
     *
     * @param string $serviceId The service identifier that failed to resolve
     * @param Throwable $previous The original exception
     * @return self
     */
    public static function forResolverFailure(string $serviceId, Throwable $previous): self
    {
        return new self(
            "Failed to resolve '{$serviceId}': {$previous->getMessage()}",
            0,
            $previous
        );
    }
}
