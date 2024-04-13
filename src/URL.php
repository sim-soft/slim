<?php

namespace Simsoft\Slim;

use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\UriFactory;

/**
 * URL Class
 *
 * URL helper for Slim routes.
 */
class URL
{
    /** @var RouteParserInterface Route parser. */
    protected static RouteParserInterface $routeParser;

    /** @var string|null Domain name. */
    protected static ?string $domain = null;

    /**
     * Set route parser.
     *
     * @param RouteParserInterface $parser Route parser.
     * @return void
     */
    public static function setParser(RouteParserInterface $parser): void
    {
        self::$routeParser = $parser;
    }

    /**
     * Set primary domain name.
     *
     * @param string|null $domain Domain name.
     * @return void
     */
    public static function setDomain(?string $domain = null): void
    {
        static::$domain = $domain;
    }

    /**
     * Get server data.
     *
     * @param string $key
     * @return string|null
     */
    protected static function server(string $key): ?string
    {
        return $_SERVER[$key] ?? null;
    }

    /**
     * Get domain name.
     *
     * @return string|null
     */
    public static function getDomain(): ?string
    {
        if (static::$domain === null) {
            static::$domain = (static::server('HTTPS') ? 'http' : 'https')
                . "://" . static::server('HTTP_HOST') . static::server('REQUEST_URI');
        }
        return static::$domain;
    }

    /**
     * Get route URL.
     *
     * @param string $routeName Route name.
     * @param string[] $data
     * @param string[] $queryParams
     * @return string
     */
    public static function for(string $routeName, array $data = [], array $queryParams = []): string
    {
        //return self::$routeParser->relativeUrlFor($routeName, $data, $queryParams);
        return strtr(self::$routeParser->urlFor($routeName, $data, $queryParams), ['//' => '/']);
    }

    /**
     * Get route full URL (included domain).
     *
     * @param string $routeName Route name.
     * @param string[] $data
     * @param string[] $queryParams
     * @return string
     */
    public static function fullFor(string $routeName, array $data = [], array $queryParams = []): string
    {
        $domain = static::getDomain();
        if ($domain) {
            [$protocol, $url] = explode('://',
                self::$routeParser->fullUrlFor(
                    (new UriFactory())->createUri($domain), $routeName, $data, $queryParams)
            );
            return "$protocol://" . strtr($url, ['//' => '/']);
        }

        return strtr(self::$routeParser->urlFor($routeName, $data, $queryParams), ['//' => '/']);
    }

}
