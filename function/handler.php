<?php

declare(strict_types=1);

/**
 * Lambda Handler Entry Point
 *
 * This file must return an instance of Psr\Http\Server\RequestHandlerInterface.
 * The runtime will call $handler->handle($request) for each Lambda invocation.
 */

// Load function dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Enable runtime debug logging (optional - shows runtime internals timing and execution details in CloudWatch)
// putenv('LAMBDA_RUNTIME_DEBUG=1');

// Load and return the application
// (Mezzio\Application implements RequestHandlerInterface)
return require __DIR__ . '/config/app.php';