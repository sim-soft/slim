<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * Auth Class
 *
 * Authentication and authorization middleware.
 * Validates credentials via a user-supplied authenticator and optionally checks roles/permissions.
 */
class Auth
{
    /** @var callable(Request): ?array<string, mixed> Authenticator function. Returns user array or null. */
    protected $authenticator;

    /** @var string[] Required roles (any match grants access). */
    protected array $roles = [];

    /** @var string[] Required permissions (all must match). */
    protected array $permissions = [];

    /** @var string Request attribute name for storing the authenticated user. */
    protected string $attribute;

    /**
     * Constructor.
     *
     * @param callable(Request): ?array<string, mixed> $authenticator Function that receives the request and returns
     *                                                                a user array (with optional 'roles' and 'permissions' keys)
     *                                                                or null if authentication fails.
     * @param string[] $roles Required roles. User must have at least one (OR logic).
     * @param string[] $permissions Required permissions. User must have all (AND logic).
     * @param string $attribute Request attribute name to store the authenticated user.
     */
    public function __construct(
        callable $authenticator,
        array    $roles = [],
        array    $permissions = [],
        string   $attribute = 'user',
    )
    {
        $this->authenticator = $authenticator;
        $this->roles = $roles;
        $this->permissions = $permissions;
        $this->attribute = $attribute;
    }

    /**
     * Create a new instance with additional role requirements.
     *
     * @param string ...$roles
     * @return static
     */
    public function withRoles(string ...$roles): static
    {
        $clone = clone $this;
        $clone->roles = $roles;
        return $clone;
    }

    /**
     * Create a new instance with additional permission requirements.
     *
     * @param string ...$permissions
     * @return static
     */
    public function withPermissions(string ...$permissions): static
    {
        $clone = clone $this;
        $clone->permissions = $permissions;
        return $clone;
    }

    /**
     * Check if the user has at least one of the required roles.
     *
     * @param array<string, mixed> $user
     * @return bool
     */
    protected function hasRequiredRole(array $user): bool
    {
        if ($this->roles === []) {
            return true;
        }

        $userRoles = $user['roles'] ?? [];
        if (!is_array($userRoles)) {
            return false;
        }

        return array_intersect($this->roles, $userRoles) !== [];
    }

    /**
     * Check if the user has all required permissions.
     *
     * @param array<string, mixed> $user
     * @return bool
     */
    protected function hasRequiredPermissions(array $user): bool
    {
        if ($this->permissions === []) {
            return true;
        }

        $userPermissions = $user['permissions'] ?? [];
        if (!is_array($userPermissions)) {
            return false;
        }

        return array_diff($this->permissions, $userPermissions) === [];
    }

    /**
     * Apply authentication and authorization.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $user = ($this->authenticator)($request);

        if ($user === null) {
            throw new HttpException($request, 'Authentication required.', 401);
        }

        if (!$this->hasRequiredRole($user)) {
            throw new HttpException($request, 'Insufficient role.', 403);
        }

        if (!$this->hasRequiredPermissions($user)) {
            throw new HttpException($request, 'Insufficient permissions.', 403);
        }

        $request = $request->withAttribute($this->attribute, $user);

        return $handler->handle($request);
    }
}
