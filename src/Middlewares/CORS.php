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
    /** @var string[] Allowed headers */
    protected array $allows = [];

    /**
     * Constructor
     *
     * @param string $origins Origins. Separate multiple origins by commas. Default: '*'
     * @param string $methods
     */
    public function __construct(string $origins = '*', string $methods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS')
    {
        $this->allows = [
            'Origin' => str_contains($origins, ',') ? $this->parseOrigins($origins) : $origins,
            'Headers' => 'X-Requested-With, Content-Type, Accept, Origin, Authorization',
            'Methods' => strtoupper($methods),
            'Credentials' => 'false',
        ];
    }

    /**
     * Add additional access control allow header.
     *
     * @param string $header Header name.
     * @param string $value Header value.
     * @return $this
     */
    public function allow(string $header, string $value): static
    {
        $this->allows[ucfirst($header)] = $value;
        return $this;
    }

    /**
     * Get allowed origin.
     *
     * @param string $origins Origins separated by commas.
     * @return string
     */
    public function parseOrigins(string $origins): string
    {
        $httpOrigin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? null;
        if ($httpOrigin && in_array($httpOrigin, explode(',', str_replace(' ', '', $origins)))) {
            return $httpOrigin;
        }
        return 'null';
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

        foreach ($this->allows as $header => $value) {
            $response = $response->withHeader('Access-Control-Allow-' . $header, $value);
        }

        return $response;
    }
}
