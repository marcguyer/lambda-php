#!/usr/bin/env bash
set -euo pipefail

# Warm request benchmark - Rapid invocations to test warm container performance
# Relies on AWS_PROFILE and AWS_REGION environment variables
FUNCTION_NAME="${1:-php-lambda-dev}"
MEMORY="${2:-384}"
SAMPLES="${3:-100}"

OUTPUT_FILE="warm_${FUNCTION_NAME}_${MEMORY}mb.csv"

echo "Memory,DurationMs,BilledMs,MemoryUsedMB" > "$OUTPUT_FILE"

echo "Testing warm requests for ${FUNCTION_NAME} at ${MEMORY}MB with ${SAMPLES} samples..."
echo "Output: $OUTPUT_FILE"

# Set memory configuration
aws lambda update-function-configuration \
    --function-name "$FUNCTION_NAME" \
    --memory-size "$MEMORY" \
    --output json > /dev/null

aws lambda wait function-updated --function-name "$FUNCTION_NAME"

# Warm up the container with 5 invocations
echo "Warming up container with 5 requests..."
for i in $(seq 1 5); do
    if [[ "$FUNCTION_NAME" == *"-http"* ]]; then
        PAYLOAD='{"version":"2.0","routeKey":"GET /benchmark","rawPath":"/benchmark"}'
    else
        PAYLOAD='{}'
    fi

    aws lambda invoke \
        --function-name "$FUNCTION_NAME" \
        --payload "$PAYLOAD" \
        --cli-binary-format raw-in-base64-out \
        /dev/null > /dev/null 2>&1

    sleep 0.5
done

echo "Container warmed up. Starting benchmark..."

# Run benchmark samples
for i in $(seq 1 "$SAMPLES"); do
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

    # Extract metrics - note: no Init Duration on warm requests
    duration=$(echo "$result" | awk -F'\t' '{print $2}' | awk '{print $2}' | cut -d. -f1)
    billed=$(echo "$result" | grep -o "Billed Duration: [0-9]*" | awk '{print $3}')
    mem=$(echo "$result" | grep -o "Max Memory Used: [0-9]*" | awk '{print $4}')

    echo "$MEMORY,$duration,$billed,$mem" >> "$OUTPUT_FILE"

    # Progress indicator every 10 samples
    if (( i % 10 == 0 )); then
        echo "  Sample $i/$SAMPLES: ${duration}ms, ${mem}MB"
    fi

    # Small delay to avoid throttling but keep container warm
    sleep 0.2
done

echo ""
echo "Complete! Results in $OUTPUT_FILE"

# Calculate and display statistics
echo ""
echo "Statistics:"
tail -n +2 "$OUTPUT_FILE" | awk -F, '
{
    sum += $2
    count++
    if (NR == 1 || $2 < min) min = $2
    if (NR == 1 || $2 > max) max = $2
}
END {
    avg = sum / count
    printf "  Avg Duration: %.1fms\n", avg
    printf "  Min Duration: %dms\n", min
    printf "  Max Duration: %dms\n", max
}
'