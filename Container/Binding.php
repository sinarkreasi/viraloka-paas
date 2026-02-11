<?php

namespace Viraloka\Core\Container;

/**
 * Value object representing a service binding.
 */
class Binding
{
    /**
     * Create a new Binding instance.
     *
     * @param string $id The service identifier
     * @param callable|string $resolver The resolver (callable or class name)
     * @param BindingType $type The binding type
     * @param bool $lazy Whether the binding is lazy-loaded
     * @param array $tags Tags associated with this binding
     */
    public function __construct(
        public readonly string $id,
        public readonly mixed $resolver,
        public readonly BindingType $type = BindingType::FACTORY,
        public readonly bool $lazy = false,
        public readonly array $tags = [],
    ) {}
}
