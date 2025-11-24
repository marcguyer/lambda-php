<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime\Tests;

use LambdaPhp\Runtime\Core\RuntimeApi;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Test Lambda Runtime API client
 */
class RuntimeApiClientTest extends TestCase
{
    private string $runtimeApi;

    protected function setUp(): void
    {
        $this->runtimeApi = 'localhost:9001';
    }

    public function testCanBeCreated(): void
    {
        $client = new RuntimeApi($this->runtimeApi);
        $this->assertInstanceOf(RuntimeApi::class, $client);
    }

    public function testConstructorSetsBaseUrl(): void
    {
        $client = new RuntimeApi('127.0.0.1:9001');

        $reflection = new ReflectionClass($client);
        $property = $reflection->getProperty('baseUrl');

        $this->assertEquals('http://127.0.0.1:9001/2018-06-01/runtime', $property->getValue($client));
    }

    public function testGetNextInvocationMethodExists(): void
    {
        $client = new RuntimeApi($this->runtimeApi);
        $this->assertTrue(method_exists($client, 'getNextInvocation'));
    }

    public function testSendResponseMethodExists(): void
    {
        $client = new RuntimeApi($this->runtimeApi);
        $this->assertTrue(method_exists($client, 'sendResponse'));
    }

    public function testSendErrorMethodExists(): void
    {
        $client = new RuntimeApi($this->runtimeApi);
        $this->assertTrue(method_exists($client, 'sendError'));
    }

    public function testParseHeaderStringExtractsHeaders(): void
    {
        $client = new RuntimeApi($this->runtimeApi);

        $headerString = "HTTP/1.1 200 OK\r\n" .
            "Lambda-Runtime-Aws-Request-Id: test-request-123\r\n" .
            "Lambda-Runtime-Deadline-Ms: 1234567890\r\n" .
            "Lambda-Runtime-Invoked-Function-Arn: arn:aws:lambda:us-east-1:123:function:test\r\n" .
            "Lambda-Runtime-Trace-Id: Root=1-abc-def\r\n" .
            "Content-Type: application/json\r\n\r\n";

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('parseHeaderString');

        $headers = $method->invoke($client, $headerString);

        $this->assertEquals('test-request-123', $headers['lambda-runtime-aws-request-id']);
        $this->assertEquals('1234567890', $headers['lambda-runtime-deadline-ms']);
        $this->assertEquals('arn:aws:lambda:us-east-1:123:function:test', $headers['lambda-runtime-invoked-function-arn']);
        $this->assertEquals('Root=1-abc-def', $headers['lambda-runtime-trace-id']);
        $this->assertEquals('application/json', $headers['content-type']);
    }

    public function testParseHeaderStringNormalizesToLowercase(): void
    {
        $client = new RuntimeApi($this->runtimeApi);

        $headerString = "Content-Type: application/json\r\nX-Custom-Header: value\r\n";

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('parseHeaderString');

        $headers = $method->invoke($client, $headerString);

        $this->assertArrayHasKey('content-type', $headers);
        $this->assertArrayHasKey('x-custom-header', $headers);
    }

    public function testFormatStackTraceFormatsException(): void
    {
        $client = new RuntimeApi($this->runtimeApi);

        $exception = new \RuntimeException('Test error');

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('formatStackTrace');

        $trace = $method->invoke($client, $exception);

        $this->assertIsArray($trace);
        // Stack trace should contain file and line information
        foreach ($trace as $frame) {
            $this->assertIsString($frame);
            $this->assertMatchesRegularExpression('/\(\d+\):/', $frame);
        }
    }

    public function testFormatStackTraceHandlesNestedExceptions(): void
    {
        $client = new RuntimeApi($this->runtimeApi);

        $inner = new \InvalidArgumentException('Inner error');
        $outer = new \RuntimeException('Outer error', 0, $inner);

        $reflection = new ReflectionClass($client);
        $method = $reflection->getMethod('formatStackTrace');

        $trace = $method->invoke($client, $outer);

        $this->assertIsArray($trace);
        $this->assertNotEmpty($trace);
    }

    public function testSendErrorAcceptsThrowable(): void
    {
        $client = new RuntimeApi($this->runtimeApi);

        $method = new \ReflectionMethod($client, 'sendError');
        $params = $method->getParameters();

        // Second parameter should accept Throwable|array
        $this->assertEquals('error', $params[1]->getName());
    }

    public function testSendErrorAcceptsArray(): void
    {
        $client = new RuntimeApi($this->runtimeApi);

        $method = new \ReflectionMethod($client, 'sendError');
        $type = $method->getParameters()[1]->getType();

        $this->assertInstanceOf(\ReflectionUnionType::class, $type);
    }
}