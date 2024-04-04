<?php

namespace Example\ErrorHandler;

use Example\ErrorHandler\ErrorRenderers\MyHtmlErrorRenderer;
use Slim\Error\Renderers\JsonErrorRenderer;
use Slim\Error\Renderers\PlainTextErrorRenderer;
use Slim\Error\Renderers\XmlErrorRenderer;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\ErrorRendererInterface;

class MyErrorHandler extends ErrorHandler
{
    protected $defaultErrorRenderer = MyHtmlErrorRenderer::class;

    /**
     * @var ErrorRendererInterface|string|callable
     */
    protected $logErrorRenderer = PlainTextErrorRenderer::class;

    protected array $errorRenderers = [
        'application/json' => JsonErrorRenderer::class,
        'application/xml' => XmlErrorRenderer::class,
        'text/xml' => XmlErrorRenderer::class,
        'text/html' => MyHtmlErrorRenderer::class,
        'text/plain' => PlainTextErrorRenderer::class,
    ];
}