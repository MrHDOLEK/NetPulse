#!/bin/sh
# Test stub: stands in for the Ookla Speedtest CLI on a FAILED run.
# Writes an error line to stderr and exits non-zero, the way the real CLI behaves when a
# test cannot complete (e.g. no servers reachable). OoklaSpeedtestRunner must turn this
# into a failure outcome.
echo "Configuration - Could not retrieve or read configuration" >&2
exit 1
