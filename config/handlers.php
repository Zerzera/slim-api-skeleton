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

use Monolog\Logger;
use Skeleton\Infrastructure\Slim\Handler\ApiErrorHandler;
use Skeleton\Infrastructure\Slim\Handler\NotFoundHandler;

$container = $app->getContainer();

$container[ApiErrorHandler::class] = function ($container) {
    return new ApiErrorHandler($container[Logger::class]);
};

$container['phpErrorHandler'] = function ($container) {
    return $container[ApiErrorHandler::class];
};

$container[NotFoundHandler::class] = function () {
    return new NotFoundHandler;
};
