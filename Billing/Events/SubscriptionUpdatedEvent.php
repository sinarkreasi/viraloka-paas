<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Subscription Updated Event
 * 
 * Emitted when a subscription plan is changed.
 */
class SubscriptionUpdatedEvent
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $workspaceId,
        public readonly string $oldPlanId,
        public readonly string $newPlanId,
        public readonly DateTimeImmutable $updatedAt
    ) {}
}
