<?php

namespace Simsoft\Slim\ErrorRenderers;

use Slim\Error\AbstractErrorRenderer;
use Slim\Error\Renderers\HtmlErrorRenderer as HtmlRenderer;
use Throwable;

/**
 * AbstractHtmlErrorRenderer class.
 */
abstract class AbstractHtmlErrorRenderer extends AbstractErrorRenderer
{
    /** @var string Default error title */
    protected string $defaultErrorTitle = 'Application Error';

    /**
     * Build HTML body.
     *
     * @param Throwable $exception
     * @return string
     */
    abstract public function renderHtmlBody(Throwable $exception): string;

    /**
     * @param Throwable $exception
     * @param bool $displayErrorDetails
     * @return string
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            $renderer = new HtmlRenderer();
            return $renderer($exception, $displayErrorDetails);
        }

        return $this->renderHtmlBody($exception);
    }
}