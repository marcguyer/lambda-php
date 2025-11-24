<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime\Function;

use LambdaPhp\Runtime\Core\Bootstrap;
use LambdaPhp\Runtime\Core\RuntimeApi;

/**
 * Simple function-type Lambda runtime bootstrap
 *
 * Handles raw Lambda events without HTTP conversion overhead.
 * The handler receives the raw event array and should return the response.
 */
class FunctionBootstrap extends Bootstrap
{
    /** @var callable */
    private $handler;

    public function __construct(
        RuntimeApi $api,
        callable $handler,
        bool $debug = false
    ) {
        parent::__construct($api, $debug);
        $this->handler = $handler;
    }

    protected function processInvocation(array $event, array $context): mixed
    {
        $handlerStart = microtime(true);

        // Call the handler with the raw event and context
        $response = ($this->handler)($event, $context);

        $handlerTime = (microtime(true) - $handlerStart) * 1000;

        $this->debugLog('Handler executed', [
            'duration_ms' => round($handlerTime, 2)
        ]);

        return $response;
    }
}
