#!/usr/bin/env bash
set -euo pipefail

# Quick test script for AWS Lambda
# Usage: ./scripts/test-aws.sh [endpoint]
# Requires: AWS_REGION or AWS_DEFAULT_REGION set in environment

ENDPOINT="${1:-health}"
FUNCTION_NAME="php-la
PAYLOAD=$(cat <<EOF
{
    "version": "2.0",
    "routeKey": "GET /$ENDPOINT",
    "rawPath": "/$ENDPOINT",
    "rawQueryString": "",
    "headers": {
        "accept": "application/json"
    },
    "requestContext": {
        "http": {
            "method": "GET",
            "path": "/$ENDPOINT"
        }
    }
}
EOF
)

echo "Testing $FUNCTION_NAME /$ENDPOINT ..."
echo ""

aws lambda invoke \
    --function-name "$FUNCTION_NAME" \
    --payload "$PAYLOAD" \
    --cli-binary-format raw-in-base64-out \
    /tmp/response.json >/dev/null

echo "Response:"
if command -v jq >/dev/null 2>&1; then
    jq '.' /tmp/response.json
else
    cat /tmp/response.json
fi
echo ""

rm -f /tmp/response.json