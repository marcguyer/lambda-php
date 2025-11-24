# PHP 8.4 Lambda Runtime

Minimal PHP 8.4 runtime for AWS Lambda on ARM64 with PSR-7/PSR-15 support.

## Features

- **PHP 8.4** compiled for ARM64 (Amazon Linux 2023)
- **Extensions**: opcache, mbstring, bcmath, sockets, pcntl, curl, zlib, openssl, pdo_sqlite
- **PSR**: Works with any PSR-7/PSR-15 handler
- **Performance**: ~197-250ms cold start, ~2ms warm (varies by memory)
- **Layer Size**: ~33MB

## Prerequisites

- Docker and Docker Compose
- AWS CLI (for deployment)

## Quick Start

```bash
# Build PHP image
docker compose build php

# Install dependencies
docker compose run --rm -w /runtime php composer install
docker compose run --rm -w /function php composer install

# Run tests
docker compose run --rm -w /runtime php composer test
docker compose run --rm -w /function php composer test

# Build runtime layer
./scripts/build-layers.sh
```

## Architecture

```
Lambda Event → Bootstrap (runtime/src/bootstrap.php)
              ↓ RuntimeApiClient (polls Runtime API)
              ↓ EventConverter (Lambda → PSR-7)
              ↓ handler.php (returns PSR-15 RequestHandlerInterface)
              ↓ Application (PSR-15)
              ↓ ResponseConverter (PSR-7 → Lambda)
              ↓ Lambda Response
```

**Single-layer deployment:**
- Runtime Layer: PHP 8.4 binary + runtime classes + libonig (~33MB)
- Function Code: Your PSR-15 application + handler.php

## Development

```bash
# Install packages
docker compose run --rm -w /function php composer require monolog/monolog

# Run tests
docker compose run --rm -w /runtime php composer test
docker compose run --rm -w /function php composer test

# Check PHP extensions
docker compose run --rm php php -m

# Interactive shell
docker compose run --rm -w /function php bash
```

### Local Testing

Run the Lambda runtime locally with AWS Runtime Interface Emulator:

```bash
# Prerequisites (one-time setup)
./scripts/build-layers.sh  # Build runtime layer with PHP binary
docker compose run --rm -w /function php composer install

# Start local Lambda runtime
docker compose build lambda
docker compose up lambda

# In another terminal, invoke the function
./scripts/test-local.sh health

# Test different routes
./scripts/test-local.sh health GET
./scripts/test-local.sh some-route POST

# View logs with debug output (LAMBDA_RUNTIME_DEBUG=1 is enabled by default)
docker compose logs -f lambda
```

The local Lambda environment:
- Runs the actual bootstrap.php runtime
- Uses AWS Lambda Runtime Interface Emulator (RIE)
- Exposes on `http://localhost:9000`
- Shows full debug output for runtime introspection

### Runtime Debug Mode

Enable detailed runtime logging to CloudWatch for introspection:

```php
// In your handler.php
putenv('LAMBDA_RUNTIME_DEBUG=1');
```

Debug logs include:
- Handler load time
- Event conversion timing
- Handler execution time
- Response conversion timing
- Memory usage per invocation
- Complete request lifecycle with timestamps

Example output in CloudWatch:
```
[RUNTIME DEBUG 1234567890.123] Runtime bootstrap starting
[RUNTIME DEBUG 1234567890.145] Runtime components initialized
[RUNTIME DEBUG 1234567890.150] Loading handler {"path":"/var/task/handler.php"}
[RUNTIME DEBUG 1234567890.234] Handler loaded {"duration_ms":84.5}
[RUNTIME DEBUG 1234567890.235] Waiting for invocation
[RUNTIME DEBUG 1234567890.456] Invocation received {"request_id":"abc-123","event_keys":["version","routeKey"...]}
[RUNTIME DEBUG 1234567890.457] Event converted to PSR-7 {"duration_ms":0.856,"method":"GET","uri":"https://example.com/health"}
[RUNTIME DEBUG 1234567890.459] Handler executed {"duration_ms":1.23,"status_code":200}
[RUNTIME DEBUG 1234567890.460] Response converted {"duration_ms":0.123}
[RUNTIME DEBUG 1234567890.462] Response sent {"send_duration_ms":1.45,"total_duration_ms":5.67}
[RUNTIME DEBUG 1234567890.462] Invocation complete {"memory_mb":42.5,"peak_memory_mb":43.2}
```

## Deployment

```bash
# Deploy to AWS
./scripts/deploy-aws.sh

# Test the function
aws lambda invoke \
    --function-name php-lambda \
    --payload '{"version":"2.0","routeKey":"GET /health","rawPath":"/health"}' \
    --cli-binary-format raw-in-base64-out \
    response.json
```

## Benchmarking

```bash
# Cold start benchmark (30 samples)
./scripts/benchmark-cold-start.sh php-lambda 384 30

# Warm invocation benchmark (100 samples)
./scripts/benchmark-warm.sh php-lambda 384 100

# Analyze results
python3 scripts/analyze-benchmarks.py
```

## Project Structure

```
lambda-php/
├── docker/
│   ├── dev/Dockerfile       # PHP 8.4 dev image (tools, composer)
│   └── lambda/Dockerfile    # Local Lambda runtime (with RIE)
├── docker-compose.yml       # Local dev environment
├── runtime/                 # PHP runtime layer
│   ├── src/
│   │   ├── bootstrap.php    # Lambda entry point (event loop)
│   │   ├── EventConverter.php
│   │   ├── ResponseConverter.php
│   │   └── RuntimeApiClient.php
│   ├── tests/               # Runtime unit tests
│   ├── build/               # PHP compilation (Dockerfile, build.sh)
│   └── layer/               # Built layer output
├── function/                # Example PSR-15 application (Mezzio)
│   ├── handler.php          # Returns PSR-15 RequestHandlerInterface
│   ├── config/              # Routes, container, app config
│   ├── src/Handler/         # PSR-15 request handlers
│   └── tests/               # Handler tests
└── scripts/
    ├── build-layers.sh      # Build runtime layer
    ├── deploy-aws.sh        # Deploy to AWS
    ├── test-aws.sh          # Test deployed Lambda
    ├── test-local.sh        # Test local Lambda
    ├── benchmark-*.sh       # Performance benchmarks
    └── analyze-benchmarks.py # Benchmark analysis
```

## Performance Results

### Cold Start Comparison (30 samples each)

| Memory | Avg BilledMs | Median | Min | Max | Avg Init | Memory Used |
|--------|-------------|--------|-----|-----|----------|-------------|
| 256MB  | 250ms       | 239ms  | 183ms | 314ms | 171ms | 41MB |
| 384MB  | 206ms       | 199ms  | 144ms | 284ms | 170ms | 42MB |
| 512MB  | 197ms       | 192ms  | 124ms | 274ms | 169ms | 42MB |

### Warm Invocation (100 samples each)

| Memory | Avg | Median | P99 | Memory Used |
|--------|-----|--------|-----|-------------|
| 256MB  | 2ms | 2ms    | 3ms | 41-42MB     |
| 384MB  | 2ms | 2ms    | 3ms | 42MB        |
| 512MB  | 2ms | 2ms    | 3ms | 42MB        |

### Analysis

- **Cold starts improve with more memory** due to proportional CPU allocation
- **256MB to 384MB** provides the biggest improvement (~18% faster)
- **384MB to 512MB** yields diminishing returns (~4% faster)
- **Warm invocations** are consistent at 2ms across all memory configurations
- **Memory usage** is ~42MB regardless of allocated memory

### Cost Comparison

Lambda ARM64 pricing: $0.0000133334/GB-second

| Memory | Cost/1M Warm | Cost/1M (1% Cold) | Cost/1M (5% Cold) |
|--------|--------------|-------------------|-------------------|
| 256MB  | $0.007       | $0.015            | $0.047            |
| 384MB  | $0.010       | $0.018            | $0.050            |
| 512MB  | $0.013       | $0.022            | $0.055            |

**Recommendation**: 384MB offers the best balance of cost and cold start performance.

## License

MIT