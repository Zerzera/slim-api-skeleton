<?php
declare(strict_types=1);

/*
 * This file is part of the Slim API skeleton package
 *
 * Copyright (c) 2016-2017 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-api-skeleton
 *
 */

use Gofabian\Negotiation\NegotiationMiddleware;
use Micheh\Cache\CacheUtil;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Skeleton\Application\Response\UnauthorizedResponse;
use Skeleton\Domain\Token;
use Slim\Http\Request;
use Slim\Http\Response;
use Tuupola\Middleware\CorsMiddleware;
use Tuupola\Middleware\HttpBasicAuthentication;
use Tuupola\Middleware\JwtAuthentication;

$container = $app->getContainer();

$app->add(function (Request $request, Response $response, callable $next) {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path !== '/' && substr($path, -1) === '/') {
        // permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $uri = $uri->withPath(substr($path, 0, -1));

        if ($request->getMethod() === 'GET') {
            return $response->withRedirect((string)$uri, 301);
        }

        return $next($request->withUri($uri), $response);
    }

    return $next($request, $response);
});

$container[HttpBasicAuthentication::class] = function ($container) {
    return new HttpBasicAuthentication([
        'path'    => '/token',
        'relaxed' => ['127.0.0.1', 'localhost'],
        'error'   => function ($response, $arguments) {
            return new UnauthorizedResponse($arguments['message'], 401);
        },
        'users'   => [
            'test' => 'test',
        ],
    ]);
};

$container[Token::class] = function ($container) {
    return new Token;
};

$container[JwtAuthentication::class] = function (ContainerInterface $container) {
    return new JwtAuthentication([
        'path'      => '/',
        'ignore'    => ['/token', '/info'],
        'secret'    => getenv('JWT_SECRET'),
        'logger'    => $container[Logger::class],
        'attribute' => false,
        'relaxed'   => ['127.0.0.1', 'localhost'],
        'error'     => function ($response, $arguments) {
            return new UnauthorizedResponse($arguments['message'], 401);
        },
        'before'    => function ($request, $arguments) use ($container) {
            $container->get(Token::class)->populate($arguments['decoded']);
        },
    ]);
};

$container[CorsMiddleware::class] = function ($container) {
    return new CorsMiddleware([
        'logger'         => $container[Logger::class],
        'origin'         => ['*'],
        'methods'        => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        'headers.allow'  => ['Authorization', 'If-Match', 'If-Unmodified-Since'],
        'headers.expose' => ['Authorization', 'Etag'],
        'credentials'    => true,
        'cache'          => 60,
        'error'          => function ($request, $response, $arguments) {
            return new UnauthorizedResponse($arguments['message'], 401);
        },
    ]);
};

$container[NegotiationMiddleware::class] = function ($container) {
    return new NegotiationMiddleware([
        'accept' => ['application/json'],
    ]);
};

$app->add(HttpBasicAuthentication::class);
$app->add(JwtAuthentication::class);
$app->add(CorsMiddleware::class);
$app->add(NegotiationMiddleware::class);

$container[CacheUtil::class] = function ($container) {
    return new CacheUtil;
};
