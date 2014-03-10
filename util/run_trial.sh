#!/bin/bash
DIR="`dirname $0`"

. "${DIR}/toolconfig"

echo "Running trial:"
${BINARY_DIR}/cluebotng -f "$TRIALFILE" -m trial_run

