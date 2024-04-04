<?php

namespace Simsoft\Slim\ErrorRenderers;

use Slim\Error\AbstractErrorRenderer;
use Slim\Error\Renderers\PlainTextErrorRenderer;
use Throwable;

/**
 * AbstractPlainTextErrorRenderer class.
 */
abstract class AbstractPlainTextErrorRenderer extends AbstractErrorRenderer
{
    /**
     * Build Plain text body.
     *
     * @param Throwable $exception
     * @return string
     */
    abstract public function formatExceptionFragment(Throwable $exception): string;

    /**
     *
     * @param Throwable $exception
     * @param bool $displayErrorDetails
     * @return string
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            $renderer = new PlainTextErrorRenderer();
            return $renderer($exception, $displayErrorDetails);
        }

        return $this->formatExceptionFragment($exception);
    }
}
