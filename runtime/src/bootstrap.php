#!/opt/bin/php
<?php

declare(strict_types=1);

/**
 * AWS Lambda Bootstrap for PHP Runtime
 *
 * This script implements the Lambda Runtime API event loop:
 * 1. Get next invocation event
 * 2. Convert event to PSR-7 request
 * 3. Process through Mezzio application
 * 4. Convert PSR-7 response to Lambda format
 * 5. Send response back to Lambda
 * 6. Repeat
 *
 * @see https://docs.aws.amazon.com/lambda/latest/dg/runtimes-custom.html
 */

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't output errors to stdout/stderr
ini_set('log_errors', '1');

// Ensure we're running in Lambda
$runtimeApi = getenv('AWS_LAMBDA_RUNTIME_API');
if (empty($runtimeApi)) {
    die("AWS_LAMBDA_RUNTIME_API environment variable is not set\n");
}

// Load runtime classes
// In production, runtime classes are in /opt/runtime
// In development, they might be in vendor/
$runtimeAutoload = '/opt/runtime/vendor/autoload.php';
$functionAutoload = '/var/task/vendor/autoload.php';

if (file_exists($runtimeAutoload)) {
    require_once $runtimeAutoload;
}

if (!file_exists($functionAutoload)) {
    die("Function autoloader not found at {$functionAutoload}\n");
}
require_once $functionAutoload;

use LambdaPhp\Runtime\RuntimeApiClient;
use LambdaPhp\Runtime\EventConverter;
use LambdaPhp\Runtime\ResponseConverter;

// Initialize components
$apiClient = new RuntimeApiClient($runtimeApi);
$eventConverter = new EventConverter();
$responseConverter = new ResponseConverter();

// Load Mezzio application
$appPath = '/var/task/config/app.php';
if (!file_exists($appPath)) {
    die("Mezzio application not found at {$appPath}\n");
}

/** @var \Mezzio\Application $app */
$app = require $appPath;

// Main event loop
while (true) {
    try {
        // Get next invocation
        $invocation = $apiClient->getNextInvocation();
        $requestId = $invocation['requestId'];
        $event = $invocation['event'];

        // Set X-Ray tracing if available
        if (!empty($invocation['context']['traceId'])) {
            putenv("_X_AMZN_TRACE_ID={$invocation['context']['traceId']}");
        }

        try {
            // Convert Lambda event to PSR-7 request
            $request = $eventConverter->convertToRequest($event);

            // Process through Mezzio
            $response = $app->handle($request);

            // Convert PSR-7 response to Lambda format
            $lambdaResponse = $responseConverter->convertFromResponse($response);

            // Send response
            $apiClient->sendResponse($requestId, $lambdaResponse);

        } catch (\Throwable $error) {
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
            $apiClient->sendError($requestId, $error);
        }

    } catch (\Throwable $error) {
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
