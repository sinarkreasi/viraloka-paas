<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Entitlement Granted Event
 * 
 * Emitted when an entitlement is granted to a workspace.
 */
class EntitlementGrantedEvent
{
    public function __construct(
        public readonly string $entitlementId,
        public readonly string $workspaceId,
        public readonly string $key,
        public readonly mixed $value,
        public readonly DateTimeImmutable $grantedAt
    ) {}
}
