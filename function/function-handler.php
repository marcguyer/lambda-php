<?php

declare(strict_types=1);

/**
 * Simple function handler for base runtime (no HTTP overhead)
 *
 * This handler receives raw Lambda events and returns a simple response.
 * Used for benchmarking the base runtime without HTTP conversion overhead.
 */

return function (array $event, array $context): array {
    return [
        'statusCode' => 200,
        'message' => 'Function handler response',
        'timestamp' => time(),
        'requestId' => $context['requestId'] ?? 'unknown',
    ];
};
