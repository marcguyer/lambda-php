<?php

declare(strict_types=1);

use Mezzio\Application;

/**
 * Route configuration
 *
 * @param Application $app
 */
return function (Application $app): void {
    // Health check endpoint
    $app->get('/health', App\Handler\HealthHandler::class);
};
