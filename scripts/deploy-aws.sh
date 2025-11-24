#!/usr/bin/env bash
set -euo pipefail

# AWS Lambda deployment script

FUNCTION_NAME="php-lambda"

echo "Deploying PHP Lambda to AWS..."
echo "=========================================="

# Create layer zip
echo "Creating runtime layer..."
cd runtime/layer
zip -q -r ../../runtime-layer.zip .
cd ../..

# Create function zip
echo "Creating function package..."
cd function
zip -q -r ../function.zip . -x "*.git*" -x "tests/*" -x "phpunit.xml" -x ".phpunit.cache/*"
cd ..

# Create IAM role if it doesn't exist
ROLE_NAME="lambda-php-execution-role"
echo "Ensuring IAM role exists..."

if ! aws iam get-role --role-name "$ROLE_NAME" >/dev/null 2>&1; then
    echo "Creating IAM role..."
    aws iam create-role \
        --role-name "$ROLE_NAME" \
        --assume-role-policy-document '{
            "Version": "2012-10-17",
            "Statement": [{
                "Effect": "Allow",
                "Principal": {"Service": "lambda.amazonaws.com"},
                "Action": "sts:AssumeRole"
            }]
        }'

    aws iam attach-role-policy \
        --role-name "$ROLE_NAME" \
        --policy-arn "arn:aws:iam::aws:policy/service-role/AWSLambdaBasicExecutionRole"

    echo "Waiting for IAM role to propagate..."
    sleep 10
fi

ROLE_ARN=$(aws iam get-role --role-name "$ROLE_NAME" --query 'Role.Arn' --output text)

# Publish runtime layer
echo "Publishing runtime layer..."
RUNTIME_LAYER_VERSION=$(aws lambda publish-layer-version \
    --layer-name "php-84-runtime" \
    --description "PHP 8.4 runtime for ARM64" \
    --zip-file fileb://runtime-layer.zip \
    --compatible-runtimes provided.al2023 \
    --compatible-architectures arm64 \
    --query 'LayerVersionArn' \
    --output text)

echo "Runtime layer: $RUNTIME_LAYER_VERSION"

# Create or update Lambda function
echo "Creating/updating Lambda function..."
if aws lambda get-function --function-name "$FUNCTION_NAME" >/dev/null 2>&1; then
    echo "Function exists, updating code..."
    aws lambda update-function-code \
        --function-name "$FUNCTION_NAME" \
        --zip-file fileb://function.zip \
        --architectures arm64

    echo "Waiting for update to complete..."
    aws lambda wait function-updated --function-name "$FUNCTION_NAME"

    echo "Updating configuration..."
    aws lambda update-function-configuration \
        --function-name "$FUNCTION_NAME" \
        --layers "$RUNTIME_LAYER_VERSION" \
        >/dev/null
else
    echo "Creating new function..."
    aws lambda create-function \
        --function-name "$FUNCTION_NAME" \
        --runtime provided.al2023 \
        --handler bootstrap \
        --role "$ROLE_ARN" \
        --zip-file fileb://function.zip \
        --timeout 30 \
        --memory-size 384 \
        --architectures arm64 \
        --layers "$RUNTIME_LAYER_VERSION" \
        >/dev/null
fi

# Wait for function to be ready
echo "Waiting for function to be active..."
aws lambda wait function-active --function-name "$FUNCTION_NAME"

# Clean up local zips
rm -f runtime-layer.zip function.zip

echo ""
echo "=========================================="
echo "Deployment complete!"
echo ""
echo "Function ARN:"
aws lambda get-function --function-name "$FUNCTION_NAME" --query 'Configuration.FunctionArn' --output text
echo ""
echo "Test with:"
echo "  aws lambda invoke --function-name $FUNCTION_NAME --payload '{\"version\":\"2.0\",\"routeKey\":\"GET /health\",\"rawPath\":\"/health\"}' --cli-binary-format raw-in-base64-out response.json"
echo "=========================================="