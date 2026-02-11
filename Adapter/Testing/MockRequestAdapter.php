<?php

namespace Viraloka\Adapter\Testing;

use Viraloka\Core\Adapter\Contracts\RequestAdapterInterface;

/**
 * Mock request adapter for testing Core in isolation.
 * 
 * This adapter provides configurable request data without any external dependencies,
 * allowing Core to be tested without WordPress or other host environments.
 */
class MockRequestAdapter implements RequestAdapterInterface
{
    private string $method = 'GET';
    private string $path = '/';
    private array $headers = [];
    private mixed $body = null;
    private string $rawBody = '';
    private array $query = [];
    private array $post = [];
    private string $url = 'http://localhost/';

    /**
     * Get the HTTP method.
     *
     * @return string HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the request path.
     *
     * @return string Request path without query string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get all request headers.
     *
     * @return array<string, string> Associative array of headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
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
        $name = strtoupper(str_replace('-', '_', $name));
        return $this->headers[$name] ?? $default;
    }

    /**
     * Get the request body.
     *
     * @return mixed Parsed body content
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Get the raw request body.
     *
     * @return string Raw body content
     */
    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * Get query parameters.
     *
     * @return array<string, mixed> Query parameters
     */
    public function getQuery(): array
    {
        return $this->query;
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
        return $this->query[$key] ?? $default;
    }

    /**
     * Get POST data.
     *
     * @return array<string, mixed> POST data
     */
    public function getPost(): array
    {
        return $this->post;
    }

    /**
     * Get the full URL.
     *
     * @return string Full URL including query string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    // Test helper methods

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function setBody(mixed $body): void
    {
        $this->body = $body;
    }

    public function setRawBody(string $rawBody): void
    {
        $this->rawBody = $rawBody;
    }

    public function setQuery(array $query): void
    {
        $this->query = $query;
    }

    public function setPost(array $post): void
    {
        $this->post = $post;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }
}
