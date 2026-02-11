<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant;

use Viraloka\Core\Application;
use Viraloka\Core\Grant\Contracts\GrantEngineInterface;
use Viraloka\Core\Grant\Contracts\GrantRepositoryInterface;
use Viraloka\Core\Grant\Repositories\GrantRepository;
use Viraloka\Core\Grant\Events\GrantIssuedEvent;
use Viraloka\Core\Grant\Events\GrantConsumedEvent;
use Viraloka\Core\Grant\Events\GrantExpiredEvent;
use Viraloka\Core\Grant\Events\GrantRevokedEvent;
use Viraloka\Core\Grant\Events\GrantExhaustedEvent;
use Viraloka\Core\Grant\Exceptions\NoConstraintException;
use Viraloka\Core\Grant\Exceptions\GrantNotFoundException;
use Viraloka\Core\Grant\Exceptions\GrantExhaustedException;
use Viraloka\Core\Events\EventDispatcher;
use Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface;
use DateTimeImmutable;

/**
 * Grant Engine
 * 
 * Manages the lifecycle of Grant entities including issue, revoke, validate, and consume.
 * Integrates with EventDispatcher for lifecycle events.
 * Enforces constraint requirements and validation.
 * Uses AdapterRegistry for StorageAdapter access.
 * 
 * Requirements: 6.1-6.10, 7.1-7.9, 11.4, 11.5
 */
class GrantEngine implements GrantEngineInterface
{
    private readonly GrantRepositoryInterface $repository;
    private readonly EventDispatcher $eventDispatcher;

    /**
     * Create a new GrantEngine instance
     * 
     * @param Application $app
     */
    public function __construct(private readonly Application $app)
    {
        // Resolve dependencies from container (Requirement 11.4, 11.5)
        $adapters = $this->app->make(AdapterRegistryInterface::class);
        
        // Create repository with StorageAdapter
        $this->repository = new GrantRepository($adapters->storage());
        
        // Get EventDispatcher from Kernel
        $kernel = $this->app->make(\Viraloka\Core\Bootstrap\Kernel::class);
        $this->eventDispatcher = $kernel->getEventDispatcher();
    }

    /**
     * Issue a new grant
     * 
     * @param string $identityId UUID
     * @param string $workspaceId UUID
     * @param string $role Role to grant
     * @param array $constraints ['expires_at' => DateTimeImmutable, 'max_usage' => int, 'allowed_actions' => array]
     * @param array $metadata Optional metadata
     * @return Grant
     * @throws NoConstraintException If no constraints provided
     */
    public function issue(
        string $identityId,
        string $workspaceId,
        string $role,
        array $constraints,
        array $metadata = []
    ): Grant
    {
        // Extract constraints (Requirement 6.3, 6.4, 6.5)
        $expiresAt = $constraints['expires_at'] ?? null;
        $maxUsage = $constraints['max_usage'] ?? null;
        $allowedActions = $constraints['allowed_actions'] ?? null;

        // Generate UUID for grant_id (Requirement 6.8)
        $grantId = $this->generateUuid();

        // Create grant (Requirement 6.1, 6.6, 6.7, 6.10)
        $grant = new Grant(
            $grantId,
            $identityId,
            $workspaceId,
            $role,
            $expiresAt,
            $maxUsage,
            $allowedActions,
            $metadata,
            new DateTimeImmutable()
        );

        // Validate constraint requirement (Requirement 6.2, 7.5)
        if (!$grant->hasConstraint()) {
            throw new NoConstraintException();
        }

        // Persist grant
        $grant = $this->repository->create($grant);

        // Emit grant.issued event (Requirement 8.1, 8.6, 8.7)
        $this->eventDispatcher->dispatch(
            'grant.issued',
            new GrantIssuedEvent(
                $grant->grantId,
                $grant->identityId,
                $grant->workspaceId,
                $grant->role,
                [
                    'expires_at' => $grant->expiresAt,
                    'max_usage' => $grant->maxUsage,
                    'allowed_actions' => $grant->allowedActions,
                ],
                $grant->createdAt
            )
        );

        return $grant;
    }

