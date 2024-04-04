<?php

namespace Simsoft\Slim;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Simsoft\Slim\Handlers\ShutdownHandler;
use Simsoft\Slim\Handlers\Strategies\Args;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;
use Slim\Handlers\ErrorHandler;
use Slim\ResponseEmitter;

/**
 * Route class
 */
class Route
{
    /** @var App Slim app object. */
    protected App $app;

    /** @var ServerRequestInterface|null Request */
    protected ?ServerRequestInterface $request = null;

    /**
     * Constructor.
     *
     * @param ContainerInterface|null $container
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->app = AppFactory::create(container: $container);
        $this->app->addBodyParsingMiddleware();
        $this->app->addRoutingMiddleware();
    }

    public static function make(?ContainerInterface $container = null): static
    {
        return new static($container);
    }

    /**
     * Set domain for the route.
     *
     * @param string $domain Domain name.
     * @return $this
     */
    public function withDomain(string $domain): static
    {
        URL::setDomain($domain);
        return $this;
    }

    /**
     * Set base path.
     *
     * @param string $path
     * @return $this
     */
    public function withBasePath(string $path): static
    {
        $this->app->setBasePath($path);
        return $this;
    }

    /**
     * Set middleware.
     *
     * @param callable $middleware
     * @return $this
     */
    public function withMiddleware(callable $middleware): static
    {
        $middleware($this->app);
        return $this;
    }

    /**
     * Set routes.
     *
     * @param callable $routes
     * @param string|null $cachePath Set routes cache file path.
     * @param string $invocationStrategy Set invocation strategy.
     * @return $this
     */
    public function withRouting(callable $routes, ?string $cachePath = null, string $invocationStrategy = Args::class): static
    {
        $this->app->getRouteCollector()->setDefaultInvocationStrategy(new $invocationStrategy());

        $routes($this->app);
        URL::setParser($this->app->getRouteCollector()->getRouteParser());

        if ($cachePath) {
            $this->app->getRouteCollector()->setCacheFile($cachePath);
        }

        return $this;
    }

    /**
     * Get request.
     *
     * @return ServerRequestInterface|null
     */
    public function getRequest(): ?ServerRequestInterface
    {
        if ($this->request === null) {
            $this->request = ServerRequestCreatorFactory::create()->createServerRequestFromGlobals();
        }
        return $this->request;
    }

    /**
     * Enable error handler.
     *
     * @param bool $displayError Display error. Default: false.
     * @param bool $logError Log error. Default: false.
     * @param bool $logErrorDetails Log error details. Default: false.
     * @param LoggerInterface|null $logger Set error logger. Default: null.
     * @return $this
     */
    public function withErrorHandler(
        bool             $displayError = false,
        bool             $logError = false,
        bool             $logErrorDetails = false,
        ?LoggerInterface $logger = null,
        string           $errorHandlerClass = ErrorHandler::class,
        string           $shutdownHandlerClass = ShutdownHandler::class
    ): static
    {
        // Create Shutdown Handler
        register_shutdown_function(
            new $shutdownHandlerClass($this->getRequest(),

                // Add Error Middleware
                $this->app->addErrorMiddleware($displayError, $logError, $logErrorDetails, $logger)
                    // Create Error Handler
                    ->setDefaultErrorHandler(
                        new $errorHandlerClass($this->app->getCallableResolver(), $this->app->getResponseFactory())
                    )
                    ->getDefaultErrorHandler(),

                $displayError
            )
        );
        return $this;
    }

    /**
     * Handling request and response.
     *
     * @return void
     */
    public function run(): void
    {
        // Run App & Emit Response
        (new ResponseEmitter())->emit($this->app->handle($this->getRequest()));
    }
}
