<?php

namespace Viraloka\Adapter\Testing;

use Viraloka\Core\Adapter\Contracts\RuntimeAdapterInterface;

/**
 * Null runtime adapter for testing Core in isolation.
 * 
 * This adapter provides no-op implementations of all runtime methods,
 * allowing Core to be tested without WordPress or other host environments.
 * All methods return safe default values.
 */
class NullRuntimeAdapter implements RuntimeAdapterInterface
{
    private bool $booted = false;
    private string $environment;
    private bool $isAdmin;
    private bool $isCli;

    /**
     * Create a new NullRuntimeAdapter.
     *
     * @param string $environment Environment name (default: "testing")
     * @param bool $isAdmin Whether to simulate admin context (default: false)
     * @param bool $isCli Whether to simulate CLI context (default: false)
     */
    public function __construct(
        string $environment = 'testing',
        bool $isAdmin = false,
        bool $isCli = false
    ) {
        $this->environment = $environment;
        $this->isAdmin = $isAdmin;
        $this->isCli = $isCli;
    }

    /**
     * Boot the host environment (no-op).
     */
    public function boot(): void
    {
        $this->booted = true;
    }

    /**
     * Shutdown the host environment (no-op).
     */
    public function shutdown(): void
    {
        $this->booted = false;
    }

    /**
     * Get the current environment name.
     *
     * @return string Environment name
     */
    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * Check if running in a specific environment.
     *
     * @param string $environment
     * @return bool
     */
    public function isEnvironment(string $environment): bool
    {
        return $this->environment === $environment;
    }

    /**
     * Check if the host is in admin/backend context.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    /**
     * Check if the host is handling a CLI request.
     *
     * @return bool
     */
    public function isCli(): bool
    {
        return $this->isCli;
    }

    /**
     * Check if the adapter has been booted (for testing purposes).
     *
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Set the environment (for testing purposes).
     *
     * @param string $environment
     */
    public function setEnvironment(string $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Set admin context (for testing purposes).
     *
     * @param bool $isAdmin
     */
    public function setIsAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }

    /**
     * Set CLI context (for testing purposes).
     *
     * @param bool $isCli
     */
    public function setIsCli(bool $isCli): void
    {
        $this->isCli = $isCli;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->environment === 'development' || $this->environment === 'testing';
    }
}

