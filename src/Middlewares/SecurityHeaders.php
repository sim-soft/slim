<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * SecurityHeaders Class
 *
 * Adds recommended security headers to all responses.
 * Protects against clickjacking, MIME sniffing, XSS, and enforces HTTPS.
 */
class SecurityHeaders
{
    /** @var array<string, string> Headers to add. */
    protected array $headers;

    /**
     * Constructor.
     *
     * @param array<string, string> $headers Custom headers to override defaults.
     */
    public function __construct(array $headers = [])
    {
        $this->headers = $headers !== [] ? $headers : [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }

    /**
     * Enable Strict-Transport-Security (HSTS).
     * Only use this if your site is fully HTTPS.
     *
     * @param int $maxAge Max age in seconds (default: 1 year).
     * @param bool $includeSubDomains Apply to subdomains.
     * @return static
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function withHsts(int $maxAge = 31536000, bool $includeSubDomains = true): static
    {
        $clone = clone $this;
        $value = "max-age=$maxAge";
        if ($includeSubDomains) {
            $value .= '; includeSubDomains';
        }
        $clone->headers['Strict-Transport-Security'] = $value;
        return $clone;
    }

    /**
     * Set Content-Security-Policy header.
     *
     * @param string $policy CSP policy string.
     * @return static
     */
    public function withCsp(string $policy): static
    {
        $clone = clone $this;
        $clone->headers['Content-Security-Policy'] = $policy;
        return $clone;
    }

    /**
     * Add security headers to the response.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
