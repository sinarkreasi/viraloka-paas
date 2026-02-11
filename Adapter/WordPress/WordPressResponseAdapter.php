<?php

namespace Viraloka\Adapter\WordPress;

use Viraloka\Core\Adapter\Contracts\ResponseAdapterInterface;
use Viraloka\Core\Adapter\Exceptions\ResponseAdapterException;

/**
 * WordPress implementation of the Response Adapter.
 * 
 * Uses WordPress functions like wp_send_json() for sending responses
 * and validates HTTP status codes.
 */
class WordPressResponseAdapter implements ResponseAdapterInterface
{
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

        // Set headers
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        // Set status code
        if (function_exists('status_header')) {
            status_header($status);
        } else {
            http_response_code($status);
        }

        // Send data
        if (is_array($data) || is_object($data)) {
            $this->json($data, $status, $headers);
        } else {
            echo $data;
        }
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

        // Set headers
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        // Use WordPress function if available
        if (function_exists('wp_send_json')) {
            wp_send_json($data, $status);
        } else {
            // Fallback to native PHP
            http_response_code($status);
            header('Content-Type: application/json');
            echo json_encode($data);
        }
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

        // Set headers
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        // Set status code
        if (function_exists('status_header')) {
            status_header($status);
        } else {
            http_response_code($status);
        }

        // Set content type
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');

        // Send HTML
        echo $html;
    }

    /**
     * Send a redirect response.
     *
     * @param string $url Redirect URL
     * @param int $status HTTP status code (301, 302, 303, 307, 308)
     */
    public function redirect(string $url, int $status = 302): void
    {
        $this->validateStatusCode($status);

        // Validate redirect status codes
        $validRedirectCodes = [301, 302, 303, 307, 308];
        if (!in_array($status, $validRedirectCodes, true)) {
            throw new ResponseAdapterException("Invalid redirect status code: {$status}");
        }

        // Use WordPress function if available
        if (function_exists('wp_redirect')) {
            wp_redirect($url, $status);
            exit;
        } else {
            // Fallback to native PHP
            http_response_code($status);
            header("Location: {$url}");
            exit;
        }
    }

    /**
     * Set a response header.
     *
     * @param string $name Header name
     * @param string $value Header value
     */
    public function setHeader(string $name, string $value): void
    {
        header("{$name}: {$value}");
    }

    /**
     * Sanitize HTML content for safe output.
     *
     * @param string $html HTML content to sanitize
     * @return string Sanitized HTML
     */
    public function sanitizeHtml(string $html): string
    {
        if (function_exists('wp_kses_post')) {
            return wp_kses_post($html);
        }
        
        // Fallback: strip all tags except safe ones
        return strip_tags($html, '<p><a><strong><em><ul><ol><li><br><span><div>');
    }

    /**
     * Validate HTTP status code.
     *
     * @param int $status Status code to validate
     * @throws ResponseAdapterException If status code is invalid
     */
    private function validateStatusCode(int $status): void
    {
        if ($status < 100 || $status > 599) {
            throw new ResponseAdapterException("Invalid HTTP status code: {$status}");
        }
    }
}
