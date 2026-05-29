<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * IpFilter Class
 *
 * Restricts access based on client IP address.
 * Supports whitelist (only allow listed IPs) or blacklist (block listed IPs) mode.
 */
class IpFilter
{
    /**
     * Constructor.
     *
     * @param string[] $ips List of IP addresses.
     * @param bool $whitelist True = only allow listed IPs, false = block listed IPs.
     * @param int $statusCode HTTP status code when blocked (default: 403).
     * @param string $message Error message when blocked.
     */
    public function __construct(
        protected array  $ips = [],
        protected bool   $whitelist = true,
        protected int    $statusCode = 403,
        protected string $message = 'Access denied.',
    )
    {
    }

    /**
     * Filter requests by IP address.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $clientIp = $this->getClientIp($request);
        $matched = in_array($clientIp, $this->ips, true);

        // Whitelist: block if NOT in list. Blacklist: block if IN list.
        $blocked = $this->whitelist ? !$matched : $matched;

        if ($blocked) {
            throw new HttpException($request, $this->message, $this->statusCode);
        }

        return $handler->handle($request);
    }

    /**
     * Get client IP from request.
     *
     * @param Request $request
     * @return string
     */
    protected function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
