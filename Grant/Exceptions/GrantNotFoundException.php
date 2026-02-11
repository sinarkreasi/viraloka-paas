<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Exceptions;

use Exception;

/**
 * Grant Not Found Exception
 * 
 * Thrown when a grant cannot be found by ID.
 */
class GrantNotFoundException extends Exception
{
    public function __construct(string $grantId)
    {
        parent::__construct("Grant not found: {$grantId}");
    }
}
