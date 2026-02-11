<?php

namespace Viraloka\Adapter\WordPress;

use Viraloka\Core\Adapter\Contracts\StorageAdapterInterface;

/**
 * WordPress implementation of the Storage Adapter.
 * 
 * Uses WordPress options for permanent storage and transients for TTL storage.
 */
class WordPressStorageAdapter implements StorageAdapterInterface
{
    /**
     * Prefix for all storage keys to avoid conflicts.
     */
    private const PREFIX = 'viraloka_';

    /**
     * Get a value from storage.
     *
     * @param string $key Storage key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Try transient first (for TTL support)
        $value = get_transient(self::PREFIX . $key);
        if ($value !== false) {
            return $value;
        }

        // Fall back to options (permanent storage)
        $value = get_option(self::PREFIX . $key, null);
        return $value ?? $default;
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
        if ($ttl !== null) {
            return set_transient(self::PREFIX . $key, $value, $ttl);
        }
        return update_option(self::PREFIX . $key, $value);
    }

    /**
     * Delete a value from storage.
     *
     * @param string $key Storage key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        delete_transient(self::PREFIX . $key);
        return delete_option(self::PREFIX . $key);
    }

    /**
     * Check if a key exists in storage.
     *
     * @param string $key Storage key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->get($key, '__NOT_FOUND__') !== '__NOT_FOUND__';
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
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . self::PREFIX . '%'
            )
        );
        
        // Clear timeout transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . self::PREFIX . '%'
            )
        );
        
        // Clear options
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                self::PREFIX . '%'
            )
        );
        
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
            'persistent' => function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache(),
            'transient' => true,
            'file_cache' => false,
        ];
    }
}
