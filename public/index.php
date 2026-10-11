<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

/** @var App\Routing\Router $router */
$router = require APP_ROOT . '/routes/web.php';
$router->dispatch();
