<?php

namespace Viraloka\Core\Billing;

use DateTimeImmutable;

/**
 * Entitlement Entity
 * 
 * Represents feature access rights owned by a workspace.
 */
class Entitlement
{
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_METERED = 'metered';
    
    public function __construct(
        public readonly string $entitlementId,
        public readonly string $workspaceId,
        public readonly string $key,
        public string $type,
        public mixed $value,
        public ?int $currentUsage = null,
        public ?DateTimeImmutable $expiresAt = null,
        public readonly ?DateTimeImmutable $createdAt = null
    ) {
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }
    
    public function isBoolean(): bool
    {
        return $this->type === self::TYPE_BOOLEAN;
    }
    
    public function isNumeric(): bool
    {
        return $this->type === self::TYPE_NUMERIC;
    }
    
    public function isMetered(): bool
    {
        return $this->type === self::TYPE_METERED;
    }
    
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }
        return $this->expiresAt < new DateTimeImmutable();
    }
    
    public function hasQuotaAvailable(int $amount = 1): bool
    {
        if (!$this->isNumeric()) {
            return false;
        }
        
        if ($this->currentUsage === null) {
            $this->currentUsage = 0;
        }
        
        return ($this->currentUsage + $amount) <= $this->value;
    }
    
    public function consume(int $amount = 1): bool
    {
        if (!$this->hasQuotaAvailable($amount)) {
            return false;
        }
        
        $this->currentUsage += $amount;
        return true;
    }
    
    public function check(): bool
    {
        if ($this->isExpired()) {
            return false;
        }
        
        if ($this->isBoolean()) {
            return (bool) $this->value;
        }
        
        if ($this->isNumeric()) {
            return $this->hasQuotaAvailable(0);
        }
        
        if ($this->isMetered()) {
            return true;
        }
        
        return false;
    }
}
