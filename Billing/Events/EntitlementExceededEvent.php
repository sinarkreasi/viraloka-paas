<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Entitlement Exceeded Event
 * 
 * Emitted when an entitlement quota is exceeded.
 */
class EntitlementExceededEvent
{
    public function __construct(
        public readonly string $workspaceId,
        public readonly string $key,
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly DateTimeImmutable $exceededAt
    ) {}
}
