<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Handlers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Simsoft\Slim\Handlers\ShutdownHandler;
use Slim\Handlers\ErrorHandler;

class ShutdownHandlerTest extends TestCase
{
    /**
     * Run the fixture script in a subprocess and return its stdout.
     *
     * A real subprocess is required: shutdown functions, error_get_last() and
     * headers_sent() cannot be exercised faithfully in-process.
     */
    private function runScenario(string $scenario): string
    {
        $fixture = __DIR__ . '/fixtures/shutdown_scenario.php';

        $command = sprintf(
            '%s -d display_errors=0 -d error_reporting=32767 %s %s 2>%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($fixture),
            escapeshellarg($scenario),
            PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'
        );

        return (string)shell_exec($command);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonFatalScenarioProvider(): array
    {
        return [
            'no error' => ['clean'],
            'deprecation' => ['deprecation'],
            'notice' => ['notice'],
            'warning' => ['warning'],
            'undefined index' => ['undefined-index'],
        ];
    }

    #[Test]
    #[DataProvider('nonFatalScenarioProvider')]
    public function nonFatalErrorsLeaveTheResponseUntouched(string $scenario): void
    {
        $output = $this->runScenario($scenario);

        $this->assertStringContainsString('NORMAL RESPONSE BODY', $output);
        $this->assertStringNotContainsString(
            '500 Internal Server Error',
            $output,
            "Scenario '$scenario' appended an error page to a successful response."
        );
    }

    #[Test]
    public function fatalErrorStillProducesAnErrorPage(): void
    {
        $output = $this->runScenario('fatal');

        $this->assertStringContainsString('500 Internal Server Error', $output);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonFatalUnsentScenarioProvider(): array
    {
        return [
            'deprecation' => ['deprecation-unsent'],
            'notice' => ['notice-unsent'],
            'warning' => ['warning-unsent'],
            'undefined index' => ['undefined-index-unsent'],
        ];
    }

    #[Test]
    #[DataProvider('nonFatalUnsentScenarioProvider')]
    public function nonFatalErrorsProduceNoErrorPageEvenWhenHeadersAreUnsent(string $scenario): void
    {
        // Nothing was emitted before the error, so headers_sent() is false and
        // only the fatal-severity check can suppress the error page. Without
        // that check a bare notice would render a 500 over an empty response.
        $output = $this->runScenario($scenario);

        $this->assertStringNotContainsString(
            '500 Internal Server Error',
            $output,
            "Scenario '$scenario' rendered an error page for a non-fatal error."
        );
        $this->assertSame('', trim($output));
    }

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
