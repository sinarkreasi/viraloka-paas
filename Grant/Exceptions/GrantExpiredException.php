<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Exceptions;

use Exception;

/**
 * Grant Expired Exception
 * 
 * Thrown when attempting to use an expired grant.
 * Requirements: 7.6
 */
class GrantExpiredException extends Exception
{
    public function __construct(string $grantId)
    {
        parent::__construct("Grant has expired: {$grantId}");
    }
}
