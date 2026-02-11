<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Contracts;

use Viraloka\Core\Grant\Grant;

/**
 * Grant Repository Interface
 * 
 * Defines the contract for grant persistence operations.
 * Handles CRUD operations and listing active grants.
 */
interface GrantRepositoryInterface
{
    /**
     * Create a new grant
     * 
     * @param Grant $grant
     * @return Grant
     */
    public function create(Grant $grant): Grant;
    
    /**
     * Update an existing grant
     * 
     * @param Grant $grant
     * @return Grant
     */
    public function update(Grant $grant): Grant;
    
    /**
     * Delete a grant
     * 
     * @param string $grantId UUID
     * @return bool
     */
    public function delete(string $grantId): bool;
    
    /**
     * Find grant by ID
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
    public function findActiveByIdentityAndWorkspace(string $identityId, string $workspaceId): array;
    
    /**
     * List all grants for an identity
     * 
     * @param string $identityId UUID
     * @return Grant[]
     */
    public function findByIdentity(string $identityId): array;
    
    /**
     * List all grants for a workspace
     * 
     * @param string $workspaceId UUID
     * @return Grant[]
     */
    public function findByWorkspace(string $workspaceId): array;
}
