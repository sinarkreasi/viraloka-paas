<?php

namespace Viraloka\Core\Account;

use Viraloka\Core\Application;
use Viraloka\Core\Account\Contracts\AccountEngineContract;
use Viraloka\Core\Modules\Logger;
use Viraloka\Core\Workspace\Workspace;

/**
 * AccountEngine
 * 
 * Provides account management interface with workspace-scoped role and capability
 * management. Handles workspace-scoped permissions and integrates with WordPress
 * user system.
 */
class AccountEngine implements AccountEngineContract
{
    /**
     * The application instance
     * 
     * @var Application
     */
    protected Application $app;
    
    /**
     * Logger instance
     * 
     * @var Logger
     */
    protected Logger $logger;
    
    /**
     * Role capabilities mapping
     * 
     * Defines which capabilities each role includes.
     * 
     * @var array
     */
    protected array $roleCapabilities = [
        'admin' => [
            'manage_viraloka_modules',
            'manage_viraloka_workspaces',
            'manage_viraloka_users',
            'manage_viraloka_settings',
            'view_viraloka_analytics',
            'access_viraloka_api',
        ],
        'member' => [
            'view_viraloka_analytics',
            'access_viraloka_api',
        ],
        'viewer' => [
            'view_viraloka_analytics',
        ],
    ];
    
    /**
     * Permission cache
     * 
     * @var array
     */
    protected array $permissionCache = [];
    
