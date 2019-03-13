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
use League\Tactician\CommandBus;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\Locator\InMemoryLocator;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Skeleton\Application\Todo\CreateTodoCommand;
use Skeleton\Application\Todo\CreateTodoHandler;
use Skeleton\Application\Todo\DeleteTodoCommand;
use Skeleton\Application\Todo\DeleteTodoHandler;
use Skeleton\Application\Todo\LatestTodoHandler;
use Skeleton\Application\Todo\LatestTodoQuery;
use Skeleton\Application\Todo\ReadTodoCollectionHandler;
use Skeleton\Application\Todo\ReadTodoCollectionQuery;
use Skeleton\Application\Todo\ReadTodoHandler;
use Skeleton\Application\Todo\ReadTodoQuery;
use Skeleton\Application\Todo\ReplaceTodoCommand;
use Skeleton\Application\Todo\ReplaceTodoHandler;
use Skeleton\Application\Todo\TransformTodoCollectionService;
use Skeleton\Application\Todo\TransformTodoService;
use Skeleton\Application\Todo\UpdateTodoCommand;
use Skeleton\Application\Todo\UpdateTodoHandler;
use Skeleton\Domain\TodoRepository;
use Skeleton\Infrastructure\ZendTodoRepository;
use Slim\Container;

$container = $app->getContainer();

$container[CommandBus::class] = function (Container $container) {
    $inflector = new HandleInflector();
    $locator = new InMemoryLocator();
    $repository = $container->get(TodoRepository::class);

    $locator->addHandler(new CreateTodoHandler($repository), CreateTodoCommand::class);
    $locator->addHandler(new ReadTodoHandler($repository), ReadTodoQuery::class);
    $locator->addHandler(new ReadTodoCollectionHandler($repository), ReadTodoCollectionQuery::class);
    $locator->addHandler(new LatestTodoHandler($repository), LatestTodoQuery::class);
    $locator->addHandler(new DeleteTodoHandler($repository), DeleteTodoCommand::class);
    $locator->addHandler(new UpdateTodoHandler($repository), UpdateTodoCommand::class);
    $locator->addHandler(new ReplaceTodoHandler($repository), ReplaceTodoCommand::class);

    $nameExtractor = new ClassNameExtractor();
    $commandHandlerMiddleware = new CommandHandlerMiddleware($nameExtractor,$locator,$inflector);
    return new CommandBus([$commandHandlerMiddleware]);
};

$container[TodoRepository::class] = function () {

    return new ZendTodoRepository([
        'driver'   => 'Mysqli',
        'database' => getenv('DB_NAME'),
        'username' => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
        'hostname' => getenv('DB_HOST'),
        'charset'  => 'utf8',
    ]);
};

$container[TransformTodoService::class] = function () {
    return new TransformTodoService;
};

$container[TransformTodoCollectionService::class] = function () {
    return new TransformTodoCollectionService;
};

$container[Logger::class] = function () {
    $logger = new Logger('slim');

    $formatter = new LineFormatter(
        "[%datetime%] [%level_name%]: %message% %context%\n",
        null,
        true,
        true
    );

    /* Log to timestamped files */
    $rotating = new RotatingFileHandler(__DIR__ . '/../logs/slim.log', 0, Logger::DEBUG);
    $rotating->setFormatter($formatter);
    $logger->pushHandler($rotating);

    return $logger;
};
