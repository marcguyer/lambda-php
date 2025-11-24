#!/usr/bin/env bash
set -euo pipefail

# Warm invocation benchmark - Ensures all invocations are warm
# Relies on AWS_PROFILE and AWS_REGION environment variables
FUNCTION_NAME="${1:-php-lambda-dev}"
MEMORY="${2:-384}"
SAMPLES="${3:-30}"

echo "Memory,BilledMs,MemoryUsedMB" > warm_results.csv

echo "Testing warm invocations at ${MEMORY}MB with ${SAMPLES} samples..."

# Set memory configuration
aws lambda update-function-configuration \
    --function-name "$FUNCTION_NAME" \
    --memory-size "$MEMORY" \
    --output json > /dev/null

aws lambda wait function-updated --function-name "$FUNCTION_NAME"

sleep 2

# Prime the function with one invocation to ensure it's warm
echo "Priming function..."
aws lambda invoke \
    --function-name "$FUNCTION_NAME" \
    --payload '{"version":"2.0","routeKey":"GET /health","rawPath":"/health"}' \
    --cli-binary-format raw-in-base64-out \
    /dev/null 2>/dev/null

sleep 1

# Now collect warm samples
for i in $(seq 1 "$SAMPLES"); do
    result=$(aws lambda invoke \
        --function-name "$FUNCTION_NAME" \
        --payload '{"version":"2.0","routeKey":"GET /health","rawPath":"/health"}' \
        --cli-binary-format raw-in-base64-out \
        --log-type Tail \
        --output json \
        /dev/stdout 2>/dev/null | jq -r '.LogResult' | base64 -d | grep "REPORT")

    billed=$(echo "$result" | grep -o "Billed Duration: [0-9]*" | awk '{print $3}')
    mem=$(echo "$result" | grep -o "Max Memory Used: [0-9]*" | awk '{print $4}')

    echo "$MEMORY,$billed,$mem" >> warm_results.csv
    echo "  Sample $i: ${billed}ms, ${mem}MB"

    # Short sleep to avoid throttling but keep container warm
    sleep 0.2
done

echo "Complete! Results in warm_results.csv"