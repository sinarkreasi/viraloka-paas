<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant;

use DateTimeImmutable;

/**
 * Grant Entity
 * 
 * Represents a temporary permission attachment binding identity, workspace, role/permission subset,
 * and constraints (time/usage/scope).
 */
class Grant
{
    public readonly string $grantId;
    public readonly string $identityId;
    public readonly string $workspaceId;
    public readonly string $role;
    public string $status;
    public readonly DateTimeImmutable $createdAt;
    
    // Constraints
    public ?DateTimeImmutable $expiresAt;
    public ?int $maxUsage;
    public int $currentUsage = 0;
    public ?array $allowedActions;
    
    // Metadata
    public array $metadata;
    
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXHAUSTED = 'exhausted';
    
    public function __construct(
        string $grantId,
        string $identityId,
        string $workspaceId,
        string $role,
        ?DateTimeImmutable $expiresAt = null,
        ?int $maxUsage = null,
        ?array $allowedActions = null,
        array $metadata = [],
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->grantId = $grantId;
        $this->identityId = $identityId;
        $this->workspaceId = $workspaceId;
        $this->role = $role;
        $this->expiresAt = $expiresAt;
        $this->maxUsage = $maxUsage;
        $this->allowedActions = $allowedActions;
        $this->metadata = $metadata;
        $this->status = self::STATUS_ACTIVE;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }
    
    /**
     * Check if grant has at least one constraint
     * 
     * @return bool
     */
    public function hasConstraint(): bool
    {
        return $this->expiresAt !== null 
            || $this->maxUsage !== null 
            || $this->allowedActions !== null;
    }
    
    /**
     * Check if grant is currently valid
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        
        if ($this->expiresAt !== null && $this->expiresAt < new DateTimeImmutable()) {
            return false;
        }
        
        if ($this->maxUsage !== null && $this->currentUsage >= $this->maxUsage) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Consume one usage from the grant
     * 
     * @return bool True if consumed successfully, false if invalid
     */
    public function consume(): bool
    {
        if (!$this->isValid()) {
            return false;
        }
        
        if ($this->maxUsage !== null) {
            $this->currentUsage++;
            if ($this->currentUsage >= $this->maxUsage) {
                $this->status = self::STATUS_EXHAUSTED;
            }
        }
        
        return true;
    }
    
    /**
     * Revoke the grant
     * 
     * @return void
     */
    public function revoke(): void
    {
        $this->status = self::STATUS_REVOKED;
    }
    
    /**
     * Check if a specific action is allowed by this grant
     * 
     * @param string $action Action to check
     * @return bool
     */
    public function isActionAllowed(string $action): bool
    {
        if ($this->allowedActions === null) {
            return true; // No scope restriction
        }
        
        return in_array($action, $this->allowedActions, true);
    }
}
