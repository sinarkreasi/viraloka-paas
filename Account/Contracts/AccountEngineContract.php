<?php

namespace Viraloka\Core\Account\Contracts;

use Viraloka\Core\Workspace\Workspace;

/**
 * Account Engine Contract
 * 
 * Defines the interface for account management, role assignment, and capability
 * management within workspace boundaries. All operations are workspace-scoped.
 */
interface AccountEngineContract
{
    /**
     * Assign a role to a user in a workspace
     * 
     * Roles are workspace-scoped, meaning a user can have different roles
     * in different workspaces.
     * 
     * @param string $userId User ID
     * @param string $role Role identifier (e.g., 'admin', 'member', 'viewer')
     * @param Workspace $workspace Workspace context
     * @return bool True on success, false on failure
     */
    public function assignRole(string $userId, string $role, Workspace $workspace): bool;
    
    /**
     * Grant a capability to a user in a workspace
     * 
     * Capabilities are workspace-scoped permissions that can be granted
     * independently of roles.
     * 
     * @param string $userId User ID
     * @param string $capability Capability identifier
     * @param Workspace $workspace Workspace context
     * @return bool True on success, false on failure
     */
    public function grantCapability(string $userId, string $capability, Workspace $workspace): bool;
    
    /**
     * Check if a user has a specific permission in a workspace
     * 
     * Checks both role-based and capability-based permissions.
     * 
     * @param string $userId User ID
     * @param string $permission Permission identifier
     * @param Workspace $workspace Workspace context
     * @return bool True if user has permission, false otherwise
     */
    public function checkPermission(string $userId, string $permission, Workspace $workspace): bool;
    
    /**
     * Check if a user has a specific role in a workspace
     * 
     * @param string $userId User ID
     * @param string $role Role identifier
     * @param Workspace $workspace Workspace context
     * @return bool True if user has the role, false otherwise
     */
    public function hasRole(string $userId, string $role, Workspace $workspace): bool;
    
    /**
     * Check if a user has a specific capability in a workspace
     * 
     * @param string $userId User ID
     * @param string $capability Capability identifier
     * @param Workspace $workspace Workspace context
     * @return bool True if user has the capability, false otherwise
     */
    public function hasCapability(string $userId, string $capability, Workspace $workspace): bool;
    
    /**
     * Get all roles for a user in a workspace
     * 
     * @param string $userId User ID
     * @param Workspace $workspace Workspace context
     * @return array Array of role identifiers
     */
    public function getUserRoles(string $userId, Workspace $workspace): array;
    
    /**
     * Get all capabilities for a user in a workspace
     * 
     * @param string $userId User ID
     * @param Workspace $workspace Workspace context
     * @return array Array of capability identifiers
     */
    public function getUserCapabilities(string $userId, Workspace $workspace): array;
    
    /**
     * Revoke a role from a user in a workspace
     * 
     * @param string $userId User ID
     * @param string $role Role identifier
     * @param Workspace $workspace Workspace context
     * @return bool True on success, false on failure
     */
    public function revokeRole(string $userId, string $role, Workspace $workspace): bool;
    
    /**
     * Revoke a capability from a user in a workspace
     * 
     * @param string $userId User ID
     * @param string $capability Capability identifier
     * @param Workspace $workspace Workspace context
     * @return bool True on success, false on failure
     */
    public function revokeCapability(string $userId, string $capability, Workspace $workspace): bool;
}
