<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Subscription Cancelled Event
 * 
 * Emitted when a subscription is cancelled.
 */
class SubscriptionCancelledEvent
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $workspaceId,
        public readonly DateTimeImmutable $cancelledAt
    ) {}
}
