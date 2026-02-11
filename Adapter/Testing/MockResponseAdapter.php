<?php

namespace Viraloka\Adapter\Testing;

use Viraloka\Core\Adapter\Contracts\ResponseAdapterInterface;
use Viraloka\Core\Adapter\Exceptions\ResponseAdapterException;

/**
 * Mock response adapter for testing Core in isolation.
 * 
 * This adapter captures response data without actually sending it,
 * allowing Core to be tested without WordPress or other host environments.
 */
class MockResponseAdapter implements ResponseAdapterInterface
{
    private array $sentResponses = [];
    private array $headers = [];

    /**
     * Send a response.
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @throws ResponseAdapterException If status code is invalid
     */
    public function send(mixed $data, int $status = 200, array $headers = []): void
    {
        $this->validateStatusCode($status);

        $this->sentResponses[] = [
            'type' => 'send',
            'data' => $data,
            'status' => $status,
            'headers' => array_merge($this->headers, $headers),
        ];
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public function json(mixed $data, int $status = 200, array $headers = []): void
    {
        $this->validateStatusCode($status);

        $headers['Content-Type'] = 'application/json';

        $this->sentResponses[] = [
            'type' => 'json',
            'data' => $data,
            'status' => $status,
            'headers' => array_merge($this->headers, $headers),
        ];
    }

    /**
     * Send an HTML response.
     *
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public function html(string $html, int $status = 200, array $headers = []): void
    {
        $this->validateStatusCode($status);

        $headers['Content-Type'] = 'text/html';

        $this->sentResponses[] = [
            'type' => 'html',
            'data' => $html,
            'status' => $status,
            'headers' => array_merge($this->headers, $headers),
        ];
    }

    /**
     * Send a redirect response.
     *
     * @param string $url Redirect URL
     * @param int $status HTTP status code (301, 302, 303, 307, 308)
     */
    public function redirect(string $url, int $status = 302): void
    {
        $validRedirectStatuses = [301, 302, 303, 307, 308];
        
        if (!in_array($status, $validRedirectStatuses, true)) {
            throw new ResponseAdapterException(
                "Invalid redirect status code: {$status}. Must be one of: " . implode(', ', $validRedirectStatuses)
            );
        }

        $this->sentResponses[] = [
            'type' => 'redirect',
            'url' => $url,
            'status' => $status,
            'headers' => array_merge($this->headers, ['Location' => $url]),
        ];
    }

    /**
     * Set a response header.
     *
     * @param string $name Header name
     * @param string $value Header value
     */
    public function setHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    /**
     * Validate HTTP status code.
     *
     * @param int $status
     * @throws ResponseAdapterException
     */
    private function validateStatusCode(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new ResponseAdapterException(
                "Invalid HTTP status code: {$status}. Must be between 100 and 599."
            );
        }
    }

    // Test helper methods

    /**
     * Get all sent responses (for testing purposes).
     *
     * @return array
     */
    public function getSentResponses(): array
    {
        return $this->sentResponses;
    }

    /**
     * Get the last sent response (for testing purposes).
     *
     * @return array|null
     */
    public function getLastResponse(): ?array
    {
        return end($this->sentResponses) ?: null;
    }

    /**
     * Clear all sent responses (for testing purposes).
     */
    public function clearResponses(): void
    {
        $this->sentResponses = [];
        $this->headers = [];
    }

    /**
     * Check if any responses were sent (for testing purposes).
     *
     * @return bool
     */
    public function hasResponses(): bool
    {
        return !empty($this->sentResponses);
    }

    /**
     * Sanitize HTML content for safe output.
     *
     * @param string $html HTML content to sanitize
     * @return string Sanitized HTML
     */
    public function sanitizeHtml(string $html): string
    {
        // For testing: strip all tags except safe ones
        return strip_tags($html, '<p><a><strong><em><ul><ol><li><br><span><div>');
    }
}
