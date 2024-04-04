<?php

namespace Simsoft\Slim\ErrorRenderers;

use Slim\Error\AbstractErrorRenderer;
use Slim\Error\Renderers\JsonErrorRenderer as JsonRenderer;
use Throwable;

/**
 * AbstractJsonErrorRenderer class.
 */
abstract class AbstractJsonErrorRenderer extends AbstractErrorRenderer
{
    /**
     * Build array for JSON.
     *
     * @return array<string|int>
     */
    abstract public function formatExceptionFragment(Throwable $exception): array;

    /**
     * @param Throwable $exception
     * @param bool $displayErrorDetails
     * @return string
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            $renderer = new JsonRenderer();
            return $renderer($exception, $displayErrorDetails);
        }

        return (string)json_encode(
            $this->formatExceptionFragment($exception),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
