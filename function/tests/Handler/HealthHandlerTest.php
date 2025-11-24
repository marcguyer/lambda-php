<?php

declare(strict_types=1);

namespace AppTest\Handler;

use App\Handler\HealthHandler;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;

class HealthHandlerTest extends TestCase
{
    public function testReturnsHealthyStatus(): void
    {
        $handler = new HealthHandler();
        $request = new ServerRequest();

        $response = $handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReturnsJsonContent(): void
    {
        $handler = new HealthHandler();
        $request = new ServerRequest();

        $response = $handler->handle($request);

        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testIncludesPhpVersion(): void
    {
        $handler = new HealthHandler();
        $request = new ServerRequest();

        $response = $handler->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('php_version', $body);
        $this->assertEquals(PHP_VERSION, $body['php_version']);
    }

    public function testIncludesExtensions(): void
    {
        $handler = new HealthHandler();
        $request = new ServerRequest();

        $response = $handler->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('extensions', $body);
        $this->assertIsArray($body['extensions']);
    }

    public function testIncludesStatus(): void
    {
        $handler = new HealthHandler();
        $request = new ServerRequest();

        $response = $handler->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        $this->assertArrayHasKey('status', $body);
        $this->assertEquals('healthy', $body['status']);
    }
}
