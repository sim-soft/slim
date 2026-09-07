<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;

/**
 * RateLimit Class
 *
 * Rate limiting middleware with pluggable storage backend.
 *
 * The 429 is built and returned directly rather than thrown as an
 * HttpException, because Slim's ErrorHandler renders a fresh response and
 * would discard the X-RateLimit-* and Retry-After headers. The trade-off is
 * that this response does not pass through a custom error renderer.
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
            $response = (new ResponseFactory())->createResponse(429);
            $response->getBody()->write('Rate limit exceeded. Try again later.');
            $response = $response->withHeader('Content-Type', 'text/plain');

            // A client that is being throttled needs to know for how long,
            // so the limit headers matter more on a 429 than on a success.
            return $this->withLimitHeaders($response, $remaining, $resetAt)
                ->withHeader('Retry-After', (string)max(0, $resetAt - time()));
        }

        return $this->withLimitHeaders($handler->handle($request), $remaining, $resetAt);
    }

    /**
     * Attach the rate limit headers to a response.
     *
     * @param Response $response Response to decorate.
     * @param int $remaining Requests left in the current window.
     * @param int $resetAt Unix timestamp when the window resets.
     * @return Response
     */
    protected function withLimitHeaders(Response $response, int $remaining, int $resetAt): Response
    {
        return $response
            ->withHeader('X-RateLimit-Limit', (string)$this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining)
            ->withHeader('X-RateLimit-Reset', (string)$resetAt);
    }
}
