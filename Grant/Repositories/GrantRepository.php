<?php

declare(strict_types=1);

namespace Viraloka\Core\Grant\Repositories;

use Viraloka\Core\Grant\Grant;
use Viraloka\Core\Grant\Contracts\GrantRepositoryInterface;
use Viraloka\Core\Adapter\Contracts\StorageAdapterInterface;
use DateTimeImmutable;

/**
 * Grant Repository
 * 
 * Handles persistence of Grant entities using StorageAdapter.
 * Implements active grant listing.
 * 
 * Requirements: 6.8
 */
class GrantRepository implements GrantRepositoryInterface
{
    private const PREFIX_ID = 'grant:id:';
    private const INDEX_IDENTITY = 'grant:index:identity:';
    private const INDEX_WORKSPACE = 'grant:index:workspace:';
    private const INDEX_IDENTITY_WORKSPACE = 'grant:index:identity_workspace:';

    public function __construct(
        private readonly StorageAdapterInterface $storageAdapter
    ) {}

    /**
     * Create a new grant
     * 
     * @param Grant $grant
     * @return Grant
     */
    public function create(Grant $grant): Grant
    {
        // Serialize grant data
        $data = $this->serialize($grant);

        // Store by ID
        $idKey = self::PREFIX_ID . $grant->grantId;
        $this->storageAdapter->set($idKey, $data);

        // Add to indexes
        $this->addToIdentityIndex($grant->identityId, $grant->grantId);
        $this->addToWorkspaceIndex($grant->workspaceId, $grant->grantId);
        $this->addToIdentityWorkspaceIndex($grant->identityId, $grant->workspaceId, $grant->grantId);

        return $grant;
    }

    /**
     * Update an existing grant
     * 
     * @param Grant $grant
     * @return Grant
     */
    public function update(Grant $grant): Grant
    {
        // Serialize updated data
        $data = $this->serialize($grant);

        // Update by ID
        $idKey = self::PREFIX_ID . $grant->grantId;
        $this->storageAdapter->set($idKey, $data);

        return $grant;
    }

    /**
     * Delete a grant
     * 
     * @param string $grantId UUID
     * @return bool
     */
    public function delete(string $grantId): bool
    {
        // Get grant first to remove from indexes
        $grant = $this->findById($grantId);
        if ($grant === null) {
            return false;
        }

        // Delete by ID
        $idKey = self::PREFIX_ID . $grantId;
        $this->storageAdapter->delete($idKey);

        // Remove from indexes
        $this->removeFromIdentityIndex($grant->identityId, $grantId);
        $this->removeFromWorkspaceIndex($grant->workspaceId, $grantId);
        $this->removeFromIdentityWorkspaceIndex($grant->identityId, $grant->workspaceId, $grantId);

        return true;
    }

    /**
     * Find grant by ID
     * 
     * @param string $grantId UUID
     * @return Grant|null
     */
    public function findById(string $grantId): ?Grant
    {
        $key = self::PREFIX_ID . $grantId;
        $data = $this->storageAdapter->get($key);

        if ($data === null) {
            return null;
        }

        return $this->deserialize($data);
    }

    /**
     * List active grants for identity in workspace
     * 
     * @param string $identityId UUID
     * @param string $workspaceId UUID
     * @return Grant[]
     */
    public function findActiveByIdentityAndWorkspace(string $identityId, string $workspaceId): array
    {
        $indexKey = self::INDEX_IDENTITY_WORKSPACE . $identityId . ':' . $workspaceId;
        $grantIds = $this->storageAdapter->get($indexKey, []);

        if (!is_array($grantIds)) {
            return [];
        }

        $grants = [];
        foreach ($grantIds as $grantId) {
            $grant = $this->findById($grantId);
            if ($grant !== null && $grant->status === Grant::STATUS_ACTIVE) {
                $grants[] = $grant;
            }
        }

        return $grants;
    }

    /**
     * List all grants for an identity
     * 
     * @param string $identityId UUID
     * @return Grant[]
     */
    public function findByIdentity(string $identityId): array
    {
        $indexKey = self::INDEX_IDENTITY . $identityId;
        $grantIds = $this->storageAdapter->get($indexKey, []);

        if (!is_array($grantIds)) {
            return [];
        }

        $grants = [];
        foreach ($grantIds as $grantId) {
            $grant = $this->findById($grantId);
            if ($grant !== null) {
                $grants[] = $grant;
            }
        }

        return $grants;
    }

    /**
     * List all grants for a workspace
     * 
     * @param string $workspaceId UUID
     * @return Grant[]
     */
    public function findByWorkspace(string $workspaceId): array
    {
        $indexKey = self::INDEX_WORKSPACE . $workspaceId;
        $grantIds = $this->storageAdapter->get($indexKey, []);

        if (!is_array($grantIds)) {
            return [];
        }

        $grants = [];
        foreach ($grantIds as $grantId) {
            $grant = $this->findById($grantId);
            if ($grant !== null) {
                $grants[] = $grant;
            }
        }

        return $grants;
    }

