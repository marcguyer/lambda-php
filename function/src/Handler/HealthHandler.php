<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Health check handler
 *
 * Returns system health status including PHP version and loaded extensions
 */
class HealthHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'status' => 'healthy',
            'php_version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'timestamp' => time(),
        ]);
    }
}
