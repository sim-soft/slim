<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * RateLimit Class
 *
 * Rate limiting middleware with pluggable storage backend.
 */
class RateLimit
{
    /** @var RateLimitStorageInterface Storage backend. */
    protected RateLimitStorageInterface $storage;

    /**
     * Constructor.
     *
     * @param int $maxRequests Maximum requests allowed within the time window.
     * @param int $windowSeconds Time window in seconds.
     * @param RateLimitStorageInterface|null $storage Storage backend. Defaults to file-based storage.
     * @param string[] $trustedProxies List of trusted proxy IPs for X-Forwarded-For resolution.
     */
    public function __construct(
        protected int              $maxRequests = 60,
        protected int              $windowSeconds = 60,
        ?RateLimitStorageInterface $storage = null,
        protected array            $trustedProxies = [],
    )
    {
        $this->storage = $storage ?? new RateLimitFileStorage();
    }

    /**
     * Get client identifier from request.
     *
     * Resolves the real client IP, respecting X-Forwarded-For from trusted proxies.
     *
     * @param Request $request
     * @return string
     */
    protected function getClientId(Request $request): string
    {
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? 'unknown';

        if ($this->trustedProxies !== [] && in_array($remoteAddr, $this->trustedProxies, true)) {
            $forwarded = $request->getHeaderLine('X-Forwarded-For');
            if ($forwarded !== '') {
                $ips = array_map('trim', explode(',', $forwarded));
                return $ips[0];
            }
        }

        return $remoteAddr;
    }

    /**
     * Apply rate limiting.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $clientId = $this->getClientId($request);
        $result = $this->storage->increment($clientId, $this->windowSeconds);

        $remaining = max(0, $this->maxRequests - $result['count']);
        $resetAt = $result['expires'];

        if ($result['count'] > $this->maxRequests) {
            throw new HttpException($request, 'Rate limit exceeded. Try again later.', 429);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining)
            ->withHeader('X-RateLimit-Reset', (string)$resetAt);
    }
}
