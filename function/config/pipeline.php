<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * Middleware pipeline configuration
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    $app->pipe(\Mezzio\Router\Middleware\RouteMiddleware::class);
    $app->pipe(\Mezzio\Router\Middleware\DispatchMiddleware::class);
    $app->pipe(\Mezzio\Handler\NotFoundHandler::class);
};