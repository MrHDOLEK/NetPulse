#!/bin/sh
# Test stub: exits 0 but prints non-JSON garbage, exercising the runner's
# invalid-JSON failure branch (success exit, unparseable output -> failure outcome).
echo "this is not json at all"
exit 0
