<?php

namespace Viraloka\Core\Billing\Events;

use DateTimeImmutable;

/**
 * Payment Succeeded Event
 * 
 * Emitted when a payment succeeds.
 */
class PaymentSucceededEvent
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $workspaceId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $gateway,
        public readonly DateTimeImmutable $succeededAt
    ) {}
}
