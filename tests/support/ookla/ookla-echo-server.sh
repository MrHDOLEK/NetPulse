#!/bin/sh
# Test/DoD stub: stands in for the Ookla Speedtest CLI on a SUCCESSFUL run, but ECHOES the
# requested --server-id back into the JSON "server.id" field. This makes server selection
# observable end-to-end: with a server pool > 1, the scheduler's round-robin rotates the
# --server-id across ticks, and the resulting measurements (and /metrics series) carry the
# rotating server id. Falls back to "auto" when no --server-id was passed.
SERVER_ID="auto"
for arg in "$@"; do
  case "$arg" in
    --server-id=*) SERVER_ID="${arg#--server-id=}" ;;
  esac
done

cat <<JSON
{
  "type": "result",
  "ping": { "jitter": 1.2, "latency": 9.3 },
  "download": { "bandwidth": 12500000, "bytes": 50000000, "elapsed": 4000 },
  "upload": { "bandwidth": 2500000, "bytes": 10000000, "elapsed": 4000 },
  "packetLoss": 0,
  "isp": "Test ISP",
  "server": { "id": "${SERVER_ID}", "name": "Stub Server ${SERVER_ID}", "host": "stub-${SERVER_ID}.example.com" },
  "result": { "id": "stub-${SERVER_ID}", "url": "https://www.speedtest.net/result/c/stub-${SERVER_ID}" }
}
JSON
exit 0
