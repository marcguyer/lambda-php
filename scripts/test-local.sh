#!/bin/bash

# Invoke local Lambda function
# Usage: ./scripts/invoke-local.sh [route] [method]

set -e

ROUTE="${1:-/health}"
METHOD="${2:-GET}"

# API Gateway v2 event format
EVENT=$(cat <<EOF
{
  "version": "2.0",
  "routeKey": "${METHOD} ${ROUTE}",
  "rawPath": "${ROUTE}",
  "rawQueryString": "",
  "headers": {
    "host": "localhost:9000",
    "user-agent": "curl",
    "accept": "*/*"
  },
  "requestContext": {
    "http": {
      "method": "${METHOD}",
      "path": "${ROUTE}",
      "protocol": "HTTP/1.1",
      "sourceIp": "127.0.0.1"
    },
    "requestId": "local-$(date +%s)",
    "stage": "\$default",
    "time": "$(date -u +%Y-%m-%dT%H:%M:%S.000Z)",
    "timeEpoch": $(date +%s)000
  }
}
EOF
)

echo "Invoking Lambda locally..."
echo "Route: ${METHOD} ${ROUTE}"
echo ""

# Invoke the function
curl -s -X POST \
  "http://localhost:9000/2015-03-31/functions/function/invocations" \
  -H "Content-Type: application/json" \
  -d "$EVENT" | jq .

echo ""