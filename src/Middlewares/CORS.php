<?php

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * CORS Class
 *
 * Handling CORS.
 */
class CORS
{
    /**
     * Constructor
     *
     * @param array $config
     */
    public function __construct(protected array $config = [])
    {
        $this->config = array_merge([
            'Origin' => '*',
            'Headers' => 'X-Requested-With, Content-Type, Accept, Origin, Authorization',
            'Methods' => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Credentials' => 'false',
        ], $this->config);

    }

    /**
     * Setup CORS headers.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        foreach ($this->config as $header => $value) {
            $response = $response->withHeader('Access-Control-Allow-' . $header, $value);
        }

        return $response;
    }
}
