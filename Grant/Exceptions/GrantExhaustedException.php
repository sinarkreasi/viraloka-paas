<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Exceptions;

use Exception;

/**
 * Grant Exhausted Exception
 * 
 * Thrown when attempting to consume an exhausted grant.
 * Requirements: 7.7
 */
class GrantExhaustedException extends Exception
{
    public function __construct(string $grantId)
    {
        parent::__construct("Grant has been exhausted: {$grantId}");
    }
}
