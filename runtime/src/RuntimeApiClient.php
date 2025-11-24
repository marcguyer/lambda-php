<?php

declare(strict_types=1);

namespace LambdaPhp\Runtime;

use RuntimeException;

/**
 * Client for AWS Lambda Runtime API
 *
 * Uses cURL for HTTP requests to the Lambda Runtime API
 *
 * @see https://docs.aws.amazon.com/lambda/latest/dg/runtimes-api.html
 */
class RuntimeApiClient
{
    private string $baseUrl;

    public function __construct(string $runtimeApi)
    {
        $this->baseUrl = "http://{$runtimeApi}/2018-06-01/runtime";
    }

    public function getNextInvocation(): array
    {
        $url = "{$this->baseUrl}/invocation/next";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 0, // No timeout - this is a long-poll
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Failed to get next invocation: {$error}");
        }

        if ($statusCode !== 200) {
            curl_close($ch);
            throw new RuntimeException("Failed to get next invocation: HTTP {$statusCode}");
        }

        $headerString = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        $headers = $this->parseHeaderString($headerString);

        $requestId = $headers['lambda-runtime-aws-request-id'] ?? '';
        if (empty($requestId)) {
            throw new RuntimeException('Missing Lambda-Runtime-Aws-Request-Id header');
        }

        return [
            'requestId' => $requestId,
            'event' => json_decode($body, true) ?? [],
            'context' => [
                'requestId' => $requestId,
                'deadlineMs' => $headers['lambda-runtime-deadline-ms'] ?? '',
                'functionArn' => $headers['lambda-runtime-invoked-function-arn'] ?? '',
                'traceId' => $headers['lambda-runtime-trace-id'] ?? '',
            ],
        ];
    }

    public function sendResponse(string $requestId, mixed $data): void
    {
        $url = "{$this->baseUrl}/invocation/{$requestId}/response";
        $body = is_string($data) ? $data : json_encode($data);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false || $statusCode !== 202) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Failed to send response: {$error} (HTTP {$statusCode})");
        }

        curl_close($ch);
    }

    public function sendError(string $requestId, \Throwable|array $error): void
    {
        $url = "{$this->baseUrl}/invocation/{$requestId}/error";

        if ($error instanceof \Throwable) {
            $errorData = [
                'errorType' => get_class($error),
                'errorMessage' => $error->getMessage(),
                'stackTrace' => $this->formatStackTrace($error),
            ];
        } else {
            $errorData = $error;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($errorData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Lambda-Runtime-Function-Error-Type: Unhandled',
            ],
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    private function formatStackTrace(\Throwable $error): array
    {
        $trace = [];
        foreach ($error->getTrace() as $frame) {
            $trace[] = sprintf(
                '%s(%d): %s%s%s()',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown'
            );
        }
        return $trace;
    }

    private function parseHeaderString(string $headerString): array
    {
        $headers = [];
        $lines = explode("\r\n", $headerString);

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }
}