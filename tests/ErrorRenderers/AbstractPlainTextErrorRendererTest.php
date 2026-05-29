<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\ErrorRenderers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Slim\ErrorRenderers\AbstractPlainTextErrorRenderer;
use Throwable;

class AbstractPlainTextErrorRendererTest extends TestCase
{
    private function createRenderer(): AbstractPlainTextErrorRenderer
    {
        return new class extends AbstractPlainTextErrorRenderer {
            public function formatExceptionFragment(Throwable $exception): string
            {
                return 'Error: ' . $exception->getMessage();
            }
        };
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsFalseRendersCustomText(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Something broke');

        $result = $renderer($exception, false);

        $this->assertSame('Error: Something broke', $result);
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsTrueDelegatesToSlimRenderer(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Detailed error');

        $result = $renderer($exception, true);

        // Slim's PlainTextErrorRenderer includes class name and trace
        $this->assertStringContainsString('RuntimeException', $result);
        $this->assertStringContainsString('Detailed error', $result);
    }

    #[Test]
    public function formatExceptionFragmentReceivesException(): void
    {
        $renderer = new class extends AbstractPlainTextErrorRenderer {
            public ?Throwable $receivedException = null;

            public function formatExceptionFragment(Throwable $exception): string
            {
                $this->receivedException = $exception;
                return 'handled';
            }
        };

        $exception = new RuntimeException('test', 500);
        $renderer($exception, false);

        $this->assertSame($exception, $renderer->receivedException);
    }

    #[Test]
    public function emptyStringReturnedFromFragment(): void
    {
        $renderer = new class extends AbstractPlainTextErrorRenderer {
            public function formatExceptionFragment(Throwable $exception): string
            {
                return '';
            }
        };

        $exception = new RuntimeException('test');
        $result = $renderer($exception, false);

        $this->assertSame('', $result);
    }
}
