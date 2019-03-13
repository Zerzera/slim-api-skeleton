<?php
declare(strict_types=1);

namespace Skeleton\Controller;

use League\Tactician\CommandBus;
use Micheh\Cache\CacheUtil;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Skeleton\Application\Response\ForbiddenResponse;
use Skeleton\Application\Response\NotFoundResponse;
use Skeleton\Application\Response\PreconditionFailedResponse;
use Skeleton\Application\Response\PreconditionRequiredResponse;
use Skeleton\Application\Todo\CreateTodoCommand;
use Skeleton\Application\Todo\DeleteTodoCommand;
use Skeleton\Application\Todo\LatestTodoQuery;
use Skeleton\Application\Todo\ReadTodoCollectionQuery;
use Skeleton\Application\Todo\ReadTodoQuery;
use Skeleton\Application\Todo\ReplaceTodoCommand;
use Skeleton\Application\Todo\TodoNotFoundException;
use Skeleton\Application\Todo\TransformTodoCollectionService;
use Skeleton\Application\Todo\TransformTodoService;
use Skeleton\Application\Todo\UpdateTodoCommand;
use Skeleton\Domain\TodoRepository;
use Skeleton\Domain\TodoUid;
use Skeleton\Domain\Token;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Class Todo
 */
class Todo
{

