<?php

declare(strict_types=1);

// Aggregate configuration from ConfigProviders
$aggregator = new Laminas\ConfigAggregator\ConfigAggregator([
    \Mezzio\ConfigProvider::class,
    \Mezzio\Router\ConfigProvider::class,
    \Mezzio\Router\FastRouteRouter\ConfigProvider::class,
    \App\ConfigProvider::class,
]);

return $aggregator->getMergedConfig();
