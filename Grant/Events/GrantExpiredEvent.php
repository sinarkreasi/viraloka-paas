<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Events;

use DateTimeImmutable;

/**
 * Grant Expired Event
 * 
 * Emitted when a grant expires due to time constraint.
 * Requirements: 8.3
 */
class GrantExpiredEvent
{
    public function __construct(
        public readonly string $grantId,
        public readonly DateTimeImmutable $expiredAt
    ) {}
}
