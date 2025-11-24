<?php

declare(strict_types=1);

use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

/**
 * Application configuration
 */

// Aggregate configuration from ConfigProviders
$aggregator = new Laminas\ConfigAggregator\ConfigAggregator([
    \Mezzio\ConfigProvider::class,
    \Mezzio\Router\ConfigProvider::class,
    \Mezzio\Router\FastRouteRouter\ConfigProvider::class,
]);

$config = $aggregator->getMergedConfig();

// Merge application-specific configuration
$config = array_merge_recursive($config, [
    'dependencies' => [
        'invokables' => [
            // Add invokable services here
        ],
        'factories' => [
            App\Handler\HealthHandler::class => ReflectionBasedAbstractFactory::class,
            App\Handler\UserHandler::class => ReflectionBasedAbstractFactory::class,
        ],
        'aliases' => [
            // Add service aliases here
        ],
    ],
]);

return $config;
