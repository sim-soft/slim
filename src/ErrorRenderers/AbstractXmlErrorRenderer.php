<?php

namespace Simsoft\Slim\ErrorRenderers;

use Slim\Error\AbstractErrorRenderer;
use Slim\Error\Renderers\XmlErrorRenderer;
use Throwable;

/**
 * AbstractXmlErrorRenderer class.
 */
abstract class AbstractXmlErrorRenderer extends AbstractErrorRenderer
{
    /**
     * Build XMLl body.
     *
     * @param Throwable $exception
     * @return string
     */
    abstract public function renderXmlBody(Throwable $exception): string;

    /**
     * Build XML body.
     *
     * @param Throwable $exception
     * @param bool $displayErrorDetails
     * @return string
     */
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        if ($displayErrorDetails) {
            $renderer = new XmlErrorRenderer();
            return $renderer($exception, $displayErrorDetails);
        }

        $xml = '<' . '?xml version="1.0" encoding="UTF-8" standalone="yes"?' . ">\n";
        $xml .= $this->renderXmlBody($exception);
        return $xml;
    }

    /**
     * Returns a CDATA section with the given content.
     */
    protected function createCdataSection(string $content): string
    {
        return sprintf('<![CDATA[%s]]>', str_replace(']]>', ']]]]><![CDATA[>', $content));
    }
}