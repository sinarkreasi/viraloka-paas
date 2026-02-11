<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Events;

use DateTimeImmutable;

/**
 * Grant Consumed Event
 * 
 * Emitted when a grant is consumed (usage decremented).
 * Requirements: 8.2
 */
class GrantConsumedEvent
{
    public function __construct(
        public readonly string $grantId,
        public readonly int $currentUsage,
        public readonly ?int $maxUsage,
        public readonly DateTimeImmutable $consumedAt
    ) {}
}
