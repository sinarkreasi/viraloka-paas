<?php

declare(strict_types=1);

namespace Viraloka\Core\Billing\Contracts;

use Viraloka\Core\Billing\Subscription;

/**
 * Subscription Engine Interface
 * 
 * Defines the contract for subscription lifecycle management.
 * 
 * Requirements: 2.1-2.10, 9.1-9.8
 */
interface SubscriptionEngineInterface
{
    /**
     * Create a new subscription for workspace
     * 
     * @param string $workspaceId UUID
     * @param string $planId Plan identifier
     * @return Subscription
     * @throws \Viraloka\Core\Billing\Exceptions\SubscriptionExistsException
     * @throws \Viraloka\Core\Billing\Exceptions\PlanNotFoundException
     */
    public function create(string $workspaceId, string $planId): Subscription;
    
    /**
     * Change subscription plan
     * 
     * @param string $workspaceId UUID
     * @param string $planId New plan identifier
     * @return Subscription
     * @throws \Viraloka\Core\Billing\Exceptions\SubscriptionNotFoundException
     * @throws \Viraloka\Core\Billing\Exceptions\PlanNotFoundException
     */
    public function changePlan(string $workspaceId, string $planId): Subscription;
    
    /**
     * Pause active subscription
     * 
     * @param string $workspaceId UUID
     * @return bool
     * @throws \Viraloka\Core\Billing\Exceptions\SubscriptionNotFoundException
     * @throws \Viraloka\Core\Billing\Exceptions\InvalidStatusException
     */
    public function pause(string $workspaceId): bool;
    
    /**
     * Resume paused subscription
     * 
     * @param string $workspaceId UUID
     * @return bool
     * @throws \Viraloka\Core\Billing\Exceptions\SubscriptionNotFoundException
     * @throws \Viraloka\Core\Billing\Exceptions\InvalidStatusException
     */
    public function resume(string $workspaceId): bool;
    
    /**
     * Cancel subscription
     * 
     * @param string $workspaceId UUID
     * @return bool
     * @throws \Viraloka\Core\Billing\Exceptions\SubscriptionNotFoundException
     */
    public function cancel(string $workspaceId): bool;
    
    /**
     * Get current active subscription for workspace
     * 
     * @param string $workspaceId UUID
     * @return Subscription|null
     */
    public function current(string $workspaceId): ?Subscription;
    
    /**
     * Check if workspace has active subscription
     * 
     * @param string $workspaceId UUID
     * @return bool
     */
    public function hasActiveSubscription(string $workspaceId): bool;
    
    /**
     * Process subscription expirations (called by scheduler)
     * 
     * @return int Number of subscriptions expired
     */
    public function processExpirations(): int;
}
