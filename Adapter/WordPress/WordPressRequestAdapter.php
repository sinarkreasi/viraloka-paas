<?php

namespace Viraloka\Adapter\WordPress;

use Viraloka\Core\Adapter\Contracts\RequestAdapterInterface;

/**
 * WordPress implementation of the Request Adapter.
 * 
 * Wraps PHP superglobals ($_SERVER, $_GET, $_POST) to provide
 * a clean abstraction for HTTP request data.
 */
class WordPressRequestAdapter implements RequestAdapterInterface
{
    /**
     * Get the HTTP method.
     *
     * @return string HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Get the request path.
     *
     * @return string Request path without query string
     */
    public function getPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return $path ?: '/';
    }

    /**
     * Get all request headers.
     *
     * @return array<string, string> Associative array of headers
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get a specific header.
     *
     * @param string $name Header name (case-insensitive)
     * @param string|null $default Default value if header not found
     * @return string|null
     */
    public function getHeader(string $name, ?string $default = null): ?string
    {
        $headers = $this->getHeaders();
        $name = strtoupper(str_replace('-', '_', $name));
        return $headers[$name] ?? $default;
    }

    /**
     * Get the request body.
     *
     * @return mixed Parsed body content
     */
    public function getBody(): mixed
    {
        $contentType = $this->getHeader('Content-Type', '');
        $raw = $this->getRawBody();

        if (str_contains($contentType, 'application/json')) {
            return json_decode($raw, true);
        }

        return $_POST;
    }

    /**
     * Get the raw request body.
     *
     * @return string Raw body content
     */
    public function getRawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Get query parameters.
     *
     * @return array<string, mixed> Query parameters
     */
    public function getQuery(): array
    {
        return $_GET;
    }

    /**
     * Get a specific query parameter.
     *
     * @param string $key Parameter name
     * @param mixed $default Default value
     * @return mixed
     */
    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Get POST data.
     *
     * @return array<string, mixed> POST data
     */
    public function getPost(): array
    {
        return $_POST;
    }

    /**
     * Get the full URL.
     *
     * @return string Full URL including query string
     */
    public function getUrl(): string
    {
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return "{$scheme}://{$host}{$uri}";
    }
}
