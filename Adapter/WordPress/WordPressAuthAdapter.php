<?php

namespace Viraloka\Adapter\WordPress;

use Viraloka\Core\Adapter\Contracts\AuthAdapterInterface;
use Viraloka\Core\Adapter\ValueObjects\User;

/**
 * WordPress implementation of the Auth Adapter.
 * 
 * Wraps WordPress user functions (wp_get_current_user, current_user_can)
 * and maps Core permissions to WordPress capabilities.
 */
class WordPressAuthAdapter implements AuthAdapterInterface
{
    /**
     * Permission mapping from Core permissions to WordPress capabilities.
     *
     * @var array<string, string>
     */
    private array $permissionMap = [
        'manage_workspace' => 'manage_options',
        'edit_content' => 'edit_posts',
        'delete_content' => 'delete_posts',
        'manage_users' => 'list_users',
        'view_analytics' => 'read',
    ];

    /**
     * Authenticate a user with username and password.
     *
     * @param string $username Username or email
     * @param string $password Password
     * @return User|null User value object on success, null on failure
     */
    public function authenticate(string $username, string $password): ?User
    {
        if (!function_exists('wp_authenticate')) {
            return null;
        }

        $wpUser = wp_authenticate($username, $password);
        
        // Check if authentication failed
        if (is_wp_error($wpUser)) {
            return null;
        }

        return new User(
            id: (string) $wpUser->ID,
            email: $wpUser->user_email,
            displayName: $wpUser->display_name,
            roles: $wpUser->roles,
            meta: [
                'registered' => $wpUser->user_registered,
                'login' => $wpUser->user_login,
            ]
        );
    }

    /**
     * Get the current authenticated user.
     *
     * @return User|null User value object or null if not authenticated
     */
    public function currentUser(): ?User
    {
        if (!function_exists('wp_get_current_user')) {
            return null;
        }

        $wpUser = wp_get_current_user();
        
        if ($wpUser->ID === 0) {
            return null;
        }

        return new User(
            id: (string) $wpUser->ID,
            email: $wpUser->user_email,
            displayName: $wpUser->display_name,
            roles: $wpUser->roles,
            meta: [
                'registered' => $wpUser->user_registered,
                'login' => $wpUser->user_login,
            ]
        );
    }

    /**
     * Check if a user is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return function_exists('is_user_logged_in') && is_user_logged_in();
    }

    /**
     * Check if the current user has a specific permission.
     *
     * @param string $permission Permission name (Core permission, not host-specific)
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        // Map Core permission to WordPress capability
        $capability = $this->permissionMap[$permission] ?? $permission;
        
        return current_user_can($capability);
    }

    /**
     * Check if the current user has a specific role.
     *
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        $user = $this->currentUser();
        return $user !== null && $user->hasRole($role);
    }

    /**
     * Get all permissions for the current user.
     *
     * @return array<string> List of permission names
     */
    public function getPermissions(): array
    {
        if (!$this->isAuthenticated()) {
            return [];
        }

        $permissions = [];
        foreach ($this->permissionMap as $corePermission => $wpCapability) {
            if (current_user_can($wpCapability)) {
                $permissions[] = $corePermission;
            }
        }
        return $permissions;
    }

    /**
     * Get all roles for the current user.
     *
     * @return array<string> List of role names
     */
    public function getRoles(): array
    {
        $user = $this->currentUser();
        return $user?->roles ?? [];
    }

    /**
     * Verify a nonce for CSRF protection.
     *
     * @param string $nonce The nonce to verify
     * @param string $action The action name
     * @return bool True if nonce is valid, false otherwise
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        if (!function_exists('wp_verify_nonce')) {
            return false;
        }
        
        return wp_verify_nonce($nonce, $action) !== false;
    }
}
