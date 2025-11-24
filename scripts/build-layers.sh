#!/bin/bash

# Build script for PHP Lambda runtime layer
set -e

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "========================================="
echo "Building PHP Lambda Runtime Layer"
echo "========================================="
echo ""

# Build runtime layer
echo "Building PHP runtime layer..."
cd "$PROJECT_ROOT/runtime/build"
./build.sh

echo ""
echo "========================================="
echo "Build complete!"
echo "========================================="
echo ""
echo "Runtime layer: $PROJECT_ROOT/runtime/layer"
echo ""
echo "Next steps:"
echo "  1. Run tests: docker compose run --rm -w /function php composer test"
echo "  2. Deploy to AWS: ./scripts/deploy-aws.sh"