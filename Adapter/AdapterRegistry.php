<?php

namespace Viraloka\Core\Adapter;

use Viraloka\Core\Adapter\Contracts\AdapterRegistryInterface;
use Viraloka\Core\Adapter\Contracts\RuntimeAdapterInterface;
use Viraloka\Core\Adapter\Contracts\RequestAdapterInterface;
use Viraloka\Core\Adapter\Contracts\ResponseAdapterInterface;
use Viraloka\Core\Adapter\Contracts\AuthAdapterInterface;
use Viraloka\Core\Adapter\Contracts\StorageAdapterInterface;
use Viraloka\Core\Adapter\Contracts\EventAdapterInterface;
use Viraloka\Core\Adapter\Contracts\PaymentAdapterInterface;
use Viraloka\Core\Adapter\Exceptions\AdapterNotRegisteredException;

/**
 * Central registry for all host adapters.
 * 
 * Provides centralized access to adapter implementations and ensures
 * that Core services can access host functionality through a single entry point.
 */
class AdapterRegistry implements AdapterRegistryInterface
{
    private ?RuntimeAdapterInterface $runtime = null;
    private ?RequestAdapterInterface $request = null;
    private ?ResponseAdapterInterface $response = null;
    private ?AuthAdapterInterface $auth = null;
    private ?StorageAdapterInterface $storage = null;
    private ?EventAdapterInterface $event = null;
    
    /**
     * @var array<string, PaymentAdapterInterface>
     */
    private array $paymentAdapters = [];

    /**
     * Get the runtime adapter.
     *
     * @return RuntimeAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function runtime(): RuntimeAdapterInterface
    {
        if ($this->runtime === null) {
            throw new AdapterNotRegisteredException('runtime');
        }
        return $this->runtime;
    }

    /**
     * Get the request adapter.
     *
     * @return RequestAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function request(): RequestAdapterInterface
    {
        if ($this->request === null) {
            throw new AdapterNotRegisteredException('request');
        }
        return $this->request;
    }

    /**
     * Get the response adapter.
     *
     * @return ResponseAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function response(): ResponseAdapterInterface
    {
        if ($this->response === null) {
            throw new AdapterNotRegisteredException('response');
        }
        return $this->response;
    }

    /**
     * Get the auth adapter.
     *
     * @return AuthAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function auth(): AuthAdapterInterface
    {
        if ($this->auth === null) {
            throw new AdapterNotRegisteredException('auth');
        }
        return $this->auth;
    }

    /**
     * Get the storage adapter.
     *
     * @return StorageAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function storage(): StorageAdapterInterface
    {
        if ($this->storage === null) {
            throw new AdapterNotRegisteredException('storage');
        }
        return $this->storage;
    }

    /**
     * Get the event adapter.
     *
     * @return EventAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function event(): EventAdapterInterface
    {
        if ($this->event === null) {
            throw new AdapterNotRegisteredException('event');
        }
        return $this->event;
    }

    /**
     * Register a runtime adapter.
     *
     * @param RuntimeAdapterInterface $adapter
     * @return void
     */
    public function registerRuntime(RuntimeAdapterInterface $adapter): void
    {
        $this->runtime = $adapter;
    }

    /**
     * Register a request adapter.
     *
     * @param RequestAdapterInterface $adapter
     * @return void
     */
    public function registerRequest(RequestAdapterInterface $adapter): void
    {
        $this->request = $adapter;
    }

    /**
     * Register a response adapter.
     *
     * @param ResponseAdapterInterface $adapter
     * @return void
     */
    public function registerResponse(ResponseAdapterInterface $adapter): void
    {
        $this->response = $adapter;
    }

    /**
     * Register an auth adapter.
     *
     * @param AuthAdapterInterface $adapter
     * @return void
     */
    public function registerAuth(AuthAdapterInterface $adapter): void
    {
        $this->auth = $adapter;
    }

    /**
     * Register a storage adapter.
     *
     * @param StorageAdapterInterface $adapter
     * @return void
     */
    public function registerStorage(StorageAdapterInterface $adapter): void
    {
        $this->storage = $adapter;
    }

    /**
     * Register an event adapter.
     *
     * @param EventAdapterInterface $adapter
     * @return void
     */
    public function registerEvent(EventAdapterInterface $adapter): void
    {
        $this->event = $adapter;
    }
    
    /**
     * Register a payment adapter.
     *
     * @param PaymentAdapterInterface $adapter
     * @return void
     */
    public function registerPayment(PaymentAdapterInterface $adapter): void
    {
        $this->paymentAdapters[$adapter->getName()] = $adapter;
    }
    
    /**
     * Get a payment adapter by name.
     *
     * @param string $name Payment gateway name
     * @return PaymentAdapterInterface
     * @throws AdapterNotRegisteredException
     */
    public function payment(string $name): PaymentAdapterInterface
    {
        if (!isset($this->paymentAdapters[$name])) {
            throw new AdapterNotRegisteredException("payment:{$name}");
        }
        return $this->paymentAdapters[$name];
    }
    
    /**
     * Get all registered payment adapters.
     *
     * @return array<string, PaymentAdapterInterface>
     */
    public function getAllPaymentAdapters(): array
    {
        return $this->paymentAdapters;
    }
    
    /**
     * Check if a payment adapter is registered.
     *
     * @param string $name Payment gateway name
     * @return bool
     */
    public function hasPaymentAdapter(string $name): bool
    {
        return isset($this->paymentAdapters[$name]);
    }
}
