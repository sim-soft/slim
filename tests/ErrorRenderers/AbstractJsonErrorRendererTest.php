<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\ErrorRenderers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Slim\ErrorRenderers\AbstractJsonErrorRenderer;
use Throwable;

class AbstractJsonErrorRendererTest extends TestCase
{
    private function createRenderer(): AbstractJsonErrorRenderer
    {
        return new class extends AbstractJsonErrorRenderer {
            public function formatExceptionFragment(Throwable $exception): array
            {
                return [
                    'error' => true,
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                ];
            }
        };
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsFalseRendersCustomJson(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Not found', 404);

        $result = $renderer($exception, false);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        $this->assertTrue($decoded['error']);
        $this->assertSame('Not found', $decoded['message']);
        $this->assertSame(404, $decoded['code']);
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsTrueDelegatesToSlimRenderer(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Detailed error');

        $result = $renderer($exception, true);

        $this->assertJson($result);
        $decoded = json_decode($result, true);
        // Slim's JsonErrorRenderer includes type, message, etc.
        $this->assertArrayHasKey('message', $decoded);
    }

    #[Test]
    public function outputUsesUnescapedSlashesAndPrettyPrint(): void
    {
        $renderer = new class extends AbstractJsonErrorRenderer {
            public function formatExceptionFragment(Throwable $exception): array
            {
                return ['url' => 'https://example.com/path'];
            }
        };

        $exception = new RuntimeException('test');
        $result = $renderer($exception, false);

        $this->assertStringContainsString('https://example.com/path', $result);
        $this->assertStringNotContainsString('\\/', $result);
        // Pretty print adds newlines
        $this->assertStringContainsString("\n", $result);
    }

    #[Test]
    public function formatExceptionFragmentReceivesException(): void
    {
        $renderer = new class extends AbstractJsonErrorRenderer {
            public ?Throwable $receivedException = null;

            public function formatExceptionFragment(Throwable $exception): array
            {
                $this->receivedException = $exception;
                return ['ok' => true];
            }
        };

        $exception = new RuntimeException('test', 500);
        $renderer($exception, false);

        $this->assertSame($exception, $renderer->receivedException);
    }

    #[Test]
    public function emptyArrayReturnsValidJson(): void
    {
        $renderer = new class extends AbstractJsonErrorRenderer {
            public function formatExceptionFragment(Throwable $exception): array
            {
                return [];
            }
        };

        $exception = new RuntimeException('test');
        $result = $renderer($exception, false);

        $this->assertSame('[]', $result);
    }
}
