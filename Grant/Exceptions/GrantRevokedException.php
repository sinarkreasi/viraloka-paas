<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Exceptions;

use Exception;

/**
 * Grant Revoked Exception
 * 
 * Thrown when attempting to use a revoked grant.
 */
class GrantRevokedException extends Exception
{
    public function __construct(string $grantId)
    {
        parent::__construct("Grant has been revoked: {$grantId}");
    }
}
