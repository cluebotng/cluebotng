#!/bin/bash
DIR="`dirname $0`"

. "${DIR}/toolconfig"

echo "Creating ANN training data:"
${BINARY_DIR}/cluebotng -f "$TRAINFILE" -m ann_train

${DIR}/train_ann_net.sh

