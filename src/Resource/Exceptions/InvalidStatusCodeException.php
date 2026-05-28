<?php

declare(strict_types=1);

namespace Simsoft\Resource\Exceptions;

/**
 * Exception thrown when an HTTP status code outside the valid range (100-599) is provided.
 */
class InvalidStatusCodeException extends \InvalidArgumentException
{
}
