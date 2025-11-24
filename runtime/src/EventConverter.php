<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Converts AWS Lambda API Gateway events to PSR-7 ServerRequest
 *
 * Supports API Gateway HTTP API (payload version 2.0)
 *
 * @see https://docs.aws.amazon.com/apigateway/latest/developerguide/http-api-develop-integrations-lambda.html
 */
class EventConverter
{
    /**
     * Convert Lambda event to PSR-7 ServerRequest
     */
    public function convertToRequest(array $event): ServerRequestInterface
    {
        $method = $event['requestContext']['http']['method'] ?? 'GET';
        $path = $event['rawPath'] ?? '/';
        $queryString = $event['rawQueryString'] ?? '';
        $headers = $event['headers'] ?? [];
        $body = $this->decodeBody($event);

        // Build URI
        $uri = new Uri();
        $uri = $uri->withPath($path);
        if (!empty($queryString)) {
            $uri = $uri->withQuery($queryString);
        }

        // Set host from headers
        if (isset($headers['host'])) {
            $uri = $uri->withHost($headers['host']);
        }

        // Create body stream
        $bodyStream = new Stream('php://temp', 'wb+');
        if (!empty($body)) {
            $bodyStream->write($body);
            $bodyStream->rewind();
        }

        // Parse query parameters
        $queryParams = [];
        if (!empty($queryString)) {
            parse_str($queryString, $queryParams);
        }

        // Create server request
        $request = new ServerRequest(
            serverParams: [],
            uploadedFiles: [],
            uri: $uri,
            method: $method,
            body: $bodyStream,
            headers: $headers
        );

        // Add query parameters
        $request = $request->withQueryParams($queryParams);

        // Add path parameters as attribute
        if (isset($event['pathParameters'])) {
            $request = $request->withAttribute('pathParameters', $event['pathParameters']);
        }

        // Add Lambda context as attribute
        $request = $request->withAttribute('lambda', [
            'event' => $event,
            'requestContext' => $event['requestContext'] ?? [],
        ]);

        return $request;
    }

    /**
     * Decode event body, handling base64 encoding if needed
     */
    private function decodeBody(array $event): string
    {
        $body = $event['body'] ?? '';

        if (empty($body)) {
            return '';
        }

        if ($event['isBase64Encoded'] ?? false) {
            return base64_decode($body);
        }

        return $body;
    }
}
