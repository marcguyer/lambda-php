<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime\Core;

/**
 * Abstract base class for Lambda runtime bootstraps
 *
 * Implements the core Lambda Runtime API event loop.
 * Subclasses implement event conversion and handler execution logic.
 */
abstract class Bootstrap
{
    protected RuntimeApi $api;
    protected bool $debug;

    public function __construct(RuntimeApi $api, bool $debug = false)
    {
        $this->api = $api;
        $this->debug = $debug;
    }

    /**
     * Run the Lambda runtime event loop
     */
    public function run(): void
    {
        $this->debugLog('Runtime bootstrap starting');
        $this->debugLog('Runtime components initialized');

        // Main event loop
        while (true) {
            try {
                $this->debugLog('Waiting for invocation');

                // Get next invocation
                $invocation = $this->api->getNextInvocation();
                $requestId = $invocation['requestId'];
                $event = $invocation['event'];

                $this->debugLog('Invocation received', [
                    'request_id' => $requestId,
                    'event_keys' => array_keys($event)
                ]);

                // Set X-Ray tracing if available
                if (!empty($invocation['context']['traceId'])) {
                    putenv("_X_AMZN_TRACE_ID={$invocation['context']['traceId']}");
                }

                try {
                    $invocationStart = microtime(true);

                    // Process the invocation (implemented by subclass)
                    $response = $this->processInvocation($event, $invocation['context']);

                    // Send response
                    $sendStart = microtime(true);
                    $this->api->sendResponse($requestId, $response);
                    $sendTime = (microtime(true) - $sendStart) * 1000;

                    $totalTime = (microtime(true) - $invocationStart) * 1000;
                    $this->debugLog('Response sent', [
                        'send_duration_ms' => round($sendTime, 3),
                        'total_duration_ms' => round($totalTime, 2)
                    ]);

                } catch (\Throwable $error) {
                    $this->debugLog('Request error', [
                        'request_id' => $requestId,
                        'error_type' => get_class($error),
                        'error_message' => $error->getMessage(),
                        'error_file' => $error->getFile() . ':' . $error->getLine()
                    ]);

                    // Log error
                    error_log(sprintf(
                        "[ERROR] Request %s failed: %s in %s:%d\n%s",
                        $requestId,
                        $error->getMessage(),
                        $error->getFile(),
                        $error->getLine(),
                        $error->getTraceAsString()
                    ));

                    // Send error to Lambda
                    $this->api->sendError($requestId, $error);
                }

                $this->debugLog('Invocation complete', [
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ]);

            } catch (\Throwable $error) {
                $this->debugLog('Fatal runtime error', [
                    'error_type' => get_class($error),
                    'error_message' => $error->getMessage(),
                    'error_file' => $error->getFile() . ':' . $error->getLine()
                ]);

                // Fatal error in event loop
                error_log(sprintf(
                    "[FATAL] Runtime error: %s in %s:%d\n%s",
                    $error->getMessage(),
                    $error->getFile(),
                    $error->getLine(),
                    $error->getTraceAsString()
                ));

                // Exit with error code
                exit(1);
            }
        }
    }

    /**
     * Process a Lambda invocation
     *
     * @param array $event The Lambda event
     * @param array $context The Lambda context
     * @return mixed The response to send back to Lambda
     */
    abstract protected function processInvocation(array $event, array $context): mixed;

    /**
     * Log debug message if debug mode is enabled
     */
    protected function debugLog(string $message, array $context = []): void
    {
        if (!$this->debug) {
            return;
        }

        $timestamp = microtime(true);
        $contextStr = $context !== [] ? ' ' . json_encode($context) : '';
        error_log(sprintf("[RUNTIME DEBUG %.3f] %s%s", $timestamp, $message, $contextStr));
    }
}