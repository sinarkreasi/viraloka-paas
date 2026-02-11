<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Subscription Paused Event
 * 
 * Emitted when a subscription is paused.
 */
class SubscriptionPausedEvent
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $workspaceId,
        public readonly DateTimeImmutable $pausedAt
    ) {}
}
