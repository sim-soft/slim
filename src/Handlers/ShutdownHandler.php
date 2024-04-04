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
        if ($error) {
            $errorFile = $error['file'];
            $errorLine = $error['line'];
            $errorMessage = $error['message'];
            $errorType = $error['type'];
            $message = 'An error while processing your request. Please try again later.';

            if ($this->displayErrorDetails) {
                switch ($errorType) {
                    case E_USER_ERROR:
                        $message = "FATAL ERROR: $errorMessage. ";
                        $message .= " on line $errorLine in file $errorFile.";
                        break;

                    case E_USER_WARNING:
                        $message = "WARNING: $errorMessage";
                        break;

                    case E_USER_NOTICE:
                        $message = "NOTICE: $errorMessage";
                        break;

                    default:
                        $message = "ERROR: $errorMessage";
                        $message .= " on line $errorLine in file $errorFile.";
                        break;
                }
            }

            $exception = new HttpInternalServerErrorException($this->request, $message);
            $response = $this->errorHandler->__invoke($this->request, $exception, $this->displayErrorDetails, false, false);

            if (ob_get_length()) {
                ob_clean();
            }

            $responseEmitter = new ResponseEmitter();
            $responseEmitter->emit($response);
        }
    }
}
