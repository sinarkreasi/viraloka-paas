<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Events;

use DateTimeImmutable;

/**
 * Grant Exhausted Event
 * 
 * Emitted when a grant is exhausted (usage limit reached).
 * Requirements: 8.5
 */
class GrantExhaustedEvent
{
    public function __construct(
        public readonly string $grantId,
        public readonly int $totalUsage,
        public readonly DateTimeImmutable $exhaustedAt
    ) {}
}
