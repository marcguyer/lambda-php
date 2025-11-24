#!/usr/bin/env bash
set -euo pipefail

# Cold start benchmark - Forces cold starts by updating function config between invocations
# Relies on AWS_PROFILE and AWS_REGION environment variables
FUNCTION_NAME="${1:-php-lambda-dev}"
MEMORY="${2:-384}"
SAMPLES="${3:-30}"

OUTPUT_FILE="cold_start_${FUNCTION_NAME}_${MEMORY}mb.csv"

echo "Memory,BilledMs,InitMs,MemoryUsedMB" > "$OUTPUT_FILE"

echo "Testing cold starts for ${FUNCTION_NAME} at ${MEMORY}MB with ${SAMPLES} samples..."
echo "Output: $OUTPUT_FILE"
echo "This will take a while as we force cold starts between each invocation..."

# Set initial memory configuration
aws lambda update-function-configuration \
    --function-name "$FUNCTION_NAME" \
    --memory-size "$MEMORY" \
    --output json > /dev/null

aws lambda wait function-updated --function-name "$FUNCTION_NAME"

sleep 2

for i in $(seq 1 "$SAMPLES"); do
    # Force a cold start by toggling an environment variable
    TOGGLE_VALUE="cold_start_$i"

    aws lambda update-function-configuration \
        --function-name "$FUNCTION_NAME" \
        --environment "Variables={COLD_START_MARKER=$TOGGLE_VALUE}" \
        --output json > /dev/null

    aws lambda wait function-updated --function-name "$FUNCTION_NAME"

    # Wait a bit to ensure previous container is gone
    sleep 3

    # Invoke and capture metrics
    # Use /benchmark for HTTP functions, empty payload for function runtime
    if [[ "$FUNCTION_NAME" == *"-http"* ]]; then
        PAYLOAD='{"version":"2.0","routeKey":"GET /benchmark","rawPath":"/benchmark"}'
    else
        PAYLOAD='{}'
    fi

    result=$(aws lambda invoke \
        --function-name "$FUNCTION_NAME" \
        --payload "$PAYLOAD" \
        --cli-binary-format raw-in-base64-out \
        --log-type Tail \
        --output json \
        /dev/stdout 2>/dev/null | jq -r '.LogResult' | base64 -d | grep "REPORT")

    billed=$(echo "$result" | grep -o "Billed Duration: [0-9]*" | awk '{print $3}')
    init=$(echo "$result" | grep -o "Init Duration: [0-9.]*" | awk '{print $3}' | cut -d. -f1)
    mem=$(echo "$result" | grep -o "Max Memory Used: [0-9]*" | awk '{print $4}')

    echo "$MEMORY,$billed,$init,$mem" >> "$OUTPUT_FILE"
    echo "  Sample $i: ${billed}ms (init: ${init}ms), ${mem}MB"
done

echo "Complete! Results in $OUTPUT_FILE"