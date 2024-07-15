<?php

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Class CacheOff
 *
 * Avoid browser caching
 */
class CacheOff
{
    /**
     * Set no cache header.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        return $response
            ->withHeader('Cache-Control', 'private, post-check=0, pre-check=, no-store, no-cache="Set-Cookie", must-revalidate, max-age=0, proxy-revalidate, no-transform')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Expires', 'Thu, 1 Jan 1970 00:00:00 GMT');
    }
}
