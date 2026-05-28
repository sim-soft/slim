<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Traits;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Simsoft\Slim\Traits\ContainerAwareTrait;

class ContainerAwareTraitTest extends TestCase
{
    #[Test]
    public function constructorAcceptsNullContainer(): void
    {
        $instance = new class (null) {
            use ContainerAwareTrait;
        };

        $this->assertNotNull($instance);
    }

    #[Test]
    public function constructorAcceptsContainerInterface(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $instance = new class ($container) {
            use ContainerAwareTrait;
        };

        $this->assertNotNull($instance);
    }

    #[Test]
    public function magicGetReturnsServiceFromContainer(): void
    {
        $logger = new \stdClass();
        $logger->name = 'test-logger';

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('logger')->willReturn(true);
        $container->method('get')->with('logger')->willReturn($logger);

        $instance = new class ($container) {
            use ContainerAwareTrait;
        };

        $this->assertSame($logger, $instance->logger);
    }

    #[Test]
    public function magicGetThrowsExceptionForMissingService(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->with('missing')->willReturn(false);

        $instance = new class ($container) {
            use ContainerAwareTrait;
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Service: 'missing' not found.");

        $instance->missing;
    }

    #[Test]
    public function magicGetThrowsExceptionWhenNoContainer(): void
    {
        $instance = new class (null) {
            use ContainerAwareTrait;
        };

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Service: 'anything' not found.");

        $instance->anything;
    }

    #[Test]
    public function magicGetWithDifferentServiceNames(): void
    {
        $db = new \stdClass();
        $cache = new \stdClass();

        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id) {
            return in_array($id, ['db', 'cache']);
        });
        $container->method('get')->willReturnCallback(function (string $id) use ($db, $cache) {
            return match ($id) {
                'db' => $db,
                'cache' => $cache,
                default => null,
            };
        });

        $instance = new class ($container) {
            use ContainerAwareTrait;
        };

        $this->assertSame($db, $instance->db);
        $this->assertSame($cache, $instance->cache);
    }
}
