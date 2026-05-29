<?php

declare(strict_types=1);

namespace Simsoft\Slim\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * ContentNegotiation Class
 *
 * Parses the Accept header and sets a request attribute with the preferred response format.
 * Route handlers can then use this to decide whether to respond with JSON, XML, HTML, etc.
 */
class ContentNegotiation
{
    /** @var array<string, string> Map of MIME types to format names. */
    protected array $formats;

    /** @var string Default format when no match is found. */
    protected string $defaultFormat;

    /** @var string Request attribute name to store the resolved format. */
    protected string $attribute;

    /**
     * Constructor.
     *
     * @param array<string, string> $formats Map of MIME type patterns to format names.
     * @param string $defaultFormat Fallback format when Accept header doesn't match.
     * @param string $attribute Request attribute name for the resolved format.
     */
    public function __construct(
        array  $formats = [],
        string $defaultFormat = 'json',
        string $attribute = 'format',
    )
    {
        $this->formats = $formats !== [] ? $formats : [
            'application/json' => 'json',
            'text/html' => 'html',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'text/plain' => 'text',
        ];
        $this->defaultFormat = $defaultFormat;
        $this->attribute = $attribute;
    }

    /**
     * Parse Accept header and set format attribute on the request.
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $accept = $request->getHeaderLine('Accept');
        $format = $this->resolve($accept);

        $request = $request->withAttribute($this->attribute, $format);

        return $handler->handle($request);
    }

    /**
     * Resolve the preferred format from the Accept header.
     *
     * @param string $accept Raw Accept header value.
     * @return string Resolved format name.
     */
    protected function resolve(string $accept): string
    {
        if ($accept === '' || $accept === '*/*') {
            return $this->defaultFormat;
        }

        $mediaTypes = $this->parseAcceptHeader($accept);

        foreach ($mediaTypes as $mediaType) {
            foreach ($this->formats as $mime => $format) {
                if ($this->matches($mediaType, $mime)) {
                    return $format;
                }
            }
        }

        return $this->defaultFormat;
    }

    /**
     * Parse Accept header into ordered list of media types (by quality).
     *
     * @param string $accept
     * @return string[] Media types sorted by quality (highest first).
     */
    protected function parseAcceptHeader(string $accept): array
    {
        $parts = array_map('trim', explode(',', $accept));
        $weighted = [];

        foreach ($parts as $part) {
            $segments = array_map('trim', explode(';', $part));
            $mediaType = $segments[0];
            $quality = 1.0;

            foreach ($segments as $segment) {
                if (str_starts_with($segment, 'q=')) {
                    $quality = (float)substr($segment, 2);
                }
            }

            $weighted[] = ['type' => $mediaType, 'quality' => $quality];
        }

        usort($weighted, fn($first, $second) => $second['quality'] <=> $first['quality']);

        return array_column($weighted, 'type');
    }

    /**
     * Check if a media type matches a registered MIME pattern.
     *
     * @param string $mediaType The media type from the Accept header.
     * @param string $mime The registered MIME type.
     * @return bool
     */
    protected function matches(string $mediaType, string $mime): bool
    {
        if ($mediaType === $mime) {
            return true;
        }

        // Handle wildcard subtypes: text/* matches text/html
        if (str_ends_with($mediaType, '/*')) {
            $prefix = substr($mediaType, 0, -1);
            return str_starts_with($mime, $prefix);
        }

        return false;
    }
}
