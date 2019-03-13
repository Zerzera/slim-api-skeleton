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
use Skeleton\Controller\Todo as ControllerTodo;
use Slim\App;

$app->group('/todos', function (App $app) {
    $app->get('', ControllerTodo::class . ':get');
    $app->post('', ControllerTodo::class . ':post');
    $app->delete('/{uid}', ControllerTodo::class . ':delete');
    $app->get('/{uid}', ControllerTodo::class . ':getById');
    $app->map(['PUT', 'PATCH'], '/{uid}', ControllerTodo::class . ':put');
});

