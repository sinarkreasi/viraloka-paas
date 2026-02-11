<?php

namespace Viraloka\Core\Container\Exceptions;

/**
 * Exception thrown when a service is not found in the container.
 */
class ServiceNotFoundException extends ContainerException
{
    /**
     * Create a new ServiceNotFoundException.
     *
     * @param string $id The service identifier that was not found
     */
    public static function forService(string $id): self
    {
        return new self("Service '{$id}' not found in container");
    }
}
