<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime;

use Psr\Http\Message\ResponseInterface;

/**
 * Converts PSR-7 Response to AWS Lambda response format (API Gateway v2)
 */
class ResponseConverter
{
    public function convertFromResponse(ResponseInterface $response): array
    {
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();

        $isBinary = $this->isBinary($body);

        $result = [
            'statusCode' => $response->getStatusCode(),
            'headers' => $this->formatHeaders($response),
            'body' => $isBinary ? base64_encode($body) : $body,
            'isBase64Encoded' => $isBinary,
        ];

        $cookies = $response->getHeader('Set-Cookie');
        if ($cookies !== []) {
            $result['cookies'] = $cookies;
        }

        return $result;
    }

    private function formatHeaders(ResponseInterface $response): array|\stdClass
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            if (strtolower($name) === 'set-cookie') {
                continue;
            }

            $headers[strtolower($name)] = implode(', ', $values);
        }

        return $headers === [] ? new \stdClass() : $headers;
    }

    private function isBinary(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        // Null bytes indicate binary content
        if (str_contains($body, "\0")) {
            return true;
        }

        // Invalid UTF-8 sequences indicate binary content
        return !mb_check_encoding($body, 'UTF-8');
    }
}