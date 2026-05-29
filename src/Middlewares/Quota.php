<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * Quota Class
 *
 * Enforces API usage quotas per identity (user, API key, etc.).
 * Unlike RateLimit (burst protection), Quota tracks total usage
 * over longer periods (daily, monthly, yearly).
 */
class Quota
{
    /** @var RateLimitStorageInterface Storage backend. */
    protected RateLimitStorageInterface $storage;

    /** @var int Period duration in seconds. */
    protected int $periodSeconds;

    /**
     * Constructor.
     *
     * @param callable(Request): string $resolver Returns the quota key (user ID, API key, etc.).
     * @param callable(Request, string): int $limit Returns the max allowed requests for this key.
     * @param string $period Reset period: 'hourly', 'daily', 'monthly', 'yearly'.
     * @param RateLimitStorageInterface|null $storage Storage backend. Defaults to file storage.
     * @param string $headerPrefix Prefix for response headers.
     */
    public function __construct(
        protected                  $resolver,
        protected                  $limit,
        string                     $period = 'monthly',
        ?RateLimitStorageInterface $storage = null,
        protected string           $headerPrefix = 'X-Quota',
    )
    {
        $this->periodSeconds = $this->parsePeriod($period);
        $this->storage = $storage ?? new RateLimitFileStorage(
            sys_get_temp_dir() . '/slim-quota'
        );
    }

    /**
     * Enforce quota.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $key = ($this->resolver)($request);
        $maxLimit = ($this->limit)($request, $key);

        $result = $this->storage->increment($key, $this->periodSeconds);
        $remaining = max(0, $maxLimit - $result['count']);

        if ($result['count'] > $maxLimit) {
            throw new HttpException($request, 'Quota exceeded.', 429);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader($this->headerPrefix . '-Limit', (string)$maxLimit)
            ->withHeader($this->headerPrefix . '-Remaining', (string)$remaining)
            ->withHeader($this->headerPrefix . '-Reset', (string)$result['expires']);
    }

    /**
     * Parse period string to seconds.
     *
     * @param string $period
     * @return int
     */
    protected function parsePeriod(string $period): int
    {
        return match ($period) {
            'hourly' => 3600,
            'daily' => 86400,
            'weekly' => 604800,
            'monthly' => 2592000,
            'yearly' => 31536000,
            default => (int)$period > 0 ? (int)$period : 2592000,
        };
    }
}
