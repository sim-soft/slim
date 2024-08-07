<?php

use Slim\App;
use function Simsoft\Slim\response;

return function (App $app) {

    $app->get('', function () {

        response('Hello World!');

    })->setName('home');

    $app->get('/version', function () {

        response('1.0.2');

    })->setName('version');

    $app->get('/welcome/{name}', function (string $name) {

        response("Hello $name!");

    })->setName('display-name');

    $app->get('/redirect', function () {

        response()->redirect('https://www.google.com');

    })->setName('redirect');

    $app->get('/redirect-now', function () {

        response()->redirectNow('https://www.google.com');

    })->setName('redirect');

};
