<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Handlers\Strategies\Args;
use Simsoft\Slim\Request;
use Simsoft\Slim\Response;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Tests for Single Action Controller (invokable class) pattern.
 */
class ActionTest extends TestCase
{
    private Args $strategy;

    protected function setUp(): void
    {
        $this->strategy = new Args();
    }

    #[Test]
    public function invokableClassIsCalledWithRouteArgs(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $action = new class {
            public ?string $receivedId = null;

            public function __invoke(string $id): void
            {
                $this->receivedId = $id;
            }
        };

        $this->strategy->__invoke($action, $request, $response, ['id' => '42']);

        $this->assertSame('42', $action->receivedId);
    }

    #[Test]
    public function invokableClassReturningArrayProducesJson(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $action = new class {
            public function __invoke(): array
            {
                return ['users' => [['id' => 1, 'name' => 'John']]];
            }
        };

        $result = $this->strategy->__invoke($action, $request, $response, []);

        $body = (string)$result->getBody();
        $this->assertJson($body);
        $this->assertSame(['users' => [['id' => 1, 'name' => 'John']]], json_decode($body, true));
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function invokableClassReturningStringProducesText(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $action = new class {
            public function __invoke(): string
            {
                return 'Hello World';
            }
        };

        $result = $this->strategy->__invoke($action, $request, $response, []);

        $this->assertSame('Hello World', (string)$result->getBody());
    }

    #[Test]
    public function invokableClassReturningVoidUsesResponseHelper(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $action = new class {
            public function __invoke(string $name): void
            {
                Response::$response->getBody()->write("Hello $name");
            }
        };

        $result = $this->strategy->__invoke($action, $request, $response, ['name' => 'World']);

        $this->assertSame('Hello World', (string)$result->getBody());
    }

    #[Test]
    public function invokableClassWithMultipleArgs(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $action = new class {
            public function __invoke(string $year, string $slug): string
            {
                return "Post: $year/$slug";
            }
        };

        $result = $this->strategy->__invoke($action, $request, $response, ['year' => '2025', 'slug' => 'hello-world']);

        $this->assertSame('Post: 2025/hello-world', (string)$result->getBody());
    }

    #[Test]
    public function invokableClassWithNoArgs(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $action = new class {
            public function __invoke(): array
            {
                return ['status' => 'ok'];
            }
        };

        $result = $this->strategy->__invoke($action, $request, $response, []);

        $this->assertJson((string)$result->getBody());
    }

    #[Test]
    public function invokableClassSetsStaticRequestAndResponse(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', 'https://example.com/users');
        $response = (new ResponseFactory())->createResponse();

        $capturedMethod = null;
        $action = new class {
            public static ?string $method = null;

            public function __invoke(): void
            {
                static::$method = Request::$request->getMethod();
            }
        };

        $this->strategy->__invoke($action, $request, $response, []);

        $this->assertSame('POST', $action::$method);
    }

    #[Test]
    public function invokableClassWithConstructorDependency(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = (new ResponseFactory())->createResponse();

        $service = new class {
            public function getUsers(): array
            {
                return [['id' => 1, 'name' => 'John']];
            }
        };

        $action = new class ($service) {
            public function __construct(private object $userService)
            {
            }

            public function __invoke(): array
            {
                return ['users' => $this->userService->getUsers()];
            }
        };

        $result = $this->strategy->__invoke($action, $request, $response, []);

        $body = json_decode((string)$result->getBody(), true);
        $this->assertSame([['id' => 1, 'name' => 'John']], $body['users']);
    }
}
