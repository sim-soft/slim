<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

/**
 * Logging Class
 *
 * Collects comprehensive request/response information and passes a LogEntry DTO
 * to a developer-defined recorder. Captures data from the request, response,
 * and attributes set by other middleware (Auth, etc.).
 */
class Logging
{
    /**
     * Constructor.
     *
     * @param callable(LogEntry): void $recorder Function that receives the LogEntry DTO.
     * @param string $userAttribute Request attribute name containing the authenticated user.
     * @param bool $logResponseBody Include response body in log data.
     * @param int $maxBodyLength Maximum response body length to capture (0 = unlimited).
     * @param bool $logRequestHeaders Include request headers in log data.
     * @param bool $logResponseHeaders Include response headers in log data.
     */
    public function __construct(
        protected        $recorder,
        protected string $userAttribute = 'user',
        protected bool   $logResponseBody = true,
        protected int    $maxBodyLength = 4096,
        protected bool   $logRequestHeaders = true,
        protected bool   $logResponseHeaders = true,
    )
    {
    }

    /**
     * Collect request/response data and pass LogEntry to recorder.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $startTime = hrtime(true);

        $response = $handler->handle($request);

        $duration = (hrtime(true) - $startTime) / 1_000_000;

        $entry = $this->buildLogEntry($request, $response, $duration);

        ($this->recorder)($entry);

        return $response;
    }

    /**
     * Build the LogEntry DTO from request and response data.
     *
     * @param Request $request
     * @param Response $response
     * @param float $duration Duration in milliseconds.
     * @return LogEntry
     */
    protected function buildLogEntry(Request $request, Response $response, float $duration): LogEntry
    {
        $uri = $request->getUri();
        $serverParams = $request->getServerParams();
        $contentLength = $request->getHeaderLine('Content-Length');
        $user = $request->getAttribute($this->userAttribute);

        return new LogEntry(
            timestamp: date('Y-m-d H:i:s'),
            requestId: $this->getRequestId($request),
            pid: getmypid() ?: 0,
            serverName: $serverParams['SERVER_NAME'] ?? ($serverParams['HOSTNAME'] ?? null),
            method: $request->getMethod(),
            uri: (string)$uri,
            scheme: $uri->getScheme(),
            host: $uri->getHost(),
            port: $uri->getPort(),
            path: $uri->getPath(),
            ip: $this->getClientIp($request),
            isXhr: $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest',
            routeName: $this->getRouteName($request),
            routeArguments: $this->getRouteArguments($request),
            protocolVersion: $request->getProtocolVersion(),
            requestHeaders: $this->logRequestHeaders ? $this->flattenHeaders($request->getHeaders()) : [],
            userAgent: $request->getHeaderLine('User-Agent') ?: null,
            referer: $request->getHeaderLine('Referer') ?: null,
            requestContentLength: $contentLength !== '' ? (int)$contentLength : null,
            queryParams: $request->getQueryParams(),
            bodyParams: $request->getParsedBody(),
            user: is_array($user) ? $user : null,
            attributes: $this->getFilteredAttributes($request),
            status: $response->getStatusCode(),
            responseContentType: $response->getHeaderLine('Content-Type') ?: null,
            responseHeaders: $this->logResponseHeaders ? $this->flattenHeaders($response->getHeaders()) : [],
            responseSize: $this->getResponseSize($response),
            responseBody: $this->captureResponseBody($response),
            durationMs: round($duration, 2),
            memoryUsage: memory_get_peak_usage(true),
        );
    }

    /**
     * Get response body size and rewind stream.
     *
     * @param Response $response
     * @return int
     */
    protected function getResponseSize(Response $response): int
    {
        $body = (string)$response->getBody();
        $response->getBody()->rewind();
        return strlen($body);
    }

    /**
     * Capture response body content (truncated if configured).
     *
     * @param Response $response
     * @return string|null
     */
    protected function captureResponseBody(Response $response): ?string
    {
        if (!$this->logResponseBody) {
            return null;
        }

        $body = (string)$response->getBody();
        $response->getBody()->rewind();

        return $this->maxBodyLength > 0
            ? substr($body, 0, $this->maxBodyLength)
            : $body;
    }

    /**
     * Get client IP address from request.
     *
     * @param Request $request
     * @return string
     */
    protected function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get the matched route name.
     *
     * @param Request $request
     * @return string|null
     */
    protected function getRouteName(Request $request): ?string
    {
        try {
            $routeContext = RouteContext::fromRequest($request);
            return $routeContext->getRoute()?->getName();
        } catch (\RuntimeException) {
            return null;
        }
    }

    /**
     * Get the resolved route arguments.
     *
     * @param Request $request
     * @return array<string, string>
     */
    protected function getRouteArguments(Request $request): array
    {
        try {
            $routeContext = RouteContext::fromRequest($request);
            return $routeContext->getRoute()?->getArguments() ?? [];
        } catch (\RuntimeException) {
            return [];
        }
    }

    /**
     * Generate or extract a request ID for correlation.
     *
     * Uses X-Request-ID header if present, otherwise generates a unique ID.
     *
     * @param Request $request
     * @return string
     */
    protected function getRequestId(Request $request): string
    {
        $existing = $request->getHeaderLine('X-Request-ID');
        if ($existing !== '') {
            return $existing;
        }

        return bin2hex(random_bytes(8));
    }

    /**
     * Get all request attributes set by middleware, excluding internal Slim attributes.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    protected function getFilteredAttributes(Request $request): array
    {
        $attributes = $request->getAttributes();

        // Remove internal Slim routing attributes
        unset(
            $attributes['__route__'],
            $attributes['__routingResults__'],
            $attributes['__basePath__'],
        );

        return $attributes;
    }

    /**
     * Flatten multi-value headers into single strings.
     *
     * @param array<string, string[]> $headers
     * @return array<string, string>
     */
    protected function flattenHeaders(array $headers): array
    {
        $flat = [];
        foreach ($headers as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }
        return $flat;
    }
}