    /**
     * Revoke a grant
     * 
     * @param string $grantId UUID
     * @return bool
     */
    public function revoke(string $grantId): bool
    {
        // Find grant (Requirement 7.2)
        $grant = $this->repository->findById($grantId);
        
        if ($grant === null) {
            return false;
        }

        // Revoke grant
        $grant->revoke();

        // Persist changes
        $this->repository->update($grant);

        // Emit grant.revoked event (Requirement 8.4, 8.6, 8.7)
        $this->eventDispatcher->dispatch(
            'grant.revoked',
            new GrantRevokedEvent(
                $grant->grantId,
                new DateTimeImmutable()
            )
        );

        return true;
    }

    /**
     * Validate if grant is still valid
     * 
     * @param string $grantId UUID
     * @return bool
     */
    public function validate(string $grantId): bool
    {
        // Find grant (Requirement 7.3)
        $grant = $this->repository->findById($grantId);
        
        if ($grant === null) {
            return false;
        }

        // Check if grant is valid (Requirement 7.6, 7.7)
        $isValid = $grant->isValid();

        // If grant has expired due to time constraint, emit event
        if (!$isValid && $grant->expiresAt !== null && $grant->expiresAt < new DateTimeImmutable()) {
            if ($grant->status === Grant::STATUS_ACTIVE) {
                $grant->status = Grant::STATUS_EXPIRED;
                $this->repository->update($grant);
                
                // Emit grant.expired event (Requirement 8.3, 8.6, 8.7)
                $this->eventDispatcher->dispatch(
                    'grant.expired',
                    new GrantExpiredEvent(
                        $grant->grantId,
                        new DateTimeImmutable()
                    )
                );
            }
        }

        return $isValid;
    }

    /**
     * Consume one usage from grant
     * 
     * @param string $grantId UUID
     * @return bool
     * @throws GrantExhaustedException If grant is exhausted
     */
    public function consume(string $grantId): bool
    {
        // Find grant (Requirement 7.4)
        $grant = $this->repository->findById($grantId);
        
        if ($grant === null) {
            throw new GrantNotFoundException($grantId);
        }

        // Check if grant is valid before consuming
        if (!$grant->isValid()) {
            // If exhausted, throw exception (Requirement 7.9)
            if ($grant->status === Grant::STATUS_EXHAUSTED) {
                throw new GrantExhaustedException($grantId);
            }
            return false;
        }

        // Consume usage (Requirement 6.9, 7.8)
        $consumed = $grant->consume();

        if (!$consumed) {
            return false;
        }

        // Persist changes
        $this->repository->update($grant);

        // Emit grant.consumed event (Requirement 8.2, 8.6, 8.7)
        $this->eventDispatcher->dispatch(
            'grant.consumed',
            new GrantConsumedEvent(
                $grant->grantId,
                $grant->currentUsage,
                $grant->maxUsage,
                new DateTimeImmutable()
            )
        );

        // If grant was exhausted by this consumption, emit exhausted event
        if ($grant->status === Grant::STATUS_EXHAUSTED) {
            // Emit grant.exhausted event (Requirement 8.5, 8.6, 8.7)
            $this->eventDispatcher->dispatch(
                'grant.exhausted',
                new GrantExhaustedEvent(
                    $grant->grantId,
                    $grant->currentUsage,
                    new DateTimeImmutable()
                )
            );
        }

        return true;
    }

    /**
     * Get grant by ID
     * 
     * @param string $grantId UUID
     * @return Grant|null
     */
    public function findById(string $grantId): ?Grant
    {
        return $this->repository->findById($grantId);
    }

    /**
     * List active grants for identity in workspace
     * 
     * @param string $identityId UUID
     * @param string $workspaceId UUID
     * @return Grant[]
     */
    public function getActiveGrants(string $identityId, string $workspaceId): array
    {
        // Requirement 6.8
        return $this->repository->findActiveByIdentityAndWorkspace($identityId, $workspaceId);
    }

    /**
     * Generate a UUID v4
     * 
     * @return string
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
