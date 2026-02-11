<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Subscription Created Event
 * 
 * Emitted when a new subscription is created.
 */
class SubscriptionCreatedEvent
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $workspaceId,
        public readonly string $planId,
        public readonly string $status,
        public readonly DateTimeImmutable $createdAt
    ) {}
}
