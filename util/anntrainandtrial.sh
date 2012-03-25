#!/bin/bash
DIR="`dirname $0`"

. "${DIR}/toolconfig"

$DIR/train_ann.sh
$DIR/run_trial.sh