    /**
     * Serialize grant to array
     * 
     * @param Grant $grant
     * @return array
     */
    private function serialize(Grant $grant): array
    {
        return [
            'grant_id' => $grant->grantId,
            'identity_id' => $grant->identityId,
            'workspace_id' => $grant->workspaceId,
            'role' => $grant->role,
            'status' => $grant->status,
            'expires_at' => $grant->expiresAt?->format('Y-m-d H:i:s'),
            'max_usage' => $grant->maxUsage,
            'current_usage' => $grant->currentUsage,
            'allowed_actions' => $grant->allowedActions,
            'metadata' => $grant->metadata,
            'created_at' => $grant->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Deserialize array to grant
     * 
     * @param array $data
     * @return Grant
     */
    private function deserialize(array $data): Grant
    {
        $grant = new Grant(
            $data['grant_id'],
            $data['identity_id'],
            $data['workspace_id'],
            $data['role'],
            isset($data['expires_at']) && $data['expires_at'] !== null 
                ? new DateTimeImmutable($data['expires_at']) 
                : null,
            $data['max_usage'] ?? null,
            $data['allowed_actions'] ?? null,
            $data['metadata'] ?? [],
            new DateTimeImmutable($data['created_at'])
        );

        // Restore status and current usage
        $grant->status = $data['status'];
        $grant->currentUsage = $data['current_usage'] ?? 0;

        return $grant;
    }

    /**
     * Add grant to identity index
     * 
     * @param string $identityId
     * @param string $grantId
     * @return void
     */
    private function addToIdentityIndex(string $identityId, string $grantId): void
    {
        $indexKey = self::INDEX_IDENTITY . $identityId;
        $grantIds = $this->storageAdapter->get($indexKey, []);
        
        if (!is_array($grantIds)) {
            $grantIds = [];
        }

        if (!in_array($grantId, $grantIds, true)) {
            $grantIds[] = $grantId;
            $this->storageAdapter->set($indexKey, $grantIds);
        }
    }

    /**
     * Remove grant from identity index
     * 
     * @param string $identityId
     * @param string $grantId
     * @return void
     */
    private function removeFromIdentityIndex(string $identityId, string $grantId): void
    {
        $indexKey = self::INDEX_IDENTITY . $identityId;
        $grantIds = $this->storageAdapter->get($indexKey, []);
        
        if (!is_array($grantIds)) {
            return;
        }

        $grantIds = array_values(array_filter($grantIds, fn($id) => $id !== $grantId));
        $this->storageAdapter->set($indexKey, $grantIds);
    }

    /**
     * Add grant to workspace index
     * 
     * @param string $workspaceId
     * @param string $grantId
     * @return void
     */
    private function addToWorkspaceIndex(string $workspaceId, string $grantId): void
    {
        $indexKey = self::INDEX_WORKSPACE . $workspaceId;
        $grantIds = $this->storageAdapter->get($indexKey, []);
        
        if (!is_array($grantIds)) {
            $grantIds = [];
        }

        if (!in_array($grantId, $grantIds, true)) {
            $grantIds[] = $grantId;
            $this->storageAdapter->set($indexKey, $grantIds);
        }
    }

    /**
     * Remove grant from workspace index
     * 
     * @param string $workspaceId
     * @param string $grantId
     * @return void
     */
    private function removeFromWorkspaceIndex(string $workspaceId, string $grantId): void
    {
        $indexKey = self::INDEX_WORKSPACE . $workspaceId;
        $grantIds = $this->storageAdapter->get($indexKey, []);
        
        if (!is_array($grantIds)) {
            return;
        }

        $grantIds = array_values(array_filter($grantIds, fn($id) => $id !== $grantId));
        $this->storageAdapter->set($indexKey, $grantIds);
    }

    /**
     * Add grant to identity-workspace index
     * 
     * @param string $identityId
     * @param string $workspaceId
     * @param string $grantId
     * @return void
     */
    private function addToIdentityWorkspaceIndex(string $identityId, string $workspaceId, string $grantId): void
    {
        $indexKey = self::INDEX_IDENTITY_WORKSPACE . $identityId . ':' . $workspaceId;
        $grantIds = $this->storageAdapter->get($indexKey, []);
        
        if (!is_array($grantIds)) {
            $grantIds = [];
        }

        if (!in_array($grantId, $grantIds, true)) {
            $grantIds[] = $grantId;
            $this->storageAdapter->set($indexKey, $grantIds);
        }
    }

    /**
     * Remove grant from identity-workspace index
     * 
     * @param string $identityId
     * @param string $workspaceId
     * @param string $grantId
     * @return void
     */
    private function removeFromIdentityWorkspaceIndex(string $identityId, string $workspaceId, string $grantId): void
    {
        $indexKey = self::INDEX_IDENTITY_WORKSPACE . $identityId . ':' . $workspaceId;
        $grantIds = $this->storageAdapter->get($indexKey, []);
        
        if (!is_array($grantIds)) {
            return;
        }

        $grantIds = array_values(array_filter($grantIds, fn($id) => $id !== $grantId));
        $this->storageAdapter->set($indexKey, $grantIds);
    }
}
