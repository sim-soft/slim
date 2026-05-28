<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\ErrorRenderers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Slim\ErrorRenderers\AbstractHtmlErrorRenderer;
use Throwable;

class AbstractHtmlErrorRendererTest extends TestCase
{
    private function createRenderer(): AbstractHtmlErrorRenderer
    {
        return new class extends AbstractHtmlErrorRenderer {
            public function renderHtmlBody(Throwable $exception): string
            {
                return '<html><body>' . $exception->getMessage() . '</body></html>';
            }
        };
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsFalseRendersCustomBody(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Something went wrong');

        $result = $renderer($exception, false);

        $this->assertStringContainsString('Something went wrong', $result);
        $this->assertStringContainsString('<html><body>', $result);
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsTrueDelegatesToSlimRenderer(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Detailed error');

        $result = $renderer($exception, true);

        // Slim's HtmlErrorRenderer produces detailed HTML with class names, traces, etc.
        $this->assertStringContainsString('Detailed error', $result);
        // Should contain more detail than our simple renderer
        $this->assertStringContainsString('RuntimeException', $result);
    }

    #[Test]
    public function renderHtmlBodyReceivesException(): void
    {
        $renderer = new class extends AbstractHtmlErrorRenderer {
            public ?Throwable $receivedException = null;

            public function renderHtmlBody(Throwable $exception): string
            {
                $this->receivedException = $exception;
                return 'rendered';
            }
        };

        $exception = new RuntimeException('test', 500);
        $renderer($exception, false);

        $this->assertSame($exception, $renderer->receivedException);
    }
}
