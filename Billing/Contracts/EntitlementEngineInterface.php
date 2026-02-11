<?php

declare(strict_types=1);

namespace Viraloka\Core\Billing\Contracts;

use Viraloka\Core\Billing\Entitlement;
use Viraloka\Core\Billing\Plan;

/**
 * Entitlement Engine Interface
 * 
 * Defines the contract for managing feature access rights and quota enforcement.
 * The Entitlement Engine is the single source of truth for what features a workspace can access.
 * 
 * Requirements: 4.1-4.10, 10.1-10.8
 */
interface EntitlementEngineInterface
{
    /**
     * Check if workspace has access to feature
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key (e.g., "shortlink.max_links")
     * @return bool
     */
    public function check(string $workspaceId, string $key): bool;
    
    /**
     * Consume quota from entitlement
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @param int $amount Amount to consume
     * @return bool True if consumed, false if quota exceeded
     */
    public function consume(string $workspaceId, string $key, int $amount = 1): bool;
    
    /**
     * Grant entitlement to workspace
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @param mixed $value Boolean, numeric, or metered value
     * @return Entitlement
     */
    public function grant(string $workspaceId, string $key, mixed $value): Entitlement;
    
    /**
     * Revoke entitlement from workspace
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @return bool
     */
    public function revoke(string $workspaceId, string $key): bool;
    
    /**
     * Get current entitlement value
     * 
     * @param string $workspaceId UUID
     * @param string $key Entitlement key
     * @return mixed|null
     */
    public function current(string $workspaceId, string $key): mixed;
    
    /**
     * Get all entitlements for workspace
     * 
     * @param string $workspaceId UUID
     * @return Entitlement[]
     */
    public function getAllForWorkspace(string $workspaceId): array;
    
    /**
     * Grant all entitlements from a plan
     * 
     * @param string $workspaceId UUID
     * @param Plan $plan Plan with entitlements
     * @return void
     */
    public function grantPlanEntitlements(string $workspaceId, Plan $plan): void;
    
    /**
     * Revoke all entitlements from a plan
     * 
     * @param string $workspaceId UUID
     * @param Plan $plan Plan with entitlements
     * @return void
     */
    public function revokePlanEntitlements(string $workspaceId, Plan $plan): void;
}
