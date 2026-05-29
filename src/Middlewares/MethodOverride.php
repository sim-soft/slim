<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * MethodOverride Class
 *
 * Allows HTML forms to simulate PUT, PATCH, and DELETE requests.
 * Checks for method override in the X-Http-Method-Override header
 * or a _METHOD field in the request body.
 *
 * HTML forms only support GET and POST. This middleware lets you
 * submit a POST form with a hidden _METHOD field to route it as
 * PUT, PATCH, or DELETE.
 */
class MethodOverride
{
    /** @var string[] Allowed override methods. */
    protected array $allowedMethods = ['PUT', 'PATCH', 'DELETE'];

    /**
     * Constructor.
     *
     * @param string $fieldName The form field name for method override.
     * @param string $headerName The header name for method override.
     */
    public function __construct(
        protected string $fieldName = '_METHOD',
        protected string $headerName = 'X-Http-Method-Override',
    )
    {
    }

    /**
     * Override the request method if a valid override is found.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() !== 'POST') {
            return $handler->handle($request);
        }

        $override = $this->getOverrideMethod($request);

        if ($override !== null) {
            $request = $request->withMethod($override);
        }

        return $handler->handle($request);
    }

    /**
     * Extract the override method from header or body.
     *
     * @param Request $request
     * @return string|null The override method (uppercase) or null if not found/invalid.
     */
    protected function getOverrideMethod(Request $request): ?string
    {
        // Check header first
        $method = $request->getHeaderLine($this->headerName);

        // Then check body field
        if ($method === '') {
            $body = $request->getParsedBody();
            $method = is_array($body) ? ($body[$this->fieldName] ?? '') : '';
        }

        if ($method === '') {
            return null;
        }

        $method = strtoupper($method);

        if (!in_array($method, $this->allowedMethods, true)) {
            return null;
        }

        return $method;
    }
}
