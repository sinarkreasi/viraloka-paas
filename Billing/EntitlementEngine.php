<?php

declare(strict_types=1);

namespace Viraloka\Core\Billing;

use DateTimeImmutable;
use Viraloka\Core\Billing\Contracts\EntitlementEngineInterface;
use Viraloka\Core\Billing\Repositories\EntitlementRepository;
use Viraloka\Core\Billing\Events\EntitlementGrantedEvent;
use Viraloka\Core\Billing\Events\EntitlementRevokedEvent;
use Viraloka\Core\Billing\Events\EntitlementExceededEvent;
use Viraloka\Core\Billing\Events\EntitlementExpiredEvent;
use Viraloka\Core\Billing\Exceptions\InvalidKeyFormatException;
use Viraloka\Core\Billing\Exceptions\InvalidEntitlementTypeException;
use Viraloka\Core\Events\EventDispatcher;
use Viraloka\Core\Workspace\WorkspaceResolver;

/**
 * Entitlement Engine
 * 
 * Manages feature access rights and quota enforcement.
 * Integrates with EventDispatcher for lifecycle events and WorkspaceResolver for context.
 * 
 * Requirements: 4.1-4.10, 10.1-10.8, 12.1, 12.4
 */
class EntitlementEngine implements EntitlementEngineInterface
{
    private EntitlementRepository $entitlementRepository;
    private EventDispatcher $eventDispatcher;
    private ?WorkspaceResolver $workspaceResolver;
    
    public function __construct(
        EntitlementRepository $entitlementRepository,
        EventDispatcher $eventDispatcher,
        ?WorkspaceResolver $workspaceResolver = null
    ) {
        $this->entitlementRepository = $entitlementRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->workspaceResolver = $workspaceResolver;
    }
    
    /**
     * Check if workspace has access to feature
     * 
     * Requirements: 4.1, 4.6
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key (e.g., "shortlink.max_links")
     * @return bool
     */
    public function check(string $workspaceId, string $key): bool
    {
        // Validate inputs (Requirement 3.8)
        $this->validateWorkspaceId($workspaceId);
        $this->validateKey($key);
        
        // Find entitlement by workspace and key
        $entitlement = $this->entitlementRepository->findByWorkspaceAndKey($workspaceId, $key);
        
        // If entitlement does not exist, return false (Requirement 4.6)
        if ($entitlement === null) {
            return false;
        }
        
        // Use entitlement's check method which handles expiration and type-specific logic
        return $entitlement->check();
    }
    
    /**
     * Consume quota from entitlement
     * 
     * Requirements: 4.2, 4.7, 4.8
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @param int $amount Amount to consume
     * @return bool True if consumed, false if quota exceeded
     * @throws \RuntimeException If attempting to consume on boolean entitlement
     */
    public function consume(string $workspaceId, string $key, int $amount = 1): bool
    {
        // Validate inputs (Requirement 3.8)
        $this->validateWorkspaceId($workspaceId);
        $this->validateKey($key);
        
        if ($amount < 0) {
            throw new \InvalidArgumentException("Amount must be non-negative: {$amount}");
        }
        
        // Find entitlement by workspace and key
        $entitlement = $this->entitlementRepository->findByWorkspaceAndKey($workspaceId, $key);
        
        // If entitlement does not exist, return false
        if ($entitlement === null) {
            return false;
        }
        
        // Check if entitlement is boolean (Requirement 4.7)
        if ($entitlement->isBoolean()) {
            throw new InvalidEntitlementTypeException($entitlement->type, 'consume');
        }
        
        // Check if entitlement is expired
        if ($entitlement->isExpired()) {
            return false;
        }
        
        // Try to consume quota
        $consumed = $entitlement->consume($amount);
        
        // If quota exceeded, emit event (Requirement 4.8)
        if (!$consumed) {
            $this->eventDispatcher->dispatch(
                'entitlement.exceeded',
                new EntitlementExceededEvent(
                    $workspaceId,
                    $key,
                    $entitlement->currentUsage ?? 0,
                    $entitlement->value,
                    new DateTimeImmutable()
                )
            );
            return false;
        }
        
        // Update entitlement in repository
        $this->entitlementRepository->update($entitlement);
        
        return true;
    }
    
