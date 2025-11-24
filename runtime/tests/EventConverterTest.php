<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime\Tests;

use LambdaPhp\Runtime\Http\EventConverter;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test Lambda Event to PSR-7 ServerRequest conversion
 *
 * API Gateway sends events in this format (payload version 2.0):
 * {
 *   "version": "2.0",
 *   "routeKey": "GET /health",
 *   "rawPath": "/health",
 *   "rawQueryString": "foo=bar",
 *   "headers": {...},
 *   "requestContext": {...},
 *   "body": "...",
 *   "isBase64Encoded": false
 * }
 */
class EventConverterTest extends TestCase
{
    public function testConvertsSimpleGetRequest(): void
    {
        $event = [
            'version' => '2.0',
            'routeKey' => 'GET /health',
            'rawPath' => '/health',
            'rawQueryString' => '',
            'headers' => [
                'host' => 'example.com',
                'user-agent' => 'test',
            ],
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/health',
                ],
            ],
            'isBase64Encoded' => false,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $this->assertInstanceOf(ServerRequestInterface::class, $request);
        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/health', $request->getUri()->getPath());
        $this->assertEquals('example.com', $request->getHeaderLine('host'));
    }

    public function testConvertsPostRequestWithBody(): void
    {
        $event = [
            'version' => '2.0',
            'routeKey' => 'POST /api/users',
            'rawPath' => '/api/users',
            'rawQueryString' => '',
            'headers' => [
                'content-type' => 'application/json',
            ],
            'requestContext' => [
                'http' => [
                    'method' => 'POST',
                    'path' => '/api/users',
                ],
            ],
            'body' => '{"name":"John Doe"}',
            'isBase64Encoded' => false,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('{"name":"John Doe"}', (string) $request->getBody());
        $this->assertEquals('application/json', $request->getHeaderLine('content-type'));
    }

    public function testHandlesBase64EncodedBody(): void
    {
        $originalBody = 'Binary content here';
        $event = [
            'version' => '2.0',
            'routeKey' => 'POST /upload',
            'rawPath' => '/upload',
            'rawQueryString' => '',
            'headers' => [],
            'requestContext' => [
                'http' => [
                    'method' => 'POST',
                    'path' => '/upload',
                ],
            ],
            'body' => base64_encode($originalBody),
            'isBase64Encoded' => true,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $this->assertEquals($originalBody, (string) $request->getBody());
    }

    public function testParsesQueryString(): void
    {
        $event = [
            'version' => '2.0',
            'routeKey' => 'GET /search',
            'rawPath' => '/search',
            'rawQueryString' => 'q=test&page=2&limit=10',
            'headers' => [],
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/search',
                ],
            ],
            'isBase64Encoded' => false,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $queryParams = $request->getQueryParams();
        $this->assertEquals('test', $queryParams['q']);
        $this->assertEquals('2', $queryParams['page']);
        $this->assertEquals('10', $queryParams['limit']);
    }

    public function testIncludesPathParameters(): void
    {
        $event = [
            'version' => '2.0',
            'routeKey' => 'GET /users/{id}',
            'rawPath' => '/users/123',
            'rawQueryString' => '',
            'headers' => [],
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/users/123',
                ],
            ],
            'pathParameters' => [
                'id' => '123',
            ],
            'isBase64Encoded' => false,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $attributes = $request->getAttributes();
        $this->assertArrayHasKey('pathParameters', $attributes);
        $this->assertEquals('123', $attributes['pathParameters']['id']);
    }

    public function testPreservesLambdaContext(): void
    {
        $event = [
            'version' => '2.0',
            'routeKey' => 'GET /test',
            'rawPath' => '/test',
            'rawQueryString' => '',
            'headers' => [],
            'requestContext' => [
                'requestId' => 'abc-123',
                'http' => [
                    'method' => 'GET',
                    'path' => '/test',
                ],
            ],
            'isBase64Encoded' => false,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $attributes = $request->getAttributes();
        $this->assertArrayHasKey('lambda', $attributes);
        $this->assertEquals('abc-123', $attributes['lambda']['requestContext']['requestId']);
    }

    public function testHandlesMultiValueHeaders(): void
    {
        $event = [
            'version' => '2.0',
            'routeKey' => 'GET /test',
            'rawPath' => '/test',
            'rawQueryString' => '',
            'headers' => [
                'accept' => 'text/html,application/json',
                'cookie' => 'session=abc; user=123',
            ],
            'requestContext' => [
                'http' => [
                    'method' => 'GET',
                    'path' => '/test',
                ],
            ],
            'isBase64Encoded' => false,
        ];

        $converter = new EventConverter();
        $request = $converter->convertToRequest($event);

        $this->assertStringContainsString('text/html', $request->getHeaderLine('accept'));
        $this->assertStringContainsString('session=abc', $request->getHeaderLine('cookie'));
    }
}
