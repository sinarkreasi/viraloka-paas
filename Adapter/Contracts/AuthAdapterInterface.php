<?php

namespace Viraloka\Core\Adapter\Contracts;

use Viraloka\Core\Adapter\ValueObjects\User;

/**
 * Auth adapter for user authentication and authorization.
 */
interface AuthAdapterInterface
{
    /**
     * Authenticate a user with username and password.
     *
     * @param string $username Username or email
     * @param string $password Password
     * @return User|null User value object on success, null on failure
     */
    public function authenticate(string $username, string $password): ?User;

    /**
     * Get the current authenticated user.
     *
     * @return User|null User value object or null if not authenticated
     */
    public function currentUser(): ?User;

    /**
     * Check if a user is authenticated.
     *
     * @return bool
     */
    public function isAuthenticated(): bool;

    /**
     * Check if the current user has a specific permission.
     *
     * @param string $permission Permission name (Core permission, not host-specific)
     * @return bool
     */
    public function hasPermission(string $permission): bool;

    /**
     * Check if the current user has a specific role.
     *
     * @param string $role Role name
     * @return bool
     */
    public function hasRole(string $role): bool;

    /**
     * Get all permissions for the current user.
     *
     * @return array<string> List of permission names
     */
    public function getPermissions(): array;

    /**
     * Get all roles for the current user.
     *
     * @return array<string> List of role names
     */
    public function getRoles(): array;

    /**
     * Verify a nonce for CSRF protection.
     *
     * @param string $nonce The nonce to verify
     * @param string $action The action name
     * @return bool True if nonce is valid, false otherwise
     */
    public function verifyNonce(string $nonce, string $action): bool;
}