    /**
     * Grant entitlement to workspace
     * 
     * Requirements: 4.3, 4.9, 10.1
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @param mixed $value Boolean, numeric, or metered value
     * @return Entitlement
     */
    public function grant(string $workspaceId, string $key, mixed $value): Entitlement
    {
        // Validate inputs (Requirement 3.8)
        $this->validateWorkspaceId($workspaceId);
        $this->validateKey($key);
        
        // Check if entitlement already exists (Requirement 4.9 - idempotent)
        $existingEntitlement = $this->entitlementRepository->findByWorkspaceAndKey($workspaceId, $key);
        
        // Determine entitlement type based on value
        $type = $this->determineType($value);
        
        if ($existingEntitlement !== null) {
            // Update existing entitlement (idempotent)
            $existingEntitlement->type = $type;
            $existingEntitlement->value = $value;
            
            // Reset current usage for numeric types when value changes
            if ($type === Entitlement::TYPE_NUMERIC) {
                $existingEntitlement->currentUsage = 0;
            }
            
            $this->entitlementRepository->update($existingEntitlement);
            
            // Emit entitlement.granted event (Requirement 10.1)
            $this->eventDispatcher->dispatch(
                'entitlement.granted',
                new EntitlementGrantedEvent(
                    $existingEntitlement->entitlementId,
                    $workspaceId,
                    $key,
                    $value,
                    new DateTimeImmutable()
                )
            );
            
            return $existingEntitlement;
        }
        
        // Create new entitlement
        $currentUsage = $type === Entitlement::TYPE_NUMERIC ? 0 : null;
        
        $entitlement = $this->entitlementRepository->create(
            $workspaceId,
            $key,
            $type,
            $value,
            $currentUsage
        );
        
        // Emit entitlement.granted event (Requirement 10.1)
        $this->eventDispatcher->dispatch(
            'entitlement.granted',
            new EntitlementGrantedEvent(
                $entitlement->entitlementId,
                $workspaceId,
                $key,
                $value,
                new DateTimeImmutable()
            )
        );
        
        return $entitlement;
    }
    
    /**
     * Revoke entitlement from workspace
     * 
     * Requirements: 4.4, 4.10, 10.2
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @return bool
     */
    public function revoke(string $workspaceId, string $key): bool
    {
        // Validate inputs (Requirement 3.8)
        $this->validateWorkspaceId($workspaceId);
        $this->validateKey($key);
        
        // Find entitlement by workspace and key
        $entitlement = $this->entitlementRepository->findByWorkspaceAndKey($workspaceId, $key);
        
        // If entitlement does not exist, succeed without error (Requirement 4.10 - idempotent)
        if ($entitlement === null) {
            return true;
        }
        
        // Delete entitlement
        $deleted = $this->entitlementRepository->delete($entitlement->entitlementId);
        
        if ($deleted) {
            // Emit entitlement.revoked event (Requirement 10.2)
            $this->eventDispatcher->dispatch(
                'entitlement.revoked',
                new EntitlementRevokedEvent(
                    $entitlement->entitlementId,
                    $workspaceId,
                    $key,
                    new DateTimeImmutable()
                )
            );
        }
        
        return $deleted;
    }
    
    /**
     * Get current entitlement value
     * 
     * Requirements: 4.5
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @return mixed|null
     */
    public function current(string $workspaceId, string $key): mixed
    {
        // Validate inputs (Requirement 3.8)
        $this->validateWorkspaceId($workspaceId);
        $this->validateKey($key);
        // Find entitlement by workspace and key
        $entitlement = $this->entitlementRepository->findByWorkspaceAndKey($workspaceId, $key);
        
        // If entitlement does not exist, return null (Requirement 4.5)
        if ($entitlement === null) {
            return null;
        }
        
        // For numeric entitlements, return remaining quota (limit - current_usage)
        if ($entitlement->isNumeric()) {
            $currentUsage = $entitlement->currentUsage ?? 0;
            return $entitlement->value - $currentUsage;
        }
        
        // For other types, return the value
        return $entitlement->value;
    }
    
    /**
     * Get all entitlements for workspace
     * 
     * @param string $workspaceId UUID
     * @return Entitlement[]
     */
    public function getAllForWorkspace(string $workspaceId): array
    {
        // Validate input
        $this->validateWorkspaceId($workspaceId);
        
        return $this->entitlementRepository->findAllByWorkspace($workspaceId);
    }
    
    /**
     * Grant all entitlements from a plan
     * 
     * Requirements: 7.6
     * 
     * @param string $workspaceId UUID
     * @param Plan $plan Plan with entitlements
     * @return void
     */
    public function grantPlanEntitlements(string $workspaceId, Plan $plan): void
    {
        // Grant each entitlement from the plan
        foreach ($plan->entitlements as $key => $value) {
            $this->grant($workspaceId, $key, $value);
        }
    }
    
