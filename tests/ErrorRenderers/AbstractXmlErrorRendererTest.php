<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\ErrorRenderers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Simsoft\Slim\ErrorRenderers\AbstractXmlErrorRenderer;
use Throwable;

class AbstractXmlErrorRendererTest extends TestCase
{
    private function createRenderer(): AbstractXmlErrorRenderer
    {
        return new class extends AbstractXmlErrorRenderer {
            public function renderXmlBody(Throwable $exception): string
            {
                return '<error><message>' . $this->createCdataSection($exception->getMessage()) . '</message></error>';
            }
        };
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsFalseRendersCustomXml(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Something went wrong');

        $result = $renderer($exception, false);

        $this->assertStringStartsWith('<?xml version="1.0"', $result);
        $this->assertStringContainsString('<error>', $result);
        $this->assertStringContainsString('Something went wrong', $result);
    }

    #[Test]
    public function invokeWithDisplayErrorDetailsTrueDelegatesToSlimRenderer(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('Detailed error');

        $result = $renderer($exception, true);

        // Slim's XmlErrorRenderer includes detailed info
        $this->assertStringContainsString('<?xml', $result);
        $this->assertStringContainsString('Detailed error', $result);
    }

    #[Test]
    public function xmlDeclarationIsCorrect(): void
    {
        $renderer = $this->createRenderer();
        $exception = new RuntimeException('test');

        $result = $renderer($exception, false);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>', $result);
    }

    #[Test]
    public function createCdataSectionWrapsContent(): void
    {
        $renderer = new class extends AbstractXmlErrorRenderer {
            public function renderXmlBody(Throwable $exception): string
            {
                return '<msg>' . $this->createCdataSection('Hello World') . '</msg>';
            }

            public function publicCreateCdata(string $content): string
            {
                return $this->createCdataSection($content);
            }
        };

        $result = $renderer->publicCreateCdata('Hello World');
        $this->assertSame('<![CDATA[Hello World]]>', $result);
    }

    #[Test]
    public function createCdataSectionEscapesClosingTag(): void
    {
        $renderer = new class extends AbstractXmlErrorRenderer {
            public function renderXmlBody(Throwable $exception): string
            {
                return '';
            }

            public function publicCreateCdata(string $content): string
            {
                return $this->createCdataSection($content);
            }
        };

        $result = $renderer->publicCreateCdata('content with ]]> inside');
        $this->assertSame('<![CDATA[content with ]]]]><![CDATA[> inside]]>', $result);
    }

    #[Test]
    public function createCdataSectionWithEmptyString(): void
    {
        $renderer = new class extends AbstractXmlErrorRenderer {
            public function renderXmlBody(Throwable $exception): string
            {
                return '';
            }

            public function publicCreateCdata(string $content): string
            {
                return $this->createCdataSection($content);
            }
        };

        $result = $renderer->publicCreateCdata('');
        $this->assertSame('<![CDATA[]]>', $result);
    }

    #[Test]
    public function renderXmlBodyReceivesException(): void
    {
        $renderer = new class extends AbstractXmlErrorRenderer {
            public ?Throwable $receivedException = null;

            public function renderXmlBody(Throwable $exception): string
            {
                $this->receivedException = $exception;
                return '<error/>';
            }
        };

        $exception = new RuntimeException('test', 500);
        $renderer($exception, false);

        $this->assertSame($exception, $renderer->receivedException);
    }

    #[Test]
    public function createCdataSectionWithSpecialCharacters(): void
    {
        $renderer = new class extends AbstractXmlErrorRenderer {
            public function renderXmlBody(Throwable $exception): string
            {
                return '';
            }

            public function publicCreateCdata(string $content): string
            {
                return $this->createCdataSection($content);
            }
        };

        $result = $renderer->publicCreateCdata('<script>alert("xss")</script>');
        $this->assertSame('<![CDATA[<script>alert("xss")</script>]]>', $result);
    }
}
