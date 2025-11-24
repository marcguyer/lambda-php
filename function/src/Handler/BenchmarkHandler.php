<?php

declare(strict_types=1);

namespace App\Handler;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Benchmark handler with realistic HTTP API work
 *
 * Simulates typical web API operations:
 * - Request parsing
 * - Data processing
 * - JSON response generation
 */
class BenchmarkHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $startTime = microtime(true);

        // Simulate fetching 10 entities from database (typical REST API response)
        // Note: removed usleep to measure pure CPU performance
        $entities = [];
        for ($i = 0; $i < 10; $i++) {
            $entities[] = [
                'id' => $i + 1,
                'name' => 'Entity ' . ($i + 1),
                'description' => 'Some description text for entity ' . ($i + 1),
                'status' => 'active',
                'created_at' => date('c'),
                'updated_at' => date('c'),
                'metadata' => [
                    'key1' => 'value1',
                    'key2' => 'value2',
                ],
                // HAL links
                '_links' => [
                    'self' => ['href' => '/api/entities/' . ($i + 1)],
                    'collection' => ['href' => '/api/entities'],
                ],
            ];
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Return JSON response with HAL
        return new JsonResponse([
            '_embedded' => [
                'items' => $entities,
            ],
            'total' => count($entities),
            'page' => 1,
            'per_page' => 10,
            '_links' => [
                'self' => ['href' => '/api/entities?page=1'],
                'first' => ['href' => '/api/entities?page=1'],
                'last' => ['href' => '/api/entities?page=1'],
            ],
            'benchmark' => [
                'duration_ms' => $duration,
            ],
        ]);
    }
}
