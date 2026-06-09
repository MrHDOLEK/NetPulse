#!/bin/sh
# Test stub: stands in for the Ookla Speedtest CLI on a SUCCESSFUL run.
# Ignores all flags and prints a minimal but valid "result"-type Ookla JSON payload
# to stdout, then exits 0 — exactly what OoklaSpeedtestRunner expects on success.
cat <<'JSON'
{
  "type": "result",
  "ping": { "jitter": 1.2, "latency": 9.3 },
  "download": { "bandwidth": 12500000, "bytes": 50000000, "elapsed": 4000 },
  "upload": { "bandwidth": 2500000, "bytes": 10000000, "elapsed": 4000 },
  "packetLoss": 0,
  "isp": "Test ISP",
  "server": { "id": "12345", "name": "Test Server", "host": "test.example.com" },
  "result": { "id": "abc-123", "url": "https://www.speedtest.net/result/c/abc-123" }
}
JSON
exit 0
