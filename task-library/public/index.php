<?php

declare(strict_types=1);

use App\Controller\TaskController;
use App\Core\Router;

require dirname(__DIR__) . '/autoload.php';

$router = new Router();
$controller = new TaskController();

$router->add('GET', '/', static fn () => $controller->index());
$router->add('POST', '/tasks', static fn () => $controller->store());
$router->add('POST', '/tasks/{id}/toggle', static fn (?int $id) => $controller->toggle((int) $id));
$router->add('POST', '/tasks/{id}/delete', static fn (?int $id) => $controller->destroy((int) $id));
$router->add('GET', '/audit', static fn () => $controller->audit());

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
