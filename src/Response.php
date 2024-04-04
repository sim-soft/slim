<?php

namespace Simsoft\Slim;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
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
     * Get instance.
     *
     * @return $this
     */
    public static function getInstance(): static
    {
        return new static();
    }

    /**
     * Set content.
     *
     * @param string|array $content
     * @return $this
     */
    public function content(string|array $content): static
    {
        if (is_array($content)) {
            $this->json($content);
        } else {
            static::$response->getBody()->write($content);
        }
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
     * @param array $data
     * @return $this
     */
    public function json(array $data): static
    {
        static::$response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        static::$response = static::$response->withHeader('Content-Type', 'application/json');
        return $this;
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
     * @param string|array $value
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
     * @param array $headers
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
     * Magic call
     *
     * @param string $name Method name of \Slim\Psr7\Response
     * @param array $args
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists(static::$response, $name)) {
            $return = call_user_func_array([static::$response, $name], $args);
            if ($return instanceof ResponseInterface) {
                static::$response = $return;
            }
            return $return;
        }

        return null;
    }
}


if (!function_exists('response')) {
    /**
     * Add flash message for next request.
     *
     * @param string|array|null $content Response content.
     * @param int|null $code Status code.
     * @return Response
     */
    function response(string|array|null $content = null, ?int $code = null): Response
    {
        $response = Response::getInstance();

        if ($content) {
            $response->content($content);
        }

        if ($code) {
            $response->Status($code);
        }

        return $response;
    }
}

if (!function_exists('redirect')) {
    /**
     * Add flash message for next request.
     *
     * @param string $url Target redirect URL.
     * @param int $code Status code. Default: 301
     * @param DateTime|null $cache Enable cache.
     * @return null
     */
    function redirect(string $url, int $code = 301, ?DateTime $cache = null): null
    {
        if ($cache) {
            $cache->setTimeZone(new DateTimeZone('GMT'));
            Response::$response = Response::$response
                ->withHeader('Expires', date_format($cache, DateTimeInterface::RFC7231))
                ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        }

        Response::$response = Response::$response->withHeader('Location', $url)->withStatus($code);
        return null;
    }
}

