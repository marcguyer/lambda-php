<?php

declare(strict_types=1);

/**
 * Benchmark function handler with realistic CPU work
 *
 * Simulates real-world Lambda work:
 * - JSON parsing/encoding
 * - String manipulation
 * - Array operations
 * - Mathematical computation
 */

return function (array $event, array $context): array {
    $startTime = microtime(true);

    // Simulate fetching 10 entities from database
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
        ];
    }

    // Encode to JSON (what you actually return)
    $responseBody = json_encode([
        'items' => $entities,
        'total' => count($entities),
        'page' => 1,
        'per_page' => 10,
    ]);

    $duration = round((microtime(true) - $startTime) * 1000, 2);

    return [
        'statusCode' => 200,
        'message' => 'Query complete',
        'duration_ms' => $duration,
        'response_size' => strlen($responseBody),
        'body' => $responseBody,
        'requestId' => $context['requestId'] ?? 'unknown',
    ];
};
