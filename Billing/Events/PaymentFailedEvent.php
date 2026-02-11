<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Payment Failed Event
 * 
 * Emitted when a payment fails.
 */
class PaymentFailedEvent
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $workspaceId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $gateway,
        public readonly string $reason,
        public readonly DateTimeImmutable $failedAt
    ) {}
}
