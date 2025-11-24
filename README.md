# PHP 8.4 Lambda Runtime

Minimal PHP 8.4 runtime for AWS Lambda on ARM64 with PSR-7/PSR-15 support via Mezzio.

## Features

- **PHP 8.4** compiled for ARM64 (Amazon Linux 2023)
- **Extensions**: opcache, mbstring, bcmath, sockets, pcntl, curl, zlib, openssl, pdo_sqlite
- **Framework**: Laminas Mezzio (PSR-7/PSR-15)
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
Lambda Event → Bootstrap (function/bootstrap)
              ↓ RuntimeApiClient (polls Runtime API)
              ↓ EventConverter (Lambda → PSR-7)
              ↓ Mezzio Application
              ↓ Request Handler (PSR-15)
              ↓ ResponseConverter (PSR-7 → Lambda)
              ↓ Lambda Response
```

**Single-layer deployment:**
- Runtime Layer: PHP 8.4 binary + runtime classes + libonig (~33MB)
- Function Code: Mezzio application

## Development

```bash
# Install packages
docker compose run --rm -w /function php composer require monolog/monolog

# Run tests
docker compose run --rm -w /function php composer test

# Check PHP extensions
docker compose run --rm php php -m

# Interactive shell
docker compose run --rm -w /function php bash
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
├── Dockerfile.dev           # PHP 8.4 dev image
├── docker-compose.yml       # Local dev environment
├── runtime/                 # PHP runtime layer
│   ├── src/                 # Runtime classes (EventConverter, etc.)
│   ├── tests/               # Runtime unit tests
│   ├── build/               # PHP compilation (Dockerfile, build.sh)
│   └── layer/               # Built layer output
├── function/                # Mezzio application
│   ├── bootstrap            # Lambda entry point
│   ├── config/              # Routes, container, app config
│   ├── src/Handler/         # PSR-15 request handlers
│   └── tests/               # Handler tests
└── scripts/
    ├── build-layers.sh      # Build runtime layer
    ├── deploy-aws.sh        # Deploy to AWS
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