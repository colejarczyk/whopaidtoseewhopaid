<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Application;

//Request::setTrustedProxies(array('127.0.0.1'));

/** @var Application $app */

// main page
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html.twig', []);
})->bind('homepage');

// list who paid
$app->get('/list/{token}', function ($token) use ($app) {
    return $app['twig']->render('list.html.twig', ['token' => $token]);
})->bind('list');

// error handler
$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/'.$code.'.html.twig',
        'errors/'.substr($code, 0, 2).'x.html.twig',
        'errors/'.substr($code, 0, 1).'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
