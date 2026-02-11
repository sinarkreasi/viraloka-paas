<?php

namespace Viraloka\Core\Adapter\Contracts;

use Viraloka\Core\Adapter\Exceptions\ResponseAdapterException;

/**
 * Response adapter for sending responses to the client.
 */
interface ResponseAdapterInterface
{
    /**
     * Send a response.
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @throws ResponseAdapterException If status code is invalid
     */
    public function send(mixed $data, int $status = 200, array $headers = []): void;

    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public function json(mixed $data, int $status = 200, array $headers = []): void;

    /**
     * Send an HTML response.
     *
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @param array<string, string> $headers Additional headers
     */
    public function html(string $html, int $status = 200, array $headers = []): void;

    /**
     * Send a redirect response.
     *
     * @param string $url Redirect URL
     * @param int $status HTTP status code (301, 302, 303, 307, 308)
     */
    public function redirect(string $url, int $status = 302): void;

    /**
     * Set a response header.
     *
     * @param string $name Header name
     * @param string $value Header value
     */
    public function setHeader(string $name, string $value): void;

    /**
     * Sanitize HTML content for safe output.
     *
     * @param string $html HTML content to sanitize
     * @return string Sanitized HTML
     */
    public function sanitizeHtml(string $html): string;
}
