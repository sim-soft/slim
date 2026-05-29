<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Handlers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Simsoft\Slim\Handlers\ShutdownHandler;
use Slim\Handlers\ErrorHandler;

class ShutdownHandlerTest extends TestCase
{
    #[Test]
    public function constructorAcceptsRequiredParameters(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $errorHandler = $this->createMock(ErrorHandler::class);

        $handler = new ShutdownHandler($request, $errorHandler, false);

        $this->assertTrue(method_exists($handler, '__invoke'));
    }

    #[Test]
    public function invokeDoesNothingWhenNoError(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $errorHandler = $this->createMock(ErrorHandler::class);

        // ErrorHandler should NOT be called when there's no error
        $errorHandler->expects($this->never())->method('__invoke');

        $handler = new ShutdownHandler($request, $errorHandler, false);

        // This should not throw or produce output since error_get_last() returns null in test
        $handler();

        // If we get here without exception, the test passes
        $this->assertTrue(true);
    }

    #[Test]
    public function isInvokable(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $errorHandler = $this->createMock(ErrorHandler::class);

        $handler = new ShutdownHandler($request, $errorHandler, true);

        $this->assertTrue(method_exists($handler, '__invoke'));
    }
}
