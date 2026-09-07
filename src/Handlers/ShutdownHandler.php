<?php

namespace Simsoft\Slim\Handlers;

use Slim\Handlers\ErrorHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\ResponseEmitter;

/**
 * ShutdownHandler Class
 */
class ShutdownHandler
{
    /**
     * Error types that terminate the script.
     *
     * Only these produce an error page. Non-fatal errors (notices, warnings,
     * deprecations) leave the already-generated response untouched.
     *
     * @var int
     */
    private const FATAL_ERRORS = E_ERROR
        | E_PARSE
        | E_CORE_ERROR
        | E_CORE_WARNING
        | E_COMPILE_ERROR
        | E_COMPILE_WARNING
        | E_USER_ERROR;

    /**
     * @var Request
     */
    private Request $request;

    /**
     * @var ErrorHandler
     */
    private ErrorHandler $errorHandler;

    /**
     * @var bool
     */
    private bool $displayErrorDetails;

    /**
     * ShutdownHandler constructor.
     *
     * @param Request $request
     * @param ErrorHandler $errorHandler
     * @param bool $displayErrorDetails
     */
    public function __construct(Request $request, ErrorHandler $errorHandler, bool $displayErrorDetails)
    {
        $this->request = $request;
        $this->errorHandler = $errorHandler;
        $this->displayErrorDetails = $displayErrorDetails;
    }

    /**
     * Invoke method
     *
     * @return void
     */
    public function __invoke(): void
    {
        $error = error_get_last();

        // Nothing happened, or only a non-fatal notice/warning/deprecation was
        // raised. The request completed normally: leave its response alone.
        if ($error === null || ($error['type'] & self::FATAL_ERRORS) === 0) {
            return;
        }

        // The response has already been sent to the client. Appending an error
        // page here would corrupt it, so there is nothing useful left to do.
        if (headers_sent()) {
            return;
        }

        $message = 'An error while processing your request. Please try again later.';

        if ($this->displayErrorDetails) {
            $message = $error['type'] === E_USER_ERROR
                ? "FATAL ERROR: {$error['message']}."
                : "ERROR: {$error['message']}";
            $message .= " on line {$error['line']} in file {$error['file']}.";
        }

        $exception = new HttpInternalServerErrorException($this->request, $message);
        $response = $this->errorHandler->__invoke($this->request, $exception, $this->displayErrorDetails, false, false);

        // Discard any partial output so the error page is the only body sent.
        while (ob_get_level() > 0 && ob_get_length() !== false) {
            ob_end_clean();
        }

        $responseEmitter = new ResponseEmitter();
        $responseEmitter->emit($response);
    }
}
