<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Entitlement Revoked Event
 * 
 * Emitted when an entitlement is revoked from a workspace.
 */
class EntitlementRevokedEvent
{
    public function __construct(
        public readonly string $entitlementId,
        public readonly string $workspaceId,
        public readonly string $key,
        public readonly DateTimeImmutable $revokedAt
    ) {}
}
