<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource\Exceptions;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Exceptions\InvalidConfigurationException;
use Simsoft\Resource\Exceptions\InvalidHeaderException;
use Simsoft\Resource\Exceptions\InvalidResourceException;
use Simsoft\Resource\Exceptions\InvalidStatusCodeException;
use Simsoft\Resource\Exceptions\SerializationException;

/**
 * Unit tests for custom exception classes.
 */
class ExceptionsTest extends TestCase
{
    #[Test]
    public function invalidResourceExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidResourceException('Invalid class');
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Invalid class', $exception->getMessage());
    }

    #[Test]
    public function invalidHeaderExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidHeaderException('Header name must not be empty');
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Header name must not be empty', $exception->getMessage());
    }

    #[Test]
    public function invalidConfigurationExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidConfigurationException("Invalid wrap key: 'meta'");
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame("Invalid wrap key: 'meta'", $exception->getMessage());
    }

    #[Test]
    public function serializationExceptionExtendsRuntimeException(): void
    {
        $exception = new SerializationException('Failed to serialize resource to JSON');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Failed to serialize resource to JSON', $exception->getMessage());
    }

    #[Test]
    public function invalidStatusCodeExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidStatusCodeException('Invalid HTTP status code: 999');
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertSame('Invalid HTTP status code: 999', $exception->getMessage());
    }
}
