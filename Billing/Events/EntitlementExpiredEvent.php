<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Entitlement Expired Event
 * 
 * Emitted when an entitlement expires.
 */
class EntitlementExpiredEvent
{
    public function __construct(
        public readonly string $entitlementId,
        public readonly string $workspaceId,
        public readonly string $key,
        public readonly DateTimeImmutable $expiredAt
    ) {}
}
