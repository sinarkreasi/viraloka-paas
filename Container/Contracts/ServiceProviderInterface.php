<?php

namespace Viraloka\Core\Container\Contracts;

/**
 * Service provider interface for registering and bootstrapping services.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings in the container.
     * Called immediately when provider is registered.
     *
     * @param ContainerInterface $container The container instance
     * @return void
     */
    public function register(ContainerInterface $container): void;

    /**
     * Bootstrap services after all providers are registered.
     * Called after all register() methods have been called.
     *
     * @param ContainerInterface $container The container instance
     * @return void
     */
    public function boot(ContainerInterface $container): void;
}
