<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;

// Load container
$container = require __DIR__ . '/container.php';

// Get application and factory from container
$app = $container->get(Application::class);
$factory = $container->get(MiddlewareFactory::class);

// Setup pipeline
(require __DIR__ . '/pipeline.php')($app, $factory, $container);

// Setup routes
(require __DIR__ . '/routes.php')($app, $factory, $container);

return $app;
