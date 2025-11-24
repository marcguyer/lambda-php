<?php

declare(strict_types=1);

use Mezzio\Application;
use Mezzio\MiddlewareFactory;
use Psr\Container\ContainerInterface;

/**
 * Route configuration
 */
return function (Application $app, MiddlewareFactory $factory, ContainerInterface $container): void {
    // Health check endpoint
    $app->get('/health', App\Handler\HealthHandler::class, 'health');

    // Benchmark endpoint for load testing
    $app->get('/benchmark', App\Handler\BenchmarkHandler::class, 'benchmark');
};
