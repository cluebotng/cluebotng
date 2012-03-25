#!/bin/bash
DIR="`dirname $0`"

. "${DIR}/toolconfig"

echo "Creating Bayesian training data:"
${BINARY_DIR}/cluebotng -f "$BAYESTRAINFILE" -m bayes_train
echo "Training main Bayesian database:"
${BINARY_DIR}/create_bayes_db ${DATA_DIR}/bayes.db ${DATA_DIR}/main_bayes_train.dat
echo "Training 2-Bayesian database:"
${BINARY_DIR}/create_bayes_db ${DATA_DIR}/two_bayes.db ${DATA_DIR}/two_bayes_train.dat

