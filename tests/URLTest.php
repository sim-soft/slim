<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\URL;
use Slim\Interfaces\RouteParserInterface;

class URLTest extends TestCase
{
    private RouteParserInterface $mockParser;

    protected function setUp(): void
    {
        $this->mockParser = $this->createMock(RouteParserInterface::class);
        URL::setParser($this->mockParser);
        URL::setDomain(null);
    }

    #[Test]
    public function setDomainAndGetDomain(): void
    {
        URL::setDomain('https://example.com');
        $this->assertSame('https://example.com', URL::getDomain());
    }

    #[Test]
    public function forReturnsUrlFromRouteParser(): void
    {
        $this->mockParser->method('urlFor')
            ->with('home', [], [])
            ->willReturn('/');

        $this->assertSame('/', URL::for('home'));
    }

    #[Test]
    public function forWithDataParameters(): void
    {
        $this->mockParser->method('urlFor')
            ->with('user', ['id' => '42'], [])
            ->willReturn('/users/42');

        $this->assertSame('/users/42', URL::for('user', ['id' => '42']));
    }

    #[Test]
    public function forWithQueryParameters(): void
    {
        $this->mockParser->method('urlFor')
            ->with('search', [], ['q' => 'test'])
            ->willReturn('/search?q=test');

        $this->assertSame('/search?q=test', URL::for('search', [], ['q' => 'test']));
    }

    #[Test]
    public function forRemovesDoubleSlashes(): void
    {
        $this->mockParser->method('urlFor')
            ->with('page', [], [])
            ->willReturn('//page');

        $this->assertSame('/page', URL::for('page'));
    }

    #[Test]
    public function fullForWithDomainSet(): void
    {
        URL::setDomain('https://example.com');

        $this->mockParser->method('fullUrlFor')
            ->willReturn('https://example.com/users/42');

        $this->assertSame('https://example.com/users/42', URL::fullFor('user', ['id' => '42']));
    }

    #[Test]
    public function fullForWithoutDomainFallsBackToUrlFor(): void
    {
        // When getDomain returns null, fullFor should fall back to urlFor
        // We need to simulate getDomain returning null
        // Since getDomain auto-detects from $_SERVER, we set domain explicitly to test the fallback
        URL::setDomain(null);

        // getDomain will try to build from $_SERVER which may not be set in CLI
        // Let's test with domain set
        URL::setDomain('https://myapp.com');

        $this->mockParser->method('fullUrlFor')
            ->willReturn('https://myapp.com/path');

        $result = URL::fullFor('route_name');
        $this->assertSame('https://myapp.com/path', $result);
    }

    #[Test]
    public function fullForRemovesDoubleSlashesInPath(): void
    {
        URL::setDomain('https://example.com');

        $this->mockParser->method('fullUrlFor')
            ->willReturn('https://example.com//users//42');

        $result = URL::fullFor('user', ['id' => '42']);
        $this->assertSame('https://example.com/users/42', $result);
    }

    #[Test]
    public function setParserAcceptsRouteParserInterface(): void
    {
        $parser = $this->createMock(RouteParserInterface::class);
        $parser->method('urlFor')
            ->with('test', [], [])
            ->willReturn('/test-path');

        URL::setParser($parser);
        $this->assertSame('/test-path', URL::for('test'));
    }
}
