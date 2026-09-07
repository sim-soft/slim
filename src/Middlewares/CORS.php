<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * CORS Class
 *
 * Handling CORS.
 *
 * The allowed origin is resolved per request from the incoming `Origin`
 * header, so a single instance can safely serve requests from several
 * different origins (including under persistent workers such as
 * RoadRunner, Swoole or FrankenPHP).
 */
class CORS
{
    /** @var string[] Additional access control headers, keyed by suffix. */
    protected array $allows = [];

    /** @var string[] Allowed origins. Empty when any origin is allowed. */
    protected array $origins = [];

    /** @var bool Whether every origin is allowed. */
    protected bool $allowAnyOrigin;

    /**
     * Constructor
     *
     * @param string $origins Origins. Separate multiple origins by commas. Default: '*'
     * @param string $methods
     */
    public function __construct(string $origins = '*', string $methods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS')
    {
        $this->allowAnyOrigin = trim($origins) === '*';

        if (!$this->allowAnyOrigin) {
            $this->origins = array_values(array_filter(
                array_map('trim', explode(',', $origins)),
                static fn(string $origin): bool => $origin !== ''
            ));
        }

        $this->allows = [
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
     * Get the allowed origin for a request.
     *
     * With a single configured origin the value is constant and is returned
     * as-is; the browser compares it against the caller's real origin. With
     * several configured origins the request's own `Origin` is echoed back
     * when it is on the allow list, otherwise `'null'`.
     *
     * @param Request $request Current request.
     * @return string
     */
    public function resolveOrigin(Request $request): string
    {
        if ($this->allowAnyOrigin) {
            return '*';
        }

        // A single origin never varies by request.
        if (count($this->origins) === 1) {
            return $this->origins[0];
        }

        $origin = $request->getHeaderLine('Origin');

        return $origin !== '' && in_array($origin, $this->origins, true) ? $origin : 'null';
    }

    /**
     * Whether the emitted origin depends on the request.
     *
     * @return bool
     */
    protected function originVariesByRequest(): bool
    {
        return !$this->allowAnyOrigin && count($this->origins) > 1;
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

        $response = $response->withHeader(
            'Access-Control-Allow-Origin',
            $this->resolveOrigin($request)
        );

        foreach ($this->allows as $header => $value) {
            $response = $response->withHeader('Access-Control-Allow-' . $header, $value);
        }

        // The header varies by Origin, so caches must key on it.
        if ($this->originVariesByRequest()) {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }

        return $response;
    }
}
