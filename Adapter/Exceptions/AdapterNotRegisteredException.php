<?php

namespace Viraloka\Core\Adapter\Exceptions;

/**
 * Exception thrown when attempting to access an adapter that has not been registered.
 */
class AdapterNotRegisteredException extends AdapterException
{
    public function __construct(string $adapterType)
    {
        parent::__construct("Adapter not registered: {$adapterType}");
    }
}