    /**
     * @var ContainerInterface
     */
    private $c;
    /**
     * @var CommandBus
     */
    private $commandBus;
    /**
     * @var CacheUtil
     */
    private $cache;
    /**
     * @var Token
     */
    private $token;
    /**
     * @var TransformTodoService
     */
    private $transformTodoService;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->c = $container;
        $this->commandBus = $container->get(CommandBus::class);
        $this->cache = $container->get(CacheUtil::class);
        $this->token = $container->get(Token::class);
        $this->transformTodoService = $container->get(TransformTodoService::class);
    }

    /**
     * @param Request    $request
     * @param Response   $response
     * @param array|null $arguments
     *
     * @return ResponseInterface
     */
    public function get($request, $response, ?array $arguments): ResponseInterface
    {
        if (false === $this->token->hasScope(['todo.all', 'todo.list'])) {
            return new ForbiddenResponse('Token not allowed to list todos', 403);
        }

        /* Add Last-Modified and ETag headers to response when at least one todo exists. */
        $this->commandBus = $this->c->get(CommandBus::class);
        try {
            $query = new LatestTodoQuery;
            $first = $this->commandBus->handle($query);
            $response = $this->cache->withETag($response, $first->etag());
            $response = $this->cache->withLastModified($response, $first->timestamp());

        } catch (TodoNotFoundException $exception) {
        }

        /* If-Modified-Since and If-None-Match request header handling. */
        /* Heads up! Apache removes previously set Last-Modified header */
        /* from 304 Not Modified responses. */
        if ($this->cache->isNotModified($request, $response)) {
            return $response->withStatus(304);
        }

        /* Serialize the response. */
        $query = new ReadTodoCollectionQuery;
        $todos = $this->commandBus->handle($query);
        $data = $this->c->get(TransformTodoCollectionService::class)->execute($todos);
        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ;
    }

    /**
     * @param Request    $request
     * @param Response   $response
     * @param array|null $arguments
     *
     * @return ResponseInterface
     */
    public function getById($request, $response, ?array $arguments): ResponseInterface
    {
        /* Check if token has needed scope. */
        if (false === $this->token->hasScope(['todo.all', 'todo.read'])) {
            return new ForbiddenResponse('Token not allowed to read todos', 403);
        }

        $uid = new TodoUid($arguments['uid']);

        /* Load existing todo using provided uid. */
        try {
            $query = new ReadTodoQuery($uid);
            $todo = $this->commandBus->handle($query);
        } catch (TodoNotFoundException $exception) {
            return new NotFoundResponse('Todo not found', 404);
        }

        /* Add Last-Modified and ETag headers to response. */
        $response = $this->cache->withETag($response, $todo->etag());
        $response = $this->cache->withLastModified($response, $todo->timestamp());

        /* If-Modified-Since and If-None-Match request header handling. */
        /* Heads up! Apache removes previously set Last-Modified header */
        /* from 304 Not Modified responses. */
        if ($this->cache->isNotModified($request, $response)) {
            return $response->withStatus(304);
        }

        /* Serialize the response. */
        $data = $this->transformTodoService->execute($todo);

        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ;
    }

    /**
     * @param Request    $request
     * @param Response   $response
     * @param array|null $arguments
     *
     * @return ResponseInterface
     */
    public function post(Request $request, Response $response, ?array $arguments): ResponseInterface
    {
        /* Check if token has needed scope. */
        if (false === $this->token->hasScope(['todo.all', 'todo.create'])) {
            return new ForbiddenResponse('Token not allowed to create todos', 403);
        }

        $data = $request->getParsedBody();
        $uid = $this->c->get(TodoRepository::class)->nextIdentity();

        $command = new CreateTodoCommand(
            $uid,
            $data['title'],
            $data['order']
        );
        $this->commandBus->handle($command);

        $query = new ReadTodoQuery($uid);
        $todo = $this->commandBus->handle($query);

        /* Add Last-Modified and ETag headers to response. */
        $response = $this->cache->withETag($response, $todo->etag());
        $response = $this->cache->withLastModified($response, $todo->timestamp());

        /* Serialize the response. */
        $data = $this->transformTodoService->execute($todo);

        return $response->withStatus(201)
                        ->withHeader('Content-Type', 'application/json')
                        ->withHeader('Content-Location', $data['data']['links']['self'])
                        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ;
    }

    /**
     * @param Request    $request
     * @param Response   $response
     * @param array|null $arguments
     *
     * @return ResponseInterface
     */
    public function delete($request, $response, $arguments): ResponseInterface
    {
        /* Check if token has needed scope. */
        if (false === $this->token->hasScope(['todo.all', 'todo.delete'])) {
            return new ForbiddenResponse('Token not allowed to delete todos', 403);
        }

        $uid = new TodoUid($arguments['uid']);

        try {
            $command = new DeleteTodoCommand($uid);
            $this->commandBus->handle($command);
        } catch (TodoNotFoundException $exception) {
            return new NotFoundResponse('Todo not found', 404);
        }

        return $response->withStatus(204);
    }

    /**
     * @param Request    $request
     * @param Response   $response
     * @param array|null $arguments
     *
     * @return ResponseInterface
     */
    public function put($request, $response, $arguments): ResponseInterface
    {
        /* Check if token has needed scope. */
        if (false === $this->token->hasScope(['todo.all', 'todo.update'])) {
            return new ForbiddenResponse('Token not allowed to update todos', 403);
        }

        $uid = new TodoUid($arguments['uid']);

        /* Load existing todo using provided uid. */
        try {
            $query = new ReadTodoQuery($uid);
            /** @var \Skeleton\Domain\Todo $todo */
            $todo = $this->commandBus->handle($query);
        } catch (TodoNotFoundException $exception) {
            return new NotFoundResponse('Todo not found', 404);
        }

        /* PATCH requires If-Unmodified-Since or If-Match request header to be present. */
        if (false === $this->cache->hasStateValidator($request)) {
            $method = strtoupper($request->getMethod());
            return new PreconditionRequiredResponse("{$method} request is required to be conditional", 428);
        }

        /* If-Unmodified-Since and If-Match request header handling. If in the meanwhile  */
        /* someone has modified the todo respond with 412 Precondition Failed. */
        if (true === $this->cache->hasCurrentState($request, $todo->etag(), $todo->timestamp())) {
            return new PreconditionFailedResponse('Todo has already been modified', 412);
        }

        $data = $request->getParsedBody();

        /* PUT request assumes full representation. PATCH allows partial data. */
        if ('PUT' === strtoupper($request->getMethod())) {
            $command = new ReplaceTodoCommand(
                $uid,
                $data['title'],
                $data['order'],
                $data['completed']
            );
        } else {
            $command = new UpdateTodoCommand(
                $uid,
                $data['title'] ?? $todo->title(),
                $data['order'] ?? $todo->order(),
                $data['completed'] ?? $todo->isCompleted()
            );
        }
        $this->commandBus->handle($command);

        $query = new ReadTodoQuery($uid);
        $todo = $this->commandBus->handle($query);

        /* Add Last-Modified and ETag headers to response. */
        $response = $this->cache->withETag($response, $todo->etag());
        $response = $this->cache->withLastModified($response, $todo->timestamp());

        $data = $this->transformTodoService->execute($todo);

        return $response->withStatus(200)
                        ->withHeader('Content-Type', 'application/json')
                        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
            ;
    }
}
