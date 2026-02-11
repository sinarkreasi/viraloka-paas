<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Subscription Resumed Event
 * 
 * Emitted when a paused subscription is resumed.
 */
class SubscriptionResumedEvent
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $workspaceId,
        public readonly DateTimeImmutable $resumedAt
    ) {}
}
