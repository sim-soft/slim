<?php

/**
 * Fixture driven by ShutdownHandlerTest.
 *
 * Registers the real ShutdownHandler, emits a normal response body, then
 * triggers the error severity named by $argv[1]. The test inspects stdout to
 * confirm whether an error page was appended.
 *
 * Run with display_errors=0 to emulate a production web SAPI, where PHP does
 * not print the fatal itself and headers are therefore not yet sent.
 */

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Simsoft\Slim\Handlers\ShutdownHandler;
use Slim\Factory\AppFactory;
use Slim\Handlers\ErrorHandler;
use Slim\Psr7\Factory\ServerRequestFactory;

$app = AppFactory::create();
$request = (new ServerRequestFactory())->createServerRequest('GET', '/');
$errorHandler = new ErrorHandler($app->getCallableResolver(), $app->getResponseFactory());

register_shutdown_function(new ShutdownHandler($request, $errorHandler, false));

$scenario = $argv[1] ?? 'clean';

// Scenarios suffixed with "-unsent" emit nothing before the error, so headers
// are still unsent when shutdown runs. That isolates the fatal-severity gate
// from the headers_sent() guard: only the severity check can suppress the
// error page in those cases.
$emitBody = !str_ends_with($scenario, '-unsent');
$scenario = $emitBody ? $scenario : substr($scenario, 0, -strlen('-unsent'));

// The response the application already produced.
if ($emitBody && $scenario !== 'fatal') {
    ob_start();
    echo "NORMAL RESPONSE BODY\n";
    ob_end_flush();
}

switch ($scenario) {
    case 'deprecation':
        @trigger_error('harmless deprecation', E_USER_DEPRECATED);
        break;

    case 'notice':
        @trigger_error('harmless notice', E_USER_NOTICE);
        break;

    case 'warning':
        @trigger_error('harmless warning', E_USER_WARNING);
        break;

    case 'undefined-index':
        $empty = [];
        @$empty['missing'];
        break;

    case 'fatal':
        require __DIR__ . '/this-file-does-not-exist.php';
        break;

    case 'clean':
    default:
        break;
}
