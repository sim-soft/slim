<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpException;

/**
 * Csrf Class
 *
 * CSRF protection middleware using per-request token pool.
 * Supports multiple concurrent forms/tabs without token conflicts.
 */
class Csrf
{
    /** @var string Session key for storing the token pool. */
    protected string $sessionKey;

    /** @var string Request field name for the CSRF token. */
    protected string $fieldName;

    /** @var string Request header name for the CSRF token. */
    protected string $headerName;

    /** @var int Maximum number of tokens to keep in the pool. */
    protected int $maxTokens;

    /** @var int Token lifetime in seconds. */
    protected int $tokenLifetime;

    /** @var string[] HTTP methods that require CSRF validation. */
    protected array $protectedMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];

    /** @var string|null The most recently generated token (for form rendering). */
    protected ?string $lastToken = null;

    /**
     * Constructor.
     *
     * @param string $fieldName Form field name for the token.
     * @param string $headerName Header name for the token (for AJAX requests).
     * @param int $maxTokens Maximum tokens in the pool (prevents memory bloat).
     * @param int $tokenLifetime Token validity duration in seconds.
     * @param string $sessionKey Session key for token storage.
     */
    public function __construct(
        string $fieldName = '_csrf_token',
        string $headerName = 'X-CSRF-Token',
        int    $maxTokens = 20,
        int    $tokenLifetime = 3600,
        string $sessionKey = '_csrf_tokens',
    )
    {
        $this->fieldName = $fieldName;
        $this->headerName = $headerName;
        $this->maxTokens = $maxTokens;
        $this->tokenLifetime = $tokenLifetime;
        $this->sessionKey = $sessionKey;
    }

    /**
     * Ensure a session is active.
     *
     * @return void
     */
    protected function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get the token pool from the session, removing expired tokens.
     *
     * @return array<string, int> Map of token => expiry timestamp.
     */
    protected function getTokenPool(): array
    {
        $this->ensureSession();
        $pool = $_SESSION[$this->sessionKey] ?? [];

        if (!is_array($pool)) {
            return [];
        }

        $now = time();
        return array_filter($pool, fn(int $expires) => $expires > $now);
    }

    /**
     * Save the token pool to the session.
     *
     * @param array<string, int> $pool
     * @return void
     */
    protected function saveTokenPool(array $pool): void
    {
        $_SESSION[$this->sessionKey] = $pool;
    }

    /**
     * Generate a new CSRF token and add it to the pool.
     *
     * @return string The generated token.
     */
    public function generateToken(): string
    {
        $this->ensureSession();

        $token = bin2hex(random_bytes(32));
        $pool = $this->getTokenPool();

        // Enforce pool size limit (remove oldest tokens)
        while (count($pool) >= $this->maxTokens) {
            array_shift($pool);
        }

        $pool[$token] = time() + $this->tokenLifetime;
        $this->saveTokenPool($pool);
        $this->lastToken = $token;

        return $token;
    }

    /**
     * Get the current token for form rendering.
     * Generates a new one if none exists.
     *
     * @return string
     */
    public function getToken(): string
    {
        if ($this->lastToken !== null) {
            return $this->lastToken;
        }

        return $this->generateToken();
    }

    /**
     * Get the form field name.
     *
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Get the header name.
     *
     * @return string
     */
    public function getHeaderName(): string
    {
        return $this->headerName;
    }

    /**
     * Generate an HTML hidden input field with a fresh CSRF token.
     *
     * @return string
     */
    public function getTokenField(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($this->fieldName, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($this->generateToken(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Validate and consume a CSRF token from the request.
     *
     * @param Request $request
     * @return bool
     */
    protected function validateToken(Request $request): bool
    {
        $this->ensureSession();

        $pool = $this->getTokenPool();
        if ($pool === []) {
            return false;
        }

        // Check header first (AJAX), then body (form)
        $token = $request->getHeaderLine($this->headerName);

        if ($token === '') {
            $body = $request->getParsedBody();
            $token = is_array($body) ? (string)($body[$this->fieldName] ?? '') : '';
        }

        if ($token === '') {
            return false;
        }

        // Timing-safe lookup
        foreach (array_keys($pool) as $storedToken) {
            if (hash_equals((string)$storedToken, $token)) {
                // Consume the token (one-time use)
                unset($pool[$storedToken]);
                $this->saveTokenPool($pool);
                return true;
            }
        }

        return false;
    }

    /**
     * Apply CSRF protection.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        if (in_array($request->getMethod(), $this->protectedMethods, true)) {
            if (!$this->validateToken($request)) {
                throw new HttpException($request, 'CSRF token validation failed.', 403);
            }
        }

        return $handler->handle($request);
    }
}
