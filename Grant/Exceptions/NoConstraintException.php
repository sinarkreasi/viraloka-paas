<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Exceptions;

use Exception;

/**
 * No Constraint Exception
 * 
 * Thrown when attempting to issue a grant without any constraints.
 * Requirements: 7.5
 */
class NoConstraintException extends Exception
{
    public function __construct()
    {
        parent::__construct('Grant must have at least one constraint (expires_at, max_usage, or allowed_actions)');
    }
}
