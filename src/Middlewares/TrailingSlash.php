<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * TrailingSlash Class
 *
 * Normalizes URLs by either removing or adding trailing slashes.
 * Can either redirect (301) or silently rewrite the URI.
 */
class TrailingSlash
{
    /**
     * Constructor.
     *
     * @param bool $add True to add trailing slash, false to remove it.
     * @param bool $redirect True to send a 301 redirect, false to silently rewrite.
     */
    public function __construct(
        protected bool $add = false,
        protected bool $redirect = true,
    )
    {
    }

    /**
     * Normalize trailing slash in the request URI.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();

        // Skip root path
        if ($path === '' || $path === '/') {
            return $handler->handle($request);
        }

        $newPath = $this->add
            ? rtrim($path, '/') . '/'
            : rtrim($path, '/');

        if ($path === $newPath) {
            return $handler->handle($request);
        }

        $newUri = $uri->withPath($newPath);

        if ($this->redirect) {
            $response = (new ResponseFactory())->createResponse(301);
            return $response->withHeader('Location', (string)$newUri);
        }

        $request = $request->withUri($newUri);
        return $handler->handle($request);
    }
}
