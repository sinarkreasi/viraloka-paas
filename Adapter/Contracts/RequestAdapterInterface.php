<?php

namespace Viraloka\Core\Adapter\Contracts;

/**
 * Request adapter for abstracting HTTP request data.
 */
interface RequestAdapterInterface
{
    /**
     * Get the HTTP method.
     *
     * @return string HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function getMethod(): string;

    /**
     * Get the request path.
     *
     * @return string Request path without query string
     */
    public function getPath(): string;

    /**
     * Get all request headers.
     *
     * @return array<string, string> Associative array of headers
     */
    public function getHeaders(): array;

    /**
     * Get a specific header.
     *
     * @param string $name Header name (case-insensitive)
     * @param string|null $default Default value if header not found
     * @return string|null
     */
    public function getHeader(string $name, ?string $default = null): ?string;

    /**
     * Get the request body.
     *
     * @return mixed Parsed body content
     */
    public function getBody(): mixed;

    /**
     * Get the raw request body.
     *
     * @return string Raw body content
     */
    public function getRawBody(): string;

    /**
     * Get query parameters.
     *
     * @return array<string, mixed> Query parameters
     */
    public function getQuery(): array;

    /**
     * Get a specific query parameter.
     *
     * @param string $key Parameter name
     * @param mixed $default Default value
     * @return mixed
     */
    public function getQueryParam(string $key, mixed $default = null): mixed;

    /**
     * Get POST data.
     *
     * @return array<string, mixed> POST data
     */
    public function getPost(): array;

    /**
     * Get the full URL.
     *
     * @return string Full URL including query string
     */
    public function getUrl(): string;
}
