<?php

namespace Viraloka\Adapter\Testing;

use Viraloka\Core\Adapter\Contracts\StorageAdapterInterface;

/**
 * In-memory storage adapter for testing Core in isolation.
 * 
 * This adapter provides array-based storage without any external dependencies,
 * allowing Core to be tested without WordPress or other host environments.
 */
class InMemoryStorageAdapter implements StorageAdapterInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $storage = [];

    /**
     * @var array<string, int>
     */
    private array $expirations = [];

    /**
     * Get a value from storage.
     *
     * @param string $key Storage key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->cleanExpired();

        if (!array_key_exists($key, $this->storage)) {
            return $default;
        }

        return $this->storage[$key];
    }

    /**
     * Set a value in storage.
     *
     * @param string $key Storage key
     * @param mixed $value Value to store
     * @param int|null $ttl Time-to-live in seconds (null for permanent)
     * @return bool Success status
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->storage[$key] = $value;

        if ($ttl !== null) {
            $this->expirations[$key] = time() + $ttl;
        } else {
            unset($this->expirations[$key]);
        }

        return true;
    }

    /**
     * Delete a value from storage.
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        unset($this->storage[$key]);
        unset($this->expirations[$key]);
        return true;
    }

    /**
     * Check if a key exists in storage.
     *
     * @param string $key Storage key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->cleanExpired();
        return array_key_exists($key, $this->storage);
    }

    /**
     * Get multiple values from storage.
     *
     * @param array<string> $keys Storage keys
     * @param mixed $default Default value for missing keys
     * @return array<string, mixed> Key-value pairs
     */
    public function getMany(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * Set multiple values in storage.
     *
     * @param array<string, mixed> $values Key-value pairs
     * @param int|null $ttl Time-to-live in seconds
     * @return bool Success status
     */
    public function setMany(array $values, ?int $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Delete multiple values from storage.
     *
     * @param array<string> $keys Storage keys
     * @return bool Success status
     */
    public function deleteMany(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    /**
     * Clear all values from storage.
     *
     * @return bool Success status
     */
    public function clear(): bool
    {
        $this->storage = [];
        $this->expirations = [];
        return true;
    }

    /**
     * Get storage adapter capabilities.
     *
     * @return array<string, bool>
     */
    public function getCapabilities(): array
    {
        return [
            'persistent' => false,
            'transient' => false,
            'file_cache' => false,
        ];
    }

    /**
     * Clean expired entries from storage.
     */
    private function cleanExpired(): void
    {
        $now = time();
        foreach ($this->expirations as $key => $expiration) {
            if ($expiration <= $now) {
                unset($this->storage[$key]);
                unset($this->expirations[$key]);
            }
        }
    }
}

