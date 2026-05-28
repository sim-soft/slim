<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Middlewares;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Simsoft\Slim\Middlewares\Auth;
use Slim\Exception\HttpException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

class AuthTest extends TestCase
{
    private function createHandler(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function (ServerRequestInterface $request) {
            return (new ResponseFactory())->createResponse();
        });
        return $handler;
    }

    private function createCapturingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public ?ServerRequestInterface $capturedRequest = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->capturedRequest = $request;
                return (new ResponseFactory())->createResponse();
            }
        };
    }

    private function validAuthenticator(): callable
    {
        return fn(ServerRequestInterface $request) => [
            'id' => 1,
            'name' => 'John',
            'roles' => ['admin', 'editor'],
            'permissions' => ['users.read', 'users.write', 'posts.read'],
        ];
    }

    private function failingAuthenticator(): callable
    {
        return fn(ServerRequestInterface $request) => null;
    }

    #[Test]
    public function authenticatedRequestPasses(): void
    {
        $auth = new Auth($this->validAuthenticator());
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function unauthenticatedRequestThrows401(): void
    {
        $auth = new Auth($this->failingAuthenticator());
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        $auth($request, $this->createHandler());
    }

    #[Test]
    public function userIsStoredInRequestAttribute(): void
    {
        $auth = new Auth($this->validAuthenticator());
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createCapturingHandler();

        $auth($request, $handler);

        $user = $handler->capturedRequest->getAttribute('user');
        $this->assertSame(1, $user['id']);
        $this->assertSame('John', $user['name']);
    }

    #[Test]
    public function customAttributeName(): void
    {
        $auth = new Auth($this->validAuthenticator(), attribute: 'currentUser');
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $handler = $this->createCapturingHandler();

        $auth($request, $handler);

        $this->assertNotNull($handler->capturedRequest->getAttribute('currentUser'));
        $this->assertNull($handler->capturedRequest->getAttribute('user'));
    }

    #[Test]
    public function roleCheckPassesWhenUserHasRole(): void
    {
        $auth = new Auth($this->validAuthenticator(), roles: ['admin']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function roleCheckPassesWithAnyMatchingRole(): void
    {
        $auth = new Auth($this->validAuthenticator(), roles: ['superadmin', 'editor']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function roleCheckThrows403WhenNoMatchingRole(): void
    {
        $auth = new Auth($this->validAuthenticator(), roles: ['superadmin']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $auth($request, $this->createHandler());
    }

    #[Test]
    public function permissionCheckPassesWhenUserHasAll(): void
    {
        $auth = new Auth($this->validAuthenticator(), permissions: ['users.read', 'posts.read']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function permissionCheckThrows403WhenMissingPermission(): void
    {
        $auth = new Auth($this->validAuthenticator(), permissions: ['users.read', 'users.delete']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $auth($request, $this->createHandler());
    }

    #[Test]
    public function combinedRoleAndPermissionCheck(): void
    {
        $auth = new Auth($this->validAuthenticator(), roles: ['admin'], permissions: ['users.read']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function roleFailsBeforePermissionCheck(): void
    {
        $auth = new Auth($this->validAuthenticator(), roles: ['superadmin'], permissions: ['users.read']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        try {
            $auth($request, $this->createHandler());
            $this->fail('Expected HttpException');
        } catch (HttpException $ex) {
            $this->assertSame(403, $ex->getCode());
            $this->assertStringContainsString('role', $ex->getMessage());
        }
    }

    #[Test]
    public function noRolesOrPermissionsOnlyRequiresAuthentication(): void
    {
        $auth = new Auth($this->validAuthenticator());
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function withRolesReturnsNewInstance(): void
    {
        $auth = new Auth($this->validAuthenticator());
        $authWithRoles = $auth->withRoles('admin');

        $this->assertNotSame($auth, $authWithRoles);
    }

    #[Test]
    public function withRolesDoesNotMutateOriginal(): void
    {
        $auth = new Auth($this->validAuthenticator());
        $auth->withRoles('superadmin');

        // Original should still pass without role check
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $response = $auth($request, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function withPermissionsReturnsNewInstance(): void
    {
        $auth = new Auth($this->validAuthenticator());
        $authWithPerms = $auth->withPermissions('users.delete');

        $this->assertNotSame($auth, $authWithPerms);
    }

    #[Test]
    public function bearerTokenAuthenticator(): void
    {
        $authenticator = function (ServerRequestInterface $request): ?array {
            $header = $request->getHeaderLine('Authorization');
            if ($header === '' || !str_starts_with($header, 'Bearer ')) {
                return null;
            }
            $token = substr($header, 7);
            if ($token === 'valid-token') {
                return ['id' => 1, 'name' => 'API User', 'roles' => ['api']];
            }
            return null;
        };

        $auth = new Auth($authenticator);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Authorization', 'Bearer valid-token');

        $response = $auth($request, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    #[Test]
    public function bearerTokenAuthenticatorFailsWithInvalidToken(): void
    {
        $authenticator = function (ServerRequestInterface $request): ?array {
            $header = $request->getHeaderLine('Authorization');
            if ($header === '' || !str_starts_with($header, 'Bearer ')) {
                return null;
            }
            $token = substr($header, 7);
            if ($token === 'valid-token') {
                return ['id' => 1, 'name' => 'API User', 'roles' => ['api']];
            }
            return null;
        };

        $auth = new Auth($authenticator);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');
        $request = $request->withHeader('Authorization', 'Bearer bad-token');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        $auth($request, $this->createHandler());
    }

    #[Test]
    public function userWithoutRolesKeyFailsRoleCheck(): void
    {
        $authenticator = fn() => ['id' => 1, 'name' => 'No Roles'];

        $auth = new Auth($authenticator, roles: ['admin']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $auth($request, $this->createHandler());
    }

    #[Test]
    public function userWithoutPermissionsKeyFailsPermissionCheck(): void
    {
        $authenticator = fn() => ['id' => 1, 'name' => 'No Perms'];

        $auth = new Auth($authenticator, permissions: ['users.read']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        $auth($request, $this->createHandler());
    }

    #[Test]
    public function sessionBasedAuthenticator(): void
    {
        $_SESSION['user'] = ['id' => 5, 'name' => 'Session User', 'roles' => ['member']];

        $authenticator = fn() => $_SESSION['user'] ?? null;

        $auth = new Auth($authenticator, roles: ['member']);
        $request = (new ServerRequestFactory())->createServerRequest('GET', 'https://example.com');

        $response = $auth($request, $this->createHandler());
        $this->assertInstanceOf(ResponseInterface::class, $response);

        unset($_SESSION['user']);
    }
}
