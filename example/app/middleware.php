<?php

use Slim\App;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

return function (App $app) {

    $app->add(function (Request $request, Handler $handler) use ($app) {
        $response = $handler->handle($request);
        $existingContent = (string)$response->getBody();

        $response = $app->getResponseFactory()->createResponse();
        $response->getBody()->write('Middleware-BEFORE ' . $existingContent);

        return $response;
    });


    $app->add(function (Request $request, Handler $handler) {
        $response = $handler->handle($request);
        $response->getBody()->write('Middleware-AFTER');
        return $response;
    });
};
