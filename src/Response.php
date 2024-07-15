<?php

namespace Simsoft\Slim;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Response class
 *
 * @method string getProtocolVersion()
 * @method ResponseInterface withProtocolVersion(string $version)
 * @method array getHeaders()
 * @method bool hasHeader(string $name)
 * @method array getHeader(string $name)
 * @method string getHeaderLine(string $name)
 * @method ResponseInterface withHeader(string $name, string|string[] $value)
 * @method ResponseInterface withAddedHeader(string $name, string|string[] $value)
 * @method ResponseInterface withoutHeader(string $name)
 * @method StreamInterface getBody()
 * @method ResponseInterface withBody(StreamInterface $body)
 * @method int getStatusCode()
 * @method ResponseInterface withStatus(int $code, string $reasonPhrase = '')
 * @method string getReasonPhrase()
 */
class Response
{
    public static ResponseInterface $response;

    /**
     * Constructor
     *
     * @return void
     */
    final public function __construct()
    {

    }

    /**
     * Get instance.
     *
     * @return Response
     */
    public static function getInstance(): Response
    {
        return new static();
    }

    /**
     * Set content.
     *
     * @param string|string[] $content
     * @return $this
     * @throws Exception
     */
    public function content(string|array $content): static
    {
        if (is_array($content)) {
            return $this->json($content);
        }

        static::$response->getBody()->write($content);
        return $this;
    }

    /**
     * Set status code.
     *
     * @param int $code Status code.
     * @param string $reasonPhrase Reason phrase.
     * @return $this
     */
    public function status(int $code, string $reasonPhrase = ''): static
    {
        static::$response = static::$response->withStatus($code, $reasonPhrase);
        return $this;
    }

    /**
     * Set JSON content.
     *
     * @param string[] $data
     * @return $this
     * @throws Exception
     */
    public function json(array $data): static
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($content) {
            static::$response->getBody()->write($content);
            static::$response = static::$response->withHeader('Content-Type', 'application/json');
            return $this;
        }

        throw new Exception('Failed to convert response to JSON');
    }

    /**
     * Set XML content.
     *
     * @param string $content XML content.
     * @return $this
     */
    public function xml(string $content): static
    {
        static::$response->getBody()->write($content);
        static::$response = static::$response->withHeader('Content-Type', 'text/xml');
        return $this;
    }

    /**
     * @param string $name
     * @param string|string[] $value
     * @return $this
     */
    public function header(string $name, string|array $value): static
    {
        static::$response = static::$response->withHeader($name, $value);
        return $this;
    }

    /**
     * Set headers.
     *
     * @param string[] $headers
     * @return $this
     */
    public function withHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }
        return $this;
    }

    /**
     * Redirect to URL.
     *
     * @param string $url Target redirect URL.
     * @param int $code Status code. Default: 302
     * @return $this
     */
    public function redirect(string $url, int $code = 302): static
    {
        $this->header('Location', $url)->status($code);
        return $this;
    }

    /**
     * Redirect to URL now.
     *
     * @param string $url Target redirect URL.
     * @param int $code Status code. Default: 302
     * @param DateTime|null $cache Enable cache.
     * @return void
     */
    public function redirectNow(string $url, int $code = 302, ?DateTime $cache = null): void
    {
        if ($cache) {
            $cache->setTimeZone(new DateTimeZone('GMT'));
            header('Expires: ' . date_format($cache, DateTimeInterface::RFC7231));
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        }
        header("Location: $url", true, $code);
        exit();
    }

    /**
     * Magic call
     *
     * @param string $name Method name of \Slim\Psr7\Response
     * @param string[] $args
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists(static::$response, $name)) {
            $callable = [static::$response, $name];
            if (is_callable($callable)) {
                $return = call_user_func_array($callable, $args);
                if ($return instanceof ResponseInterface) {
                    static::$response = $return;
                }
                return $return;
            }
        }

        return null;
    }
}


if (!function_exists('response')) {
    /**
     * Add flash message for next request.
     *
     * @param string|string[]|null $content Response content.
     * @param int|null $code Status code.
     * @return Response
     * @throws Exception
     */
    function response(string|array|null $content = null, ?int $code = null): Response
    {
        $response = Response::getInstance();

        if ($content) {
            $response->content($content);
        }

        if ($code) {
            $response->status($code);
        }

        return $response;
    }
}
