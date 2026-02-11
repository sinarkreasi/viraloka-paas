<?php

namespace Viraloka\Core\Container;

/**
 * Enum representing the type of service binding.
 */
enum BindingType: string
{
    case FACTORY = 'factory';
    case SINGLETON = 'singleton';
    case SCOPED = 'scoped';
}
