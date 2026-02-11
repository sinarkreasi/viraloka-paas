<?php

declare(strict_types=1);

namespace Viraloka\Core\Billing;

/**
 * Billing Entitlement Error
 * 
 * Standardized error response format for billing and entitlement operations.
 * Provides error code, message, and contextual information.
 */
class BillingEntitlementError
{
    /**
     * @param string $code Error code (e.g., "SUBSCRIPTION_EXISTS", "QUOTA_EXCEEDED")
     * @param string $message Human-readable error message
     * @param array $context Additional context information (workspace_id, key, values, etc.)
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $context = []
    ) {}
    
    /**
     * Convert error to array format
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'context' => $this->context,
        ];
    }
    
    /**
     * Convert error to JSON string
     * 
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }
    
    /**
     * Create error from exception
     * 
     * @param \Throwable $exception
     * @return self
     */
    public static function fromException(\Throwable $exception): self
    {
        $code = self::getErrorCodeFromException($exception);
        
        return new self(
            $code,
            $exception->getMessage(),
            [
                'exception_class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]
        );
    }
    
    /**
     * Get error code from exception class name
     * 
     * @param \Throwable $exception
     * @return string
     */
    private static function getErrorCodeFromException(\Throwable $exception): string
    {
        $className = get_class($exception);
        $shortName = substr($className, strrpos($className, '\\') + 1);
        
        // Convert SubscriptionExistsException to SUBSCRIPTION_EXISTS
        $code = preg_replace('/Exception$/', '', $shortName);
        $code = strtoupper(preg_replace('/(?<!^)[A-Z]/', '_$0', $code));
        
        return $code;
    }
}