    /**
     * Revoke all entitlements from a plan
     * 
     * Requirements: 7.7
     * 
     * @param string $workspaceId UUID
     * @param Plan $plan Plan with entitlements
     * @return void
     */
    public function revokePlanEntitlements(string $workspaceId, Plan $plan): void
    {
        // Revoke each entitlement from the plan
        foreach ($plan->entitlements as $key => $value) {
            $this->revoke($workspaceId, $key);
        }
    }
    
    /**
     * Determine entitlement type based on value
     * 
     * @param mixed $value Entitlement value
     * @return string Entitlement type constant
     */
    private function determineType(mixed $value): string
    {
        if (is_bool($value)) {
            return Entitlement::TYPE_BOOLEAN;
        }
        
        if (is_int($value)) {
            return Entitlement::TYPE_NUMERIC;
        }
        
        if (is_array($value)) {
            return Entitlement::TYPE_METERED;
        }
        
        // Default to boolean for other types
        return Entitlement::TYPE_BOOLEAN;
    }
    
    /**
     * Get current workspace ID from WorkspaceResolver
     * 
     * Requirements: 12.1, 12.4
     * 
     * @return string|null Workspace ID or null if resolver not available
     */
    private function getCurrentWorkspaceId(): ?string
    {
        if ($this->workspaceResolver === null) {
            return null;
        }
        
        $workspace = $this->workspaceResolver->resolve();
        return $workspace->workspaceId;
    }
    
    /**
     * Check entitlement for current workspace (using WorkspaceResolver)
     * 
     * Requirements: 12.1, 12.4
     * 
     * @param string $key Entitlement key
     * @return bool
     * @throws \RuntimeException If WorkspaceResolver not available
     */
    public function checkForCurrentWorkspace(string $key): bool
    {
        $workspaceId = $this->getCurrentWorkspaceId();
        
        if ($workspaceId === null) {
            throw new \RuntimeException('WorkspaceResolver not available');
        }
        
        return $this->check($workspaceId, $key);
    }
    
    /**
     * Consume quota for current workspace (using WorkspaceResolver)
     * 
     * Requirements: 12.1, 12.4
     * 
     * @param string $key Entitlement key
     * @param int $amount Amount to consume
     * @return bool
     * @throws \RuntimeException If WorkspaceResolver not available
     */
    public function consumeForCurrentWorkspace(string $key, int $amount = 1): bool
    {
        $workspaceId = $this->getCurrentWorkspaceId();
        
        if ($workspaceId === null) {
            throw new \RuntimeException('WorkspaceResolver not available');
        }
        
        return $this->consume($workspaceId, $key, $amount);
    }
    
    /**
     * Process entitlement expirations (called by scheduler)
     * 
     * Requirements: 3.9, 10.4
     * 
     * @return int Number of entitlements expired
     */
    public function processExpirations(): int
    {
        // Find all expired entitlements
        $expiredEntitlements = $this->entitlementRepository->findExpired();
        
        $count = 0;
        foreach ($expiredEntitlements as $entitlement) {
            // Delete expired entitlement
            $deleted = $this->entitlementRepository->delete($entitlement->entitlementId);
            
            if ($deleted) {
                // Emit entitlement.expired event (Requirement 10.4)
                $this->eventDispatcher->dispatch(
                    'entitlement.expired',
                    new EntitlementExpiredEvent(
                        $entitlement->entitlementId,
                        $entitlement->workspaceId,
                        $entitlement->key,
                        new DateTimeImmutable()
                    )
                );
                
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Validate workspace ID format
     * 
     * Note: This validates the format only. For production use, consider adding
     * workspace existence validation by injecting WorkspaceRepositoryInterface
     * and checking if the workspace exists in the database (Requirement 13.6).
     * 
     * @param string $workspaceId
     * @return void
     * @throws \InvalidArgumentException
     */
    private function validateWorkspaceId(string $workspaceId): void
    {
        if (empty($workspaceId)) {
            throw new \InvalidArgumentException("Workspace ID cannot be empty");
        }
        
        // UUID format validation (optional but recommended)
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $workspaceId)) {
            throw new \InvalidArgumentException("Workspace ID must be a valid UUID: {$workspaceId}");
        }
    }
    
    /**
     * Validate entitlement key format (dot notation)
     * 
     * @param string $key
     * @return void
     * @throws InvalidKeyFormatException
     */
    private function validateKey(string $key): void
    {
        if (empty($key)) {
            throw new InvalidKeyFormatException($key);
        }
        
        // Key must follow dot notation (at least one dot)
        if (strpos($key, '.') === false) {
            throw new InvalidKeyFormatException($key);
        }
        
        // Key should be alphanumeric with dots and underscores
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $key)) {
            throw new InvalidKeyFormatException($key);
        }
    }
}