    /**
     * Create a new AccountEngine instance
     * 
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->logger = $app->make(Logger::class);
    }
    
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
    public function assignRole(string $userId, string $role, Workspace $workspace): bool
    {
        try {
            // Get current roles for this workspace
            $roles = $this->getUserRoles($userId, $workspace);
            
            // Add role if not already assigned
            if (!in_array($role, $roles)) {
                $roles[] = $role;
                
                // Store workspace-scoped roles
                $metaKey = $this->getWorkspaceMetaKey('roles', $workspace);
                update_user_meta($userId, $metaKey, $roles);
                
                // Clear cache
                $this->clearPermissionCache($userId, $workspace);
                
                $this->logger->info(
                    sprintf('Role %s assigned to user %s in workspace %s', $role, $userId, $workspace->id),
                    'account-engine'
                );
            }
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to assign role %s to user %s in workspace %s: %s', 
                    $role, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Role Assignment Error'
            );
            return false;
        }
    }
    
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
    public function grantCapability(string $userId, string $capability, Workspace $workspace): bool
    {
        try {
            // Get current capabilities for this workspace
            $capabilities = $this->getUserCapabilities($userId, $workspace);
            
            // Add capability if not already granted
            if (!in_array($capability, $capabilities)) {
                $capabilities[] = $capability;
                
                // Store workspace-scoped capabilities
                $metaKey = $this->getWorkspaceMetaKey('capabilities', $workspace);
                update_user_meta($userId, $metaKey, $capabilities);
                
                // Clear cache
                $this->clearPermissionCache($userId, $workspace);
                
                $this->logger->info(
                    sprintf('Capability %s granted to user %s in workspace %s', $capability, $userId, $workspace->id),
                    'account-engine'
                );
            }
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to grant capability %s to user %s in workspace %s: %s', 
                    $capability, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Capability Grant Error'
            );
            return false;
        }
    }
    
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
    public function checkPermission(string $userId, string $permission, Workspace $workspace): bool
    {
        // Check cache first
        $cacheKey = $this->getPermissionCacheKey($userId, $permission, $workspace);
        if (isset($this->permissionCache[$cacheKey])) {
            return $this->permissionCache[$cacheKey];
        }
        
        try {
            // Check if user has the capability directly
            if ($this->hasCapability($userId, $permission, $workspace)) {
                $this->permissionCache[$cacheKey] = true;
                return true;
            }
            
            // Check if any of the user's roles include this capability
            $roles = $this->getUserRoles($userId, $workspace);
            foreach ($roles as $role) {
                if ($this->roleHasCapability($role, $permission)) {
                    $this->permissionCache[$cacheKey] = true;
                    return true;
                }
            }
            
            // Permission not found
            $this->permissionCache[$cacheKey] = false;
            return false;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to check permission %s for user %s in workspace %s: %s', 
                    $permission, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Permission Check Error'
            );
            
            // Deny on error for security
            return false;
        }
    }
    
    /**
     * Check if a user has a specific role in a workspace
     * 
     * @param string $userId User ID
     * @param string $role Role identifier
     * @param Workspace $workspace Workspace context
     * @return bool True if user has the role, false otherwise
     */
    public function hasRole(string $userId, string $role, Workspace $workspace): bool
    {
        try {
            $roles = $this->getUserRoles($userId, $workspace);
            return in_array($role, $roles);
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to check role %s for user %s in workspace %s: %s', 
                    $role, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Role Check Error'
            );
            return false;
        }
    }
    
    /**
     * Check if a user has a specific capability in a workspace
     * 
     * @param string $userId User ID
     * @param string $capability Capability identifier
     * @param Workspace $workspace Workspace context
     * @return bool True if user has the capability, false otherwise
     */
    public function hasCapability(string $userId, string $capability, Workspace $workspace): bool
    {
        try {
            $capabilities = $this->getUserCapabilities($userId, $workspace);
            return in_array($capability, $capabilities);
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to check capability %s for user %s in workspace %s: %s', 
                    $capability, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Capability Check Error'
            );
            return false;
        }
    }
    
    /**
     * Get all roles for a user in a workspace
     * 
     * @param string $userId User ID
     * @param Workspace $workspace Workspace context
     * @return array Array of role identifiers
     */
    public function getUserRoles(string $userId, Workspace $workspace): array
    {
        try {
            $metaKey = $this->getWorkspaceMetaKey('roles', $workspace);
            $roles = get_user_meta($userId, $metaKey, true);
            
            if (!is_array($roles)) {
                // Default to 'member' role if no roles assigned
                return ['member'];
            }
            
            return $roles;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to get roles for user %s in workspace %s: %s', 
                    $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Get Roles Error'
            );
            return ['member']; // Default role on error
        }
    }
    
    /**
     * Get all capabilities for a user in a workspace
     * 
     * @param string $userId User ID
     * @param Workspace $workspace Workspace context
     * @return array Array of capability identifiers
     */
    public function getUserCapabilities(string $userId, Workspace $workspace): array
    {
        try {
            $metaKey = $this->getWorkspaceMetaKey('capabilities', $workspace);
            $capabilities = get_user_meta($userId, $metaKey, true);
            
            if (!is_array($capabilities)) {
                return [];
            }
            
            return $capabilities;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to get capabilities for user %s in workspace %s: %s', 
                    $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Get Capabilities Error'
            );
            return [];
        }
    }
    
    /**
     * Revoke a role from a user in a workspace
     * 
     * @param string $userId User ID
     * @param string $role Role identifier
     * @param Workspace $workspace Workspace context
     * @return bool True on success, false on failure
     */
    public function revokeRole(string $userId, string $role, Workspace $workspace): bool
    {
        try {
            // Get current roles
            $roles = $this->getUserRoles($userId, $workspace);
            
            // Remove role
            $roles = array_values(array_filter($roles, function($r) use ($role) {
                return $r !== $role;
            }));
            
            // Store updated roles
            $metaKey = $this->getWorkspaceMetaKey('roles', $workspace);
            update_user_meta($userId, $metaKey, $roles);
            
            // Clear cache
            $this->clearPermissionCache($userId, $workspace);
            
            $this->logger->info(
                sprintf('Role %s revoked from user %s in workspace %s', $role, $userId, $workspace->id),
                'account-engine'
            );
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to revoke role %s from user %s in workspace %s: %s', 
                    $role, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Role Revocation Error'
            );
            return false;
        }
    }
    
    /**
     * Revoke a capability from a user in a workspace
     * 
     * @param string $userId User ID
     * @param string $capability Capability identifier
     * @param Workspace $workspace Workspace context
     * @return bool True on success, false on failure
     */
    public function revokeCapability(string $userId, string $capability, Workspace $workspace): bool
    {
        try {
            // Get current capabilities
            $capabilities = $this->getUserCapabilities($userId, $workspace);
            
            // Remove capability
            $capabilities = array_values(array_filter($capabilities, function($c) use ($capability) {
                return $c !== $capability;
            }));
            
            // Store updated capabilities
            $metaKey = $this->getWorkspaceMetaKey('capabilities', $workspace);
            update_user_meta($userId, $metaKey, $capabilities);
            
            // Clear cache
            $this->clearPermissionCache($userId, $workspace);
            
            $this->logger->info(
                sprintf('Capability %s revoked from user %s in workspace %s', $capability, $userId, $workspace->id),
                'account-engine'
            );
            
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('Failed to revoke capability %s from user %s in workspace %s: %s', 
                    $capability, $userId, $workspace->id, $e->getMessage()),
                'account-engine',
                'Capability Revocation Error'
            );
            return false;
        }
    }
    
    /**
     * Check if a role has a specific capability
     * 
     * @param string $role Role identifier
     * @param string $capability Capability identifier
     * @return bool True if role has the capability, false otherwise
     */
    protected function roleHasCapability(string $role, string $capability): bool
    {
        if (!isset($this->roleCapabilities[$role])) {
            return false;
        }
        
        return in_array($capability, $this->roleCapabilities[$role]);
    }
    
    /**
     * Get workspace-scoped meta key
     * 
     * @param string $type Type of meta (roles, capabilities)
     * @param Workspace $workspace Workspace context
     * @return string Meta key
     */
    protected function getWorkspaceMetaKey(string $type, Workspace $workspace): string
    {
        return sprintf('viraloka_workspace_%s_%s', $workspace->id, $type);
    }
    
    /**
     * Get permission cache key
     * 
     * @param string $userId User ID
     * @param string $permission Permission identifier
     * @param Workspace $workspace Workspace context
     * @return string Cache key
     */
    protected function getPermissionCacheKey(string $userId, string $permission, Workspace $workspace): string
    {
        return sprintf('%s:%s:%s', $userId, $workspace->id, $permission);
    }
    
    /**
     * Clear permission cache for a user in a workspace
     * 
     * @param string $userId User ID
     * @param Workspace $workspace Workspace context
     * @return void
     */
    protected function clearPermissionCache(string $userId, Workspace $workspace): void
    {
        $prefix = sprintf('%s:%s:', $userId, $workspace->id);
        
        foreach (array_keys($this->permissionCache) as $key) {
            if (strpos($key, $prefix) === 0) {
                unset($this->permissionCache[$key]);
            }
        }
    }
    
    /**
     * Get all available roles
     * 
     * @return array Array of role identifiers
     */
    public function getAvailableRoles(): array
    {
        return array_keys($this->roleCapabilities);
    }
    
    /**
     * Get capabilities for a role
     * 
     * @param string $role Role identifier
     * @return array Array of capability identifiers
     */
    public function getRoleCapabilities(string $role): array
    {
        return $this->roleCapabilities[$role] ?? [];
    }
}
