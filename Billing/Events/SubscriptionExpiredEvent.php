<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Subscription Expired Event
 * 
 * Emitted when a subscription expires.
 */
class SubscriptionExpiredEvent
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $workspaceId,
        public readonly DateTimeImmutable $expiredAt
    ) {}
}
