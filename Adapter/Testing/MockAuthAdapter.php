<?php

namespace Viraloka\Adapter\Testing;

use Viraloka\Core\Adapter\Contracts\AuthAdapterInterface;
use Viraloka\Core\Adapter\ValueObjects\User;

/**
 * Mock auth adapter for testing Core in isolation.
 * 
 * This adapter provides configurable authentication state without any external dependencies,
 * allowing Core to be tested without WordPress or other host environments.
 */
class MockAuthAdapter implements AuthAdapterInterface
{
    private ?User $currentUser = null;
    private array $permissions = [];
    private array $roles = [];
    private array $mockUsers = []; // Store mock users for authentication testing

    /**
     * Authenticate a user with username and password.
     *
     * @param string $username Username or email
     * @param string $password Password
     * @return User|null User value object on success, null on failure
     */
    public function authenticate(string $username, string $password): ?User
    {
        // Check if user exists in mock users
        if (isset($this->mockUsers[$username])) {
            $userData = $this->mockUsers[$username];
            
            // Verify password
            if ($userData['password'] === $password) {
                $user = new User(
                    id: $userData['id'],
                    email: $userData['email'],
                    displayName: $userData['displayName'],
                    roles: $userData['roles'] ?? [],
                    meta: $userData['meta'] ?? []
                );
                
                // Set as current user
                $this->setCurrentUser($user);
                
                return $user;
            }
        }
        
        return null;
    }

    /**
     * Get the current authenticated user.
     *
     * @return User|null User value object or null if not authenticated
     */
    public function currentUser(): ?User
    {
        return $this->currentUser;
    }

    /**
     * Check if a user is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool
    {
        return $this->currentUser !== null;
    }

    /**
     * Check if the current user has a specific permission.
     *
     * @param string $permission Permission name (Core permission, not host-specific)
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Check if the current user has a specific role.
     *
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    /**
     * Get all permissions for the current user.
     *
     * @return array<string> List of permission names
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get all roles for the current user.
     *
     * @return array<string> List of role names
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Verify a nonce for CSRF protection.
     *
     * For testing purposes, this implementation accepts any nonce
     * unless explicitly configured otherwise.
     *
     * @param string $nonce The nonce to verify
     * @param string $action The action name
     * @return bool True if nonce is valid, false otherwise
     */
    public function verifyNonce(string $nonce, string $action): bool
    {
        // For testing, accept any non-empty nonce by default
        // Tests can override this behavior by setting specific nonces
        return !empty($nonce);
    }

    // Test helper methods

    /**
     * Set the current user (for testing purposes).
     *
     * @param User|null $user
     */
    public function setCurrentUser(?User $user): void
    {
        $this->currentUser = $user;
        
        if ($user !== null) {
            $this->roles = $user->roles;
        } else {
            $this->roles = [];
            $this->permissions = [];
        }
    }

    /**
     * Set permissions (for testing purposes).
     *
     * @param array<string> $permissions
     */
    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    /**
     * Add a permission (for testing purposes).
     *
     * @param string $permission
     */
    public function addPermission(string $permission): void
    {
        if (!in_array($permission, $this->permissions, true)) {
            $this->permissions[] = $permission;
        }
    }

    /**
     * Remove a permission (for testing purposes).
     *
     * @param string $permission
     */
    public function removePermission(string $permission): void
    {
        $this->permissions = array_values(array_filter(
            $this->permissions,
            fn($p) => $p !== $permission
        ));
    }

    /**
     * Set roles (for testing purposes).
     *
     * @param array<string> $roles
     */
    public function setRoles(array $roles): void
    {
        $this->roles = $roles;
    }

    /**
     * Add a role (for testing purposes).
     *
     * @param string $role
     */
    public function addRole(string $role): void
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
    }

    /**
     * Remove a role (for testing purposes).
     *
     * @param string $role
     */
    public function removeRole(string $role): void
    {
        $this->roles = array_values(array_filter(
            $this->roles,
            fn($r) => $r !== $role
        ));
    }

    /**
     * Clear authentication (for testing purposes).
     */
    public function clearAuth(): void
    {
        $this->currentUser = null;
        $this->permissions = [];
        $this->roles = [];
    }

    /**
     * Add a mock user for authentication testing.
     *
     * @param string $username Username
     * @param string $password Password
     * @param string $id User ID
     * @param string $email Email
     * @param string $displayName Display name
     * @param array $roles Roles
     * @param array $meta Additional metadata
     */
    public function addMockUser(
        string $username,
        string $password,
        string $id,
        string $email,
        string $displayName,
        array $roles = [],
        array $meta = []
    ): void {
        $this->mockUsers[$username] = [
            'password' => $password,
            'id' => $id,
            'email' => $email,
            'displayName' => $displayName,
            'roles' => $roles,
            'meta' => $meta,
        ];
    }

    /**
     * Remove a mock user.
     *
     * @param string $username Username
     */
    public function removeMockUser(string $username): void
    {
        unset($this->mockUsers[$username]);
    }

    /**
     * Clear all mock users.
     */
    public function clearMockUsers(): void
    {
        $this->mockUsers = [];
    }
}
