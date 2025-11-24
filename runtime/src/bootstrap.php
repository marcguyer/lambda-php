#!/opt/bin/php
<?php

declare(strict_types=1);

/**
 * AWS Lambda Bootstrap for PHP Runtime
 *
 * This script implements the Lambda Runtime API event loop:
 * 1. Get next invocation event
 * 2. Convert event to PSR-7 request
 * 3. Process through PSR-15 request handler
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
$runtimeAutoload = '/opt/runtime/vendor/autoload.php';

if (file_exists($runtimeAutoload)) {
    require_once $runtimeAutoload;
}

use LambdaPhp\Runtime\Core\RuntimeApi;
use LambdaPhp\Runtime\Http\HttpBootstrap;
use LambdaPhp\Runtime\Function\FunctionBootstrap;
use Psr\Http\Server\RequestHandlerInterface;

// Check if debug mode is enabled
$debug = getenv('LAMBDA_RUNTIME_DEBUG') === '1';

// Initialize Runtime API client
$apiClient = new RuntimeApi($runtimeApi);

// Load application handler
$handlerFile = getenv('LAMBDA_HANDLER') ?: 'handler.php';
$handlerPath = "/var/task/{$handlerFile}";
if (!file_exists($handlerPath)) {
    die("Handler not found at {$handlerPath}\n");
}

$handler = require $handlerPath;

// Detect runtime type based on what the handler returned
if ($handler instanceof RequestHandlerInterface) {
    // HTTP runtime: handler is a PSR-15 request handler
    $bootstrap = new HttpBootstrap($apiClient, $handler, $debug);
} elseif (is_callable($handler)) {
    // Function runtime: handler is a simple callable
    $bootstrap = new FunctionBootstrap($apiClient, $handler, $debug);
} else {
    die("Handler must return either a PSR-15 RequestHandlerInterface or a callable\n");
}

$bootstrap->run();
