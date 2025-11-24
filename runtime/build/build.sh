#!/bin/bash

# Build script for PHP 8.4 Lambda runtime layer
set -e

echo "Building PHP 8.4 runtime for AWS Lambda (ARM64)..."

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RUNTIME_DIR="$(dirname "$SCRIPT_DIR")"
LAYER_DIR="$RUNTIME_DIR/layer"

# Clean previous build
echo "Cleaning previous build..."
rm -rf "$LAYER_DIR"
mkdir -p "$LAYER_DIR"

# Build Docker image
echo "Building Docker image..."
docker build \
  --target exporter \
  --platform linux/arm64 \
  -t lambda-php-runtime-builder \
  "$SCRIPT_DIR"

# Extract built files from container
echo "Extracting runtime files..."
docker run --rm \
  --platform linux/arm64 \
  -v "$LAYER_DIR:/output" \
  lambda-php-runtime-builder \
  sh -c "cp -r /layer/* /output/"

# Copy runtime source and vendor into layer
echo "Copying runtime source..."
mkdir -p "$LAYER_DIR/runtime"
cp -r "$RUNTIME_DIR/src" "$LAYER_DIR/runtime/"
cp -r "$RUNTIME_DIR/vendor" "$LAYER_DIR/runtime/"

# Copy bootstrap to layer root (Lambda looks for /opt/bootstrap)
echo "Copying bootstrap to layer root..."
cp "$RUNTIME_DIR/src/bootstrap.php" "$LAYER_DIR/bootstrap"
chmod +x "$LAYER_DIR/bootstrap"

echo ""
echo "Runtime layer build complete!"
echo "Output: $LAYER_DIR"
echo ""
echo "Layer contents:"
ls -lh "$LAYER_DIR/"
echo ""
echo "PHP version:"
docker run --rm --platform linux/arm64 -v "$LAYER_DIR:/opt" -e LD_LIBRARY_PATH=/opt/lib lambda-php-runtime-builder /opt/bin/php -v
