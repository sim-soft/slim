<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Simsoft\Slim\Handlers\Strategies;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Simsoft\Slim\Request;
use Simsoft\Slim\Response;
use Slim\Interfaces\InvocationStrategyInterface;

use function array_values;

/**
 * Route callback strategy with route parameters as individual arguments.
 */
class Args implements InvocationStrategyInterface
{
    /**
     * Invoke a route callable with request, response and all route parameters
     * as individual arguments.
     *
     * @param array<string, string> $routeArguments
     * @throws Exception
     */
    public function __invoke(
        callable               $callable,
        ServerRequestInterface $request,
        ResponseInterface      $response,
        array                  $routeArguments
    ): ResponseInterface
    {

        Request::$request = $request;
        Response::$response = $response;

        $value = $callable(...array_values($routeArguments));

        if ($value) {
            if (is_array($value)) {
                $content = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                if ($content === false) {
                    throw new Exception('Failed to json encode content.');
                }
                Response::$response->getBody()->write($content);
                return Response::$response->withHeader('Content-Type', 'application/json');

            } elseif (is_string($value)) {
                Response::$response->getBody()->write($value);
                return Response::$response;
            }
        }

        return Response::$response;
    }
}
