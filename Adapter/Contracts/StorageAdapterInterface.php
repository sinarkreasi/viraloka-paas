<?php

namespace Viraloka\Core\Adapter\Contracts;

/**
 * Storage adapter for key-value persistence.
 */
interface StorageAdapterInterface
{
    /**
     * Get a value from storage.
     *
     * @param string $key Storage key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a value in storage.
     *
     * @param string $key Storage key
     * @param mixed $value Value to store
     * @param int|null $ttl Time-to-live in seconds (null for permanent)
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete a value from storage.
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete(string $key): bool;

    /**
     * Check if a key exists in storage.
     *
     * @param string $key Storage key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get multiple values from storage.
     *
     * @param array<string> $keys Storage keys
     * @param mixed $default Default value for missing keys
     * @return array<string, mixed> Key-value pairs
     */
    public function getMany(array $keys, mixed $default = null): array;

    /**
     * Set multiple values in storage.
     *
     * @param array<string, mixed> $values Key-value pairs
     * @param int|null $ttl Time-to-live in seconds
     * @return bool Success status
     */
    public function setMany(array $values, ?int $ttl = null): bool;

    /**
     * Delete multiple values from storage.
     *
     * @param array<string> $keys Storage keys
     * @return bool Success status
     */
    public function deleteMany(array $keys): bool;

    /**
     * Clear all values from storage.
     *
     * @return bool Success status
     */
    public function clear(): bool;

    /**
     * Get storage adapter capabilities.
     *
     * @return array<string, bool> Capabilities (e.g., ['persistent' => true, 'transient' => false])
     */
    public function getCapabilities(): array;
}
