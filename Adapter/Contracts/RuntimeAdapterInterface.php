<?php

namespace Viraloka\Core\Adapter\Contracts;

use Viraloka\Core\Adapter\Exceptions\RuntimeAdapterException;

/**
 * Runtime adapter for host environment lifecycle management.
 */
interface RuntimeAdapterInterface
{
    /**
     * Boot the host environment.
     *
     * @throws RuntimeAdapterException If boot fails
     */
    public function boot(): void;

    /**
     * Shutdown the host environment.
     */
    public function shutdown(): void;

    /**
     * Get the current environment name.
     *
     * @return string Environment name (e.g., "production", "development", "testing")
     */
    public function environment(): string;

    /**
     * Check if running in a specific environment.
     *
     * @param string $environment
     * @return bool
     */
    public function isEnvironment(string $environment): bool;

    /**
     * Check if the host is in admin/backend context.
     *
     * @return bool
     */
    public function isAdmin(): bool;

    /**
     * Check if the host is handling a CLI request.
     *
     * @return bool
     */
    public function isCli(): bool;

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugMode(): bool;
}
