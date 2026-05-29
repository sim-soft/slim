<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

/**
 * LogEntry DTO
 *
 * Structured data object containing comprehensive request/response information
 * collected by the Logging middleware.
 *
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class LogEntry
{
    /**
     * Constructor.
     *
     * @param string $timestamp Request timestamp (Y-m-d H:i:s).
     * @param string $requestId Unique request/correlation ID.
     * @param int $pid PHP process ID.
     * @param string|null $serverName Server hostname handling the request.
     * @param string $method HTTP method (GET, POST, etc.).
     * @param string $uri Full request URI.
     * @param string $scheme Request scheme (http/https).
     * @param string $host Request host/domain.
     * @param int|null $port Request port (null if standard 80/443).
     * @param string $path URI path without query string.
     * @param string $ip Client IP address.
     * @param bool $isXhr Whether the request is an AJAX/XHR request.
     * @param string|null $routeName Matched route name.
     * @param array<string, string> $routeArguments Resolved route parameters.
     * @param string $protocolVersion HTTP protocol version (1.0, 1.1, 2).
     * @param array<string, string> $requestHeaders Request headers (name => value).
     * @param string|null $userAgent User-Agent header value.
     * @param string|null $referer Referer header value.
     * @param int|null $requestContentLength Request Content-Length in bytes.
     * @param array<string, mixed> $queryParams Query string parameters.
     * @param array<string, mixed>|object|null $bodyParams Parsed request body.
     * @param array<string, mixed>|null $user Authenticated user data (from Auth middleware).
     * @param array<string, mixed> $attributes All request attributes set by middleware.
     * @param int $status Response HTTP status code.
     * @param string|null $responseContentType Response Content-Type header.
     * @param array<string, string> $responseHeaders Response headers (name => value).
     * @param int $responseSize Response body size in bytes.
     * @param string|null $responseBody Response body content (may be truncated).
     * @param float $durationMs Execution time in milliseconds.
     * @param int $memoryUsage Peak memory usage in bytes.
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public readonly string            $timestamp,
        public readonly string            $requestId,
        public readonly int               $pid,
        public readonly ?string           $serverName,
        public readonly string            $method,
        public readonly string            $uri,
        public readonly string            $scheme,
        public readonly string            $host,
        public readonly ?int              $port,
        public readonly string            $path,
        public readonly string            $ip,
        public readonly bool              $isXhr,
        public readonly ?string           $routeName,
        public readonly array             $routeArguments,
        public readonly string            $protocolVersion,
        public readonly array             $requestHeaders,
        public readonly ?string           $userAgent,
        public readonly ?string           $referer,
        public readonly ?int              $requestContentLength,
        public readonly array             $queryParams,
        public readonly array|object|null $bodyParams,
        public readonly ?array            $user,
        public readonly array             $attributes,
        public readonly int               $status,
        public readonly ?string           $responseContentType,
        public readonly array             $responseHeaders,
        public readonly int               $responseSize,
        public readonly ?string           $responseBody,
        public readonly float             $durationMs,
        public readonly int               $memoryUsage,
    )
    {
    }

    /**
     * Convert to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'request_id' => $this->requestId,
            'pid' => $this->pid,
            'server_name' => $this->serverName,
            'method' => $this->method,
            'uri' => $this->uri,
            'scheme' => $this->scheme,
            'host' => $this->host,
            'port' => $this->port,
            'path' => $this->path,
            'ip' => $this->ip,
            'is_xhr' => $this->isXhr,
            'route_name' => $this->routeName,
            'route_arguments' => $this->routeArguments,
            'protocol_version' => $this->protocolVersion,
            'request_headers' => $this->requestHeaders,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
            'request_content_length' => $this->requestContentLength,
            'query_params' => $this->queryParams,
            'body_params' => $this->bodyParams,
            'user' => $this->user,
            'attributes' => $this->attributes,
            'status' => $this->status,
            'response_content_type' => $this->responseContentType,
            'response_headers' => $this->responseHeaders,
            'response_size' => $this->responseSize,
            'response_body' => $this->responseBody,
            'duration_ms' => $this->durationMs,
            'memory_usage' => $this->memoryUsage,
        ];
    }
}
