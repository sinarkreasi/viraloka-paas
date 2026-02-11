<?php

namespace Viraloka\Core\Adapter\Contracts;

use Viraloka\Core\Billing\PaymentResult;

/**
 * Payment adapter for payment gateway integration.
 * 
 * Provides a gateway-agnostic interface for payment processing.
 * Adapters handle gateway-specific logic and map payment events to core events.
 */
interface PaymentAdapterInterface
{
    /**
     * Charge workspace for subscription.
     *
     * @param string $workspaceId UUID
     * @param int $amount Amount in smallest currency unit (cents)
     * @param string $currency Currency code (USD, IDR, etc.)
     * @param array $metadata Optional metadata
     * @return PaymentResult
     * @throws \Viraloka\Core\Billing\Exceptions\PaymentException
     */
    public function charge(string $workspaceId, int $amount, string $currency, array $metadata = []): PaymentResult;
    
    /**
     * Handle webhook from payment gateway.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    public function handleWebhook(array $payload): void;
    
    /**
     * Get payment gateway name.
     *
     * @return string
     */
    public function getName(): string;
    
    /**
     * Validate webhook signature.
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function validateWebhookSignature(array $payload, string $signature): bool;
}
