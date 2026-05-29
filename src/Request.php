<?php

namespace Simsoft\Slim;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteInterface;
use Slim\Psr7\Factory\UriFactory;
use Slim\Routing\RouteContext;

/**
 * Request class
 *
 * @method string getProtocolVersion()
 * @method ServerRequestInterface withProtocolVersion(string $version)
 * @method array getHeaders()
 * @method bool hasHeader(string $name)
 * @method array getHeader(string $name)
 * @method string getHeaderLine(string $name)
 * @method ServerRequestInterface withHeader(string $name, string|string[] $value)
 * @method ServerRequestInterface withAddedHeader(string $name, string|string[] $value)
 * @method ServerRequestInterface withoutHeader(string $name)
 * @method StreamInterface getBody()
 * @method ServerRequestInterface withBody(StreamInterface $body)
 * @method string getMethod()
 * @method ServerRequestInterface withMethod(string $method)
 * @method string getRequestTarget()
 * @method ServerRequestInterface withRequestTarget(string $requestTarget)
 * @method UriInterface getUri()
 * @method ServerRequestInterface withUri(UriInterface $uri, bool $preserveHost = false)
 * @method array getCookieParams()
 * @method ServerRequestInterface withCookieParams(array $cookies)
 * @method array getQueryParams()
 * @method ServerRequestInterface withQueryParams(array $query)
 * @method UploadedFileInterface[] getUploadedFiles()
 * @method ServerRequestInterface withUploadedFiles(array $uploadedFiles)
 * @method array getServerParams()
 * @method array getAttributes()
 * @method mixed getAttribute(string $name, mixed $default = null)
 * @method ServerRequestInterface withAttribute(string $name, mixed $value)
 * @method ServerRequestInterface withoutAttribute(string $name)
 * @method array|null|object getParsedBody()
 * @method ServerRequestInterface withParsedBody(array|null|object $data)
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class Request
{
    /** @var ServerRequestInterface Current request object. */
    public static ServerRequestInterface $request;

    /** @var RouteContext|null Route context. */
    protected static ?RouteContext $routeContext = null;

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
     * @return static
     */
    public static function getInstance(): static
    {
        return new static();
    }

    /**
     * Get route context.
     *
     * @return RouteContext
     */
    public function getRouteContext(): RouteContext
    {
        if (static::$routeContext === null) {
            static::$routeContext = RouteContext::fromRequest(static::$request);
        }
        return static::$routeContext;
    }

    /**
     * Get route object.
     *
     * @return RouteInterface|null
     */
    public function getRoute(): ?RouteInterface
    {
        return $this->getRouteContext()->getRoute();
    }

    /**
     * Get route name.
     *
     * @return string|null
     */
    public function getRouteName(): ?string
    {
        return $this->getRoute()?->getName();
    }

    /**
     * Get route's unique identifier.
     *
     * @return string|null
     */
    public function getRouteIdentifier(): ?string
    {
        return $this->getRoute()?->getIdentifier();
    }

    /**
     * Retrieve a specific route argument.
     *
     * @param string $name
     * @param string|null $default
     * @return string|null
     */
    public function getArgument(string $name, ?string $default = null): ?string
    {
        return $this->getRoute()?->getArgument($name, $default);
    }

    /**
     * Retrieve route arguments.
     *
     * @return array<string, string>
     */
    public function getArguments(): array
    {
        return $this->getRoute()?->getArguments() ?? [];
    }

    /**
     * Get base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->getRouteContext()->getBasePath();
    }

    /**
     * Build the path for a named route excluding the base path.
     *
     * @param string $routeName Route name.
     * @param string[] $data Named argument replacement data.
     * @param string[] $queryParams Optional query string parameters.
     * @return string
     */
    public function relativeUrlFor(string $routeName, array $data = [], array $queryParams = []): string
    {
        return $this->getRouteContext()->getRouteParser()->relativeUrlFor($routeName, $data, $queryParams);
    }

    /**
     * Build the path for a named route including the base path.
     *
     * @param string $routeName Route name.
     * @param string[] $data Named argument replacement data.
     * @param string[] $queryParams Optional query string parameters.
     * @return string
     */
    public function urlFor(string $routeName, array $data = [], array $queryParams = []): string
    {
        return $this->getRouteContext()->getRouteParser()->urlFor($routeName, $data, $queryParams);
    }

    /**
     * Get fully qualified URL for named route.
     *
     * @param string $routeName Route name.
     * @param string[] $data Named argument replacement data.
     * @param string[] $queryParams Optional query string parameters.
     * @return string
     */
    public function fullUrlFor(string $routeName, array $data = [], array $queryParams = []): string
    {
        $domain = URL::getDomain();
        if ($domain) {
            return $this->getRouteContext()
                ->getRouteParser()
                ->fullUrlFor((new UriFactory())->createUri($domain), $routeName, $data, $queryParams);
        }

        return $this->getRouteContext()->getRouteParser()->urlFor($routeName, $data, $queryParams);
    }

    /**
     * Detect XHR request.
     *
     * @return bool
     */
    public function isXHR(): bool
    {
        return static::$request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * Check request method.
     *
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return static::$request->getMethod() === strtoupper($method);
    }

    /**
     * Request's allowed methods.
     *
     * @param string[] $methods Allowed methods.
     * @param string|null $message
     * @return void
     */
    public function allowedMethods(array $methods, ?string $message = null): void
    {
        throw (new HttpMethodNotAllowedException(static::$request, $message))->setAllowedMethods($methods);
    }

    /**
     * Request not found.
     *
     * @param string|null $message
     * @return void
     */
    public function notFound(?string $message = null): void
    {
        throw new HttpNotFoundException(static::$request, $message);
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * @param string $name Case-insensitive header field name.
     * @param string $default Default header value.
     * @return string
     */
    public function header(string $name, string $default = ''): string
    {
        return static::$request->getHeaderLine($name) ?: $default;
    }

    /** @var callable|null Global sanitizer applied to query() and input() values. */
    protected static $sanitizer = null;

    /**
     * Set a global sanitizer for query() and input() values.
     * Set null to disable.
     *
     * @param callable|null $sanitizer fn(mixed $value, string $key): mixed
     * @return void
     */
    public static function setSanitizer(?callable $sanitizer): void
    {
        static::$sanitizer = $sanitizer;
    }

    /**
     * Get the current global sanitizer.
     *
     * @return callable|null
     */
    public static function getSanitizer(): ?callable
    {
        return static::$sanitizer;
    }

    /**
     * Get bearer token.
     *
     * @return string
     */
    public function getBearerToken(): string
    {
        $value = $this->header('Authorization');
        if ($value) {
            [, $value] = explode(' ', $value);
        }
        return $value;
    }

    /**
     * Get query parameters (GET data).
     *
     * - `query()` — returns all query params
     * - `query('key')` — returns a single param value (or null)
     * - `query(['key1', 'key2'])` — returns only the specified params
     *
     * Values are automatically sanitized using the global sanitizer (if set via setSanitizer()).
     *
     * @param string|string[]|null $key Key name, array of key names, or null for all.
     * @param mixed $default Default value when key is not found.
     * @return mixed
     */
    public function query(string|array|null $key = null, mixed $default = null): mixed
    {
        return $this->extractData(static::$request->getQueryParams(), $key, $default);
    }

    /**
     * Get parsed body parameters (POST/PUT/JSON data).
     *
     * - `input()` — returns all body params
     * - `input('key')` — returns a single param value (or null)
     * - `input(['key1', 'key2'])` — returns only the specified params
     *
     * Values are automatically sanitized using the global sanitizer (if set via setSanitizer()).
     *
     * @param string|string[]|null $key Key name, array of key names, or null for all.
     * @param mixed $default Default value when key is not found.
     * @return mixed
     */
    public function input(string|array|null $key = null, mixed $default = null): mixed
    {
        $body = static::$request->getParsedBody();
        $data = is_array($body) ? $body : (array)$body;
        return $this->extractData($data, $key, $default);
    }

    /**
     * Get uploaded files.
     *
     * - `files()` — returns all uploaded files
     * - `files('key')` — returns a single file (or null)
     * - `files(['key1', 'key2'])` — returns only the specified files
     *
     * @param string|string[]|null $key Key name, array of key names, or null for all.
     * @return mixed
     */
    public function files(string|array|null $key = null): mixed
    {
        $files = static::$request->getUploadedFiles();

        if ($key === null) {
            return $files;
        }

        if (is_string($key)) {
            return $files[$key] ?? null;
        }

        $result = [];
        foreach ($key as $name) {
            if (array_key_exists($name, $files)) {
                $result[$name] = $files[$name];
            }
        }
        return $result;
    }

    /**
     * Extract data from an array with optional key filtering and sanitization.
     *
     * @param array<string, mixed> $data Source data array.
     * @param string|string[]|null $key Key name, array of key names, or null for all.
     * @param mixed $default Default value when key is not found.
     * @return mixed
     */
    protected function extractData(array $data, string|array|null $key, mixed $default): mixed
    {
        $sanitizer = static::$sanitizer;

        // Return all data
        if ($key === null) {
            if ($sanitizer === null) {
                return $data;
            }
            $result = [];
            foreach ($data as $name => $value) {
                $result[$name] = $sanitizer($value, $name);
            }
            return $result;
        }

        // Single key
        if (is_string($key)) {
            $value = array_key_exists($key, $data) ? $data[$key] : $default;
            return $sanitizer ? $sanitizer($value, $key) : $value;
        }

        // Array of keys
        $result = [];
        foreach ($key as $name) {
            $value = array_key_exists($name, $data) ? $data[$name] : $default;
            $result[$name] = $sanitizer ? $sanitizer($value, $name) : $value;
        }
        return $result;
    }


    /**
     * Magic method call.
     *
     * @param string $name
     * @param array<int, string> $args
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists(static::$request, $name)) {
            $callable = [static::$request, $name];

            if (is_callable($callable)) {
                $return = call_user_func_array($callable, $args);
                if ($return instanceof ServerRequestInterface) {
                    static::$request = $return;
                }
                return $return;
            }
        }

        return null;
    }

}

if (!function_exists('request')) {
    /**
     * Add flash message for next request.
     *
     * @return Request
     */
    function request(): Request
    {
        return Request::getInstance();
    }
}
