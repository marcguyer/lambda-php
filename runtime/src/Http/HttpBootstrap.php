<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime\Http;

use LambdaPhp\Runtime\Core\Bootstrap;
use LambdaPhp\Runtime\Core\RuntimeApi;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * HTTP-specific Lambda runtime bootstrap
 *
 * Handles API Gateway events by converting them to PSR-7 requests,
 * processing through a PSR-15 request handler, and converting responses back.
 */
class HttpBootstrap extends Bootstrap
{
    private RequestHandlerInterface $handler;
    private EventConverter $eventConverter;
    private ResponseConverter $responseConverter;

    public function __construct(
        RuntimeApi $api,
        RequestHandlerInterface $handler,
        bool $debug = false
    ) {
        parent::__construct($api, $debug);

        $this->handler = $handler;
        $this->eventConverter = new EventConverter();
        $this->responseConverter = new ResponseConverter();
    }

    protected function processInvocation(array $event, array $context): mixed
    {
        // Convert Lambda event to PSR-7 request
        $conversionStart = microtime(true);
        $request = $this->eventConverter->convertToRequest($event);
        $conversionTime = (microtime(true) - $conversionStart) * 1000;

        $this->debugLog('Event converted to PSR-7', [
            'duration_ms' => round($conversionTime, 3),
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri()
        ]);

        // Process through application handler
        $handlerStart = microtime(true);
        $response = $this->handler->handle($request);
        $handlerTime = (microtime(true) - $handlerStart) * 1000;

        $this->debugLog('Handler executed', [
            'duration_ms' => round($handlerTime, 2),
            'status_code' => $response->getStatusCode()
        ]);

        // Convert PSR-7 response to Lambda format
        $responseConversionStart = microtime(true);
        $lambdaResponse = $this->responseConverter->convertFromResponse($response);
        $responseConversionTime = (microtime(true) - $responseConversionStart) * 1000;

        $this->debugLog('Response converted', [
            'duration_ms' => round($responseConversionTime, 3)
        ]);

        return $lambdaResponse;
    }
}