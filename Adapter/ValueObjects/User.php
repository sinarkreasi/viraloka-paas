<?php

namespace Viraloka\Core\Adapter\ValueObjects;

/**
 * User value object - Core's representation of a user.
 * Adapters translate host-specific user objects to this.
 */
final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $displayName,
        public readonly array $roles = [],
        public readonly array $meta = []
    ) {}

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
