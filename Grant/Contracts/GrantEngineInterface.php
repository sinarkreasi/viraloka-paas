<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Contracts;

use Viraloka\Core\Grant\Grant;

/**
 * Grant Engine Interface
 * 
 * Defines the contract for managing temporary access grants with constraints.
 */
interface GrantEngineInterface
{
    /**
     * Issue a new grant
     * 
     * @param string $identityId UUID
     * @param string $workspaceId UUID
     * @param string $role Role to grant
     * @param array $constraints ['expires_at' => DateTimeImmutable, 'max_usage' => int, 'allowed_actions' => array]
     * @param array $metadata Optional metadata
     * @return Grant
     * @throws \Viraloka\Core\Grant\Exceptions\NoConstraintException
     */
    public function issue(
        string $identityId,
        string $workspaceId,
        string $role,
        array $constraints,
        array $metadata = []
    ): Grant;
    
    /**
     * Revoke a grant
     * 
     * @param string $grantId UUID
     * @return bool
     */
    public function revoke(string $grantId): bool;
    
    /**
     * Validate if grant is still valid
     * 
     * @param string $grantId UUID
     * @return bool
     */
    public function validate(string $grantId): bool;
    
    /**
     * Consume one usage from grant
     * 
     * @param string $grantId UUID
     * @return bool
     * @throws \Viraloka\Core\Grant\Exceptions\GrantExhaustedException
     */
    public function consume(string $grantId): bool;
    
    /**
     * Get grant by ID
     * 
     * @param string $grantId UUID
     * @return Grant|null
     */
    public function findById(string $grantId): ?Grant;
    
    /**
     * List active grants for identity in workspace
     * 
     * @param string $identityId UUID
     * @param string $workspaceId UUID
     * @return Grant[]
     */
    public function getActiveGrants(string $identityId, string $workspaceId): array;
}
