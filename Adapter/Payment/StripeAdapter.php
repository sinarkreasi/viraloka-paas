<?php

namespace Viraloka\Adapter\Payment;

use Viraloka\Core\Adapter\Contracts\PaymentAdapterInterface;
use Viraloka\Core\Billing\PaymentResult;
use Viraloka\Core\Billing\Exceptions\PaymentException;
use Viraloka\Core\Billing\Events\PaymentSucceededEvent;
use Viraloka\Core\Billing\Events\PaymentFailedEvent;
use Viraloka\Core\Events\EventDispatcher;
use DateTimeImmutable;

/**
 * Stripe Payment Adapter
 * 
 * Example implementation of PaymentAdapterInterface for Stripe.
 * Maps Stripe events to core payment events.
 */
class StripeAdapter implements PaymentAdapterInterface
{
    private const GATEWAY_NAME = 'stripe';
    
    public function __construct(
        private readonly EventDispatcher $eventDispatcher,
        private readonly string $apiKey,
        private readonly string $webhookSecret
    ) {}
    
    /**
     * Charge workspace for subscription.
     *
     * @param string $workspaceId UUID
     * @param int $amount Amount in smallest currency unit (cents)
     * @param string $currency Currency code (USD, IDR, etc.)
     * @param array $metadata Optional metadata
     * @return PaymentResult
     * @throws PaymentException
     */
    public function charge(string $workspaceId, int $amount, string $currency, array $metadata = []): PaymentResult
    {
        try {
            // Example: In a real implementation, this would call Stripe API
            // For now, this is a mock implementation
            
            // Simulate Stripe API call
            $transactionId = 'ch_' . bin2hex(random_bytes(12));
            
            // Simulate payment processing
            $status = PaymentResult::STATUS_SUCCEEDED;
            
            $result = new PaymentResult(
                transactionId: $transactionId,
                status: $status,
                amount: $amount,
                currency: $currency,
                metadata: array_merge($metadata, [
                    'workspace_id' => $workspaceId,
                    'gateway' => self::GATEWAY_NAME
                ]),
                processedAt: new DateTimeImmutable()
            );
            
            // Emit event based on result
            if ($result->isSucceeded()) {
                $this->eventDispatcher->dispatch(
                    'payment.succeeded',
                    new PaymentSucceededEvent(
                        transactionId: $result->transactionId,
                        workspaceId: $workspaceId,
                        amount: $result->amount,
                        currency: $result->currency,
                        gateway: self::GATEWAY_NAME,
                        succeededAt: $result->processedAt
                    )
                );
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            throw new PaymentException(
                message: "Stripe charge failed: {$e->getMessage()}",
                context: [
                    'workspace_id' => $workspaceId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'gateway' => self::GATEWAY_NAME
                ]
            );
        }
    }
    
    /**
     * Handle webhook from payment gateway.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    public function handleWebhook(array $payload): void
    {
        // Extract event type from Stripe webhook
        $eventType = $payload['type'] ?? null;
        
        if (!$eventType) {
            return;
        }
        
        // Map Stripe events to core events
        switch ($eventType) {
            case 'charge.succeeded':
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($payload);
                break;
                
            case 'charge.failed':
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($payload);
                break;
                
            default:
                // Ignore other event types
                break;
        }
    }
    
    /**
     * Handle successful payment webhook.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function handlePaymentSucceeded(array $payload): void
    {
        $data = $payload['data']['object'] ?? [];
        
        $transactionId = $data['id'] ?? 'unknown';
        $amount = $data['amount'] ?? 0;
        $currency = strtoupper($data['currency'] ?? 'USD');
        $workspaceId = $data['metadata']['workspace_id'] ?? null;
        
        if (!$workspaceId) {
            return;
        }
        
        $this->eventDispatcher->dispatch(
            'payment.succeeded',
            new PaymentSucceededEvent(
                transactionId: $transactionId,
                workspaceId: $workspaceId,
                amount: $amount,
                currency: $currency,
                gateway: self::GATEWAY_NAME,
                succeededAt: new DateTimeImmutable()
            )
        );
    }
    
    /**
     * Handle failed payment webhook.
     *
     * @param array $payload Webhook payload
     * @return void
     */
    private function handlePaymentFailed(array $payload): void
    {
        $data = $payload['data']['object'] ?? [];
        
        $transactionId = $data['id'] ?? 'unknown';
        $amount = $data['amount'] ?? 0;
        $currency = strtoupper($data['currency'] ?? 'USD');
        $workspaceId = $data['metadata']['workspace_id'] ?? null;
        $reason = $data['failure_message'] ?? 'Unknown error';
        
        if (!$workspaceId) {
            return;
        }
        
        $this->eventDispatcher->dispatch(
            'payment.failed',
            new PaymentFailedEvent(
                transactionId: $transactionId,
                workspaceId: $workspaceId,
                amount: $amount,
                currency: $currency,
                gateway: self::GATEWAY_NAME,
                reason: $reason,
                failedAt: new DateTimeImmutable()
            )
        );
    }
    
    /**
     * Get payment gateway name.
     *
     * @return string
     */
    public function getName(): string
    {
        return self::GATEWAY_NAME;
    }
    
    /**
     * Validate webhook signature.
     *
     * @param array $payload Webhook payload
     * @param string $signature Webhook signature
     * @return bool
     */
    public function validateWebhookSignature(array $payload, string $signature): bool
    {
        // Example: In a real implementation, this would validate Stripe signature
        // using the webhook secret and HMAC verification
        
        // Mock implementation for demonstration
        if (empty($signature)) {
            return false;
        }
        
        // In real implementation:
        // $expectedSignature = hash_hmac('sha256', json_encode($payload), $this->webhookSecret);
        // return hash_equals($expectedSignature, $signature);
        
        return true;
    }
}
