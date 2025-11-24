<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime\Tests;

use LambdaPhp\Runtime\Http\ResponseConverter;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\HtmlResponse;
use PHPUnit\Framework\TestCase;

/**
 * Test PSR-7 Response to Lambda response conversion
 *
 * Lambda expects responses in this format:
 * {
 *   "statusCode": 200,
 *   "headers": {...},
 *   "body": "...",
 *   "isBase64Encoded": false
 * }
 */
class ResponseConverterTest extends TestCase
{
    public function testConvertsSimpleResponse(): void
    {
        $psrResponse = new Response\TextResponse('Hello, World!', 200);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertIsArray($lambdaResponse);
        $this->assertEquals(200, $lambdaResponse['statusCode']);
        $this->assertEquals('Hello, World!', $lambdaResponse['body']);
        $this->assertFalse($lambdaResponse['isBase64Encoded']);
    }

    public function testConvertsJsonResponse(): void
    {
        $data = ['message' => 'Success', 'data' => ['id' => 123]];
        $psrResponse = new JsonResponse($data);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertEquals(200, $lambdaResponse['statusCode']);
        $this->assertJsonStringEqualsJsonString(
            json_encode($data),
            $lambdaResponse['body']
        );
        $this->assertEquals('application/json', $lambdaResponse['headers']['content-type']);
    }

    public function testConvertsHtmlResponse(): void
    {
        $html = '<html><body><h1>Hello</h1></body></html>';
        $psrResponse = new HtmlResponse($html);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertEquals(200, $lambdaResponse['statusCode']);
        $this->assertEquals($html, $lambdaResponse['body']);
        $this->assertStringContainsString('text/html', $lambdaResponse['headers']['content-type']);
    }

    public function testHandlesCustomStatusCodes(): void
    {
        $psrResponse = new Response\TextResponse('Created', 201);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertEquals(201, $lambdaResponse['statusCode']);
    }

    public function testHandlesErrorStatusCodes(): void
    {
        $psrResponse = new Response\TextResponse('Not Found', 404);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertEquals(404, $lambdaResponse['statusCode']);
        $this->assertEquals('Not Found', $lambdaResponse['body']);
    }

    public function testConvertsHeaders(): void
    {
        $psrResponse = (new Response\TextResponse('OK'))
            ->withHeader('X-Custom-Header', 'CustomValue')
            ->withHeader('Cache-Control', 'no-cache');

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertArrayHasKey('headers', $lambdaResponse);
        $this->assertEquals('CustomValue', $lambdaResponse['headers']['x-custom-header']);
        $this->assertEquals('no-cache', $lambdaResponse['headers']['cache-control']);
    }

    public function testHandlesMultiValueHeaders(): void
    {
        $psrResponse = (new Response\TextResponse('OK'))
            ->withAddedHeader('Set-Cookie', 'session=abc')
            ->withAddedHeader('Set-Cookie', 'user=123');

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertArrayHasKey('cookies', $lambdaResponse);
        $this->assertIsArray($lambdaResponse['cookies']);
        $this->assertCount(2, $lambdaResponse['cookies']);
    }

    public function testHandlesBinaryContent(): void
    {
        $binaryContent = random_bytes(100);
        $psrResponse = new Response();
        $psrResponse->getBody()->write($binaryContent);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        // Binary content should be base64 encoded
        $this->assertTrue($lambdaResponse['isBase64Encoded']);
        $this->assertEquals(base64_encode($binaryContent), $lambdaResponse['body']);
    }

    public function testDetectsBase64EncodingNeeded(): void
    {
        // Text content should not be base64 encoded
        $textResponse = new Response\TextResponse('Plain text');

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($textResponse);

        $this->assertFalse($lambdaResponse['isBase64Encoded']);

        // Binary content should be base64 encoded
        $binaryResponse = new Response();
        $binaryResponse->getBody()->write("\x00\x01\x02\x03");

        $lambdaResponse = $converter->convertFromResponse($binaryResponse);
        $this->assertTrue($lambdaResponse['isBase64Encoded']);
    }

    public function testHandlesEmptyBody(): void
    {
        $psrResponse = new Response\EmptyResponse(204);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertEquals(204, $lambdaResponse['statusCode']);
        $this->assertEquals('', $lambdaResponse['body']);
    }

    public function testHandlesRedirect(): void
    {
        $psrResponse = new Response\RedirectResponse('/login', 302);

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        $this->assertEquals(302, $lambdaResponse['statusCode']);
        $this->assertEquals('/login', $lambdaResponse['headers']['location']);
    }

    public function testNormalizesHeaderNamesToLowercase(): void
    {
        $psrResponse = (new Response\TextResponse('OK'))
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('X-Custom-Header', 'value');

        $converter = new ResponseConverter();
        $lambdaResponse = $converter->convertFromResponse($psrResponse);

        // Lambda prefers lowercase header names
        $this->assertArrayHasKey('content-type', $lambdaResponse['headers']);
        $this->assertArrayHasKey('x-custom-header', $lambdaResponse['headers']);
    }
}
