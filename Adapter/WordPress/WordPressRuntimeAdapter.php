<?php

namespace Viraloka\Adapter\WordPress;

use Viraloka\Core\Adapter\Contracts\RuntimeAdapterInterface;
use Viraloka\Core\Adapter\Exceptions\RuntimeAdapterException;

/**
 * WordPress implementation of the Runtime Adapter.
 * 
 * Handles WordPress-specific lifecycle management, environment detection,
 * and context checking (admin, CLI, etc.).
 */
class WordPressRuntimeAdapter implements RuntimeAdapterInterface
{
    private bool $booted = false;

    /**
     * Boot the WordPress environment.
     *
     * @throws RuntimeAdapterException If WordPress is not loaded
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        if (!function_exists('add_action')) {
            throw new RuntimeAdapterException('WordPress not loaded');
        }

        $this->booted = true;
    }

    /**
     * Shutdown the WordPress environment.
     */
    public function shutdown(): void
    {
        // WordPress handles its own shutdown
        $this->booted = false;
    }

    /**
     * Get the current environment name.
     *
     * @return string Environment name (e.g., "production", "development", "testing")
     */
    public function environment(): string
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return 'development';
        }
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }
        return 'production';
    }

    /**
     * Check if running in a specific environment.
     *
     * @param string $environment
     * @return bool
     */
    public function isEnvironment(string $environment): bool
    {
        return $this->environment() === $environment;
    }

    /**
     * Check if the host is in admin/backend context.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    /**
     * Check if the host is handling a CLI request.
     *
     * @return bool
     */
    public function isCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return (defined('WP_DEBUG') && WP_DEBUG) || 
               (defined('VIRALOKA_DEBUG') && VIRALOKA_DEBUG);
    }
}
