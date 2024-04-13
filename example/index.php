<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Example\ErrorHandler\MyErrorHandler;
use Simsoft\Slim\Route;
use Simsoft\Slim\URL;

Route::make()
    ->withBasePath('/simsoft/slim/example/')
    ->withErrorHandler(true, true, true, errorHandlerClass: MyErrorHandler::class)
    ->withMiddleware(require_once 'app/middleware.php')
    ->withRouting(
        routes: require_once 'app/routes.php',
        cachePath: 'routes.cache'
    )
    ->run();


var_dump(
    URL::for('version'),
    URL::for('display-name', ['name' => 'william']),
    URL::fullFor('display-name', ['name' => 'william']),
);
