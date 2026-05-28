<?php

declare(strict_types=1);

namespace Simsoft\Resource\Exceptions;

/**
 * Exception thrown when an invalid configuration value is provided,
 * such as an invalid wrapKey, type, or other resource configuration.
 */
class InvalidConfigurationException extends \InvalidArgumentException
{
}
