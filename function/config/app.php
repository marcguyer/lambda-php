<?php

declare(strict_types=1);

use Mezzio\Application;

/**
 * Application bootstrap
 *
 * Returns configured Mezzio Application instance
 */

// Load container
$container = require __DIR__ . '/container.php';

// Get application from container (uses ApplicationFactory)
$app = $container->get(Application::class);

// Add routing middleware
$app->pipe(\Mezzio\Router\Middleware\RouteMiddleware::class);
$app->pipe(\Mezzio\Router\Middleware\DispatchMiddleware::class);

// Load routes
(require __DIR__ . '/routes.php')($app);

return $app;
