<?php

namespace Viraloka\Core\Adapter\Contracts;

/**
 * Central registry interface for all host adapters.
 * 
 * Provides access to all adapter implementations and supports
 * adapter registration for dependency injection and testing.
 */
interface AdapterRegistryInterface
{
    /**
     * Get the runtime adapter.
     *
     * @return RuntimeAdapterInterface
     * @throws \Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException
     */
    public function runtime(): RuntimeAdapterInterface;

    /**
     * Get the request adapter.
     *
     * @return RequestAdapterInterface
     * @throws \Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException
     */
    public function request(): RequestAdapterInterface;

    /**
     * Get the response adapter.
     *
     * @return ResponseAdapterInterface
     * @throws \Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException
     */
    public function response(): ResponseAdapterInterface;

    /**
     * Get the auth adapter.
     *
     * @return AuthAdapterInterface
     * @throws \Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException
     */
    public function auth(): AuthAdapterInterface;

    /**
     * Get the storage adapter.
     *
     * @return StorageAdapterInterface
     * @throws \Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException
     */
    public function storage(): StorageAdapterInterface;

    /**
     * Get the event adapter.
     *
     * @return EventAdapterInterface
     * @throws \Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException
     */
    public function event(): EventAdapterInterface;

    /**
     * Register a runtime adapter.
     *
     * @param RuntimeAdapterInterface $adapter
     * @return void
     */
    public function registerRuntime(RuntimeAdapterInterface $adapter): void;

    /**
     * Register a request adapter.
     *
     * @param RequestAdapterInterface $adapter
     * @return void
     */
    public function registerRequest(RequestAdapterInterface $adapter): void;

    /**
     * Register a response adapter.
     *
     * @param ResponseAdapterInterface $adapter
     * @return void
     */
    public function registerResponse(ResponseAdapterInterface $adapter): void;

    /**
     * Register an auth adapter.
     *
     * @param AuthAdapterInterface $adapter
     * @return void
     */
    public function registerAuth(AuthAdapterInterface $adapter): void;

    /**
     * Register a storage adapter.
     *
     * @param StorageAdapterInterface $adapter
     * @return void
     */
    public function registerStorage(StorageAdapterInterface $adapter): void;

    /**
     * Register an event adapter.
     *
     * @param EventAdapterInterface $adapter
     * @return void
     */
    public function registerEvent(EventAdapterInterface $adapter): void;
}
