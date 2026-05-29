<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * MaintenanceMode Class
 *
 * Returns a 503 Service Unavailable response when maintenance mode is active.
 * Optionally allows specific IPs to bypass (e.g., developers).
 */
class MaintenanceMode
{
    /**
     * Constructor.
     *
     * @param bool $enabled Whether maintenance mode is active.
     * @param string $message Message shown to users during maintenance.
     * @param string[] $allowedIps IPs that can bypass maintenance mode.
     * @param int $retryAfter Retry-After header value in seconds (tells clients when to try again).
     */
    public function __construct(
        protected bool   $enabled = false,
        protected string $message = 'We are currently performing maintenance. Please try again later.',
        protected array  $allowedIps = [],
        protected int    $retryAfter = 3600,
    )
    {
    }

    /**
     * Block requests when maintenance mode is active.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        if ($this->isAllowed($request)) {
            return $handler->handle($request);
        }

        throw new HttpException($request, $this->message, 503);
    }

    /**
     * Check if the client IP is in the allowed list.
     *
     * @param Request $request
     * @return bool
     */
    protected function isAllowed(Request $request): bool
    {
        if ($this->allowedIps === []) {
            return false;
        }

        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        return in_array($clientIp, $this->allowedIps, true);
    }
}
