<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Request;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;

class RequestQueryInputFilesTest extends TestCase
{
    protected function setUp(): void
    {
        Request::$request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/test?page=2&limit=10&search=hello');
        Request::$request = Request::$request->withQueryParams(['page' => '2', 'limit' => '10', 'search' => 'hello']);
        Request::setSanitizer(null);
    }

    protected function tearDown(): void
    {
        Request::setSanitizer(null);
    }

    // --- query() ---

    #[Test]
    public function queryReturnsAllParams(): void
    {
        $result = Request::getInstance()->query();
        $this->assertSame(['page' => '2', 'limit' => '10', 'search' => 'hello'], $result);
    }

    #[Test]
    public function queryReturnsSingleParam(): void
    {
        $this->assertSame('2', Request::getInstance()->query('page'));
    }

    #[Test]
    public function queryReturnsNullForMissingKey(): void
    {
        $this->assertNull(Request::getInstance()->query('missing'));
    }

    #[Test]
    public function queryReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('1', Request::getInstance()->query('offset', '1'));
    }

    #[Test]
    public function queryReturnsMultipleParams(): void
    {
        $result = Request::getInstance()->query(['page', 'limit']);
        $this->assertSame(['page' => '2', 'limit' => '10'], $result);
    }

    #[Test]
    public function queryReturnsDefaultForMissingKeysInArray(): void
    {
        $result = Request::getInstance()->query(['page', 'missing'], 'default');
        $this->assertSame(['page' => '2', 'missing' => 'default'], $result);
    }

    // --- input() ---

    #[Test]
    public function inputReturnsAllParams(): void
    {
        Request::$request = Request::$request->withParsedBody(['name' => 'John', 'email' => 'john@example.com']);
        $result = Request::getInstance()->input();
        $this->assertSame(['name' => 'John', 'email' => 'john@example.com'], $result);
    }

    #[Test]
    public function inputReturnsSingleParam(): void
    {
        Request::$request = Request::$request->withParsedBody(['name' => 'John', 'email' => 'john@example.com']);
        $this->assertSame('John', Request::getInstance()->input('name'));
    }

    #[Test]
    public function inputReturnsNullForMissingKey(): void
    {
        Request::$request = Request::$request->withParsedBody(['name' => 'John']);
        $this->assertNull(Request::getInstance()->input('missing'));
    }

    #[Test]
    public function inputReturnsDefaultForMissingKey(): void
    {
        Request::$request = Request::$request->withParsedBody(['name' => 'John']);
        $this->assertSame('user', Request::getInstance()->input('role', 'user'));
    }

    #[Test]
    public function inputReturnsMultipleParams(): void
    {
        Request::$request = Request::$request->withParsedBody(['name' => 'John', 'email' => 'j@e.com', 'age' => '30']);
        $result = Request::getInstance()->input(['name', 'email']);
        $this->assertSame(['name' => 'John', 'email' => 'j@e.com'], $result);
    }

    #[Test]
    public function inputHandlesNullBody(): void
    {
        Request::$request = Request::$request->withParsedBody(null);
        $result = Request::getInstance()->input();
        $this->assertSame([], $result);
    }

    #[Test]
    public function inputHandlesObjectBody(): void
    {
        $body = (object)['name' => 'John', 'email' => 'j@e.com'];
        Request::$request = Request::$request->withParsedBody($body);
        $this->assertSame('John', Request::getInstance()->input('name'));
    }

    // --- files() ---

    #[Test]
    public function filesReturnsAllFiles(): void
    {
        $stream = (new StreamFactory())->createStream('file content');
        $file = new UploadedFile($stream, 'test.txt', 'text/plain', 12);
        Request::$request = Request::$request->withUploadedFiles(['doc' => $file]);

        $result = Request::getInstance()->files();
        $this->assertArrayHasKey('doc', $result);
    }

    #[Test]
    public function filesReturnsSingleFile(): void
    {
        $stream = (new StreamFactory())->createStream('file content');
        $file = new UploadedFile($stream, 'test.txt', 'text/plain', 12);
        Request::$request = Request::$request->withUploadedFiles(['avatar' => $file]);

        $result = Request::getInstance()->files('avatar');
        $this->assertSame($file, $result);
    }

    #[Test]
    public function filesReturnsNullForMissingKey(): void
    {
        Request::$request = Request::$request->withUploadedFiles([]);
        $this->assertNull(Request::getInstance()->files('missing'));
    }

    #[Test]
    public function filesReturnsMultipleFiles(): void
    {
        $stream1 = (new StreamFactory())->createStream('a');
        $stream2 = (new StreamFactory())->createStream('b');
        $file1 = new UploadedFile($stream1, 'a.txt', 'text/plain', 1);
        $file2 = new UploadedFile($stream2, 'b.txt', 'text/plain', 1);
        Request::$request = Request::$request->withUploadedFiles(['a' => $file1, 'b' => $file2, 'c' => $file1]);

        $result = Request::getInstance()->files(['a', 'b']);
        $this->assertCount(2, $result);
        $this->assertSame($file1, $result['a']);
        $this->assertSame($file2, $result['b']);
    }

    // --- setSanitizer() ---

    #[Test]
    public function sanitizerAppliedToQueryAll(): void
    {
        Request::setSanitizer(fn($value, $key) => is_string($value) ? strtoupper($value) : $value);

        $result = Request::getInstance()->query();
        $this->assertSame(['page' => '2', 'limit' => '10', 'search' => 'HELLO'], $result);
    }

    #[Test]
    public function sanitizerAppliedToQuerySingle(): void
    {
        Request::setSanitizer(fn($value, $key) => trim((string)$value) . '_sanitized');

        $this->assertSame('2_sanitized', Request::getInstance()->query('page'));
    }

    #[Test]
    public function sanitizerAppliedToQueryMultiple(): void
    {
        Request::setSanitizer(fn($value, $key) => (int)$value);

        $result = Request::getInstance()->query(['page', 'limit']);
        $this->assertSame(['page' => 2, 'limit' => 10], $result);
    }

    #[Test]
    public function sanitizerAppliedToInput(): void
    {
        Request::$request = Request::$request->withParsedBody(['name' => '  John  ', 'email' => '  j@e.com  ']);
        Request::setSanitizer(fn($value, $key) => is_string($value) ? trim($value) : $value);

        $this->assertSame('John', Request::getInstance()->input('name'));
        $this->assertSame('j@e.com', Request::getInstance()->input('email'));
    }

    #[Test]
    public function sanitizerReceivesKeyName(): void
    {
        $receivedKeys = [];
        Request::setSanitizer(function ($value, $key) use (&$receivedKeys) {
            $receivedKeys[] = $key;
            return $value;
        });

        Request::getInstance()->query(['page', 'limit']);
        $this->assertSame(['page', 'limit'], $receivedKeys);
    }

    #[Test]
    public function sanitizerCanBeDisabled(): void
    {
        Request::setSanitizer(fn($value, $key) => 'SANITIZED');
        Request::setSanitizer(null);

        $this->assertSame('2', Request::getInstance()->query('page'));
    }

    #[Test]
    public function getSanitizerReturnsCurrentSanitizer(): void
    {
        $this->assertNull(Request::getSanitizer());

        $fn = fn($v, $k) => $v;
        Request::setSanitizer($fn);
        $this->assertSame($fn, Request::getSanitizer());
    }

    #[Test]
    public function sanitizerNotAppliedToFiles(): void
    {
        $stream = (new StreamFactory())->createStream('content');
        $file = new UploadedFile($stream, 'test.txt', 'text/plain', 7);
        Request::$request = Request::$request->withUploadedFiles(['doc' => $file]);

        Request::setSanitizer(fn($value, $key) => 'SANITIZED');

        // files() should NOT be affected by sanitizer
        $result = Request::getInstance()->files('doc');
        $this->assertSame($file, $result);
    }
}
