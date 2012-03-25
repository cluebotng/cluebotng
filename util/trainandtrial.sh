#!/bin/bash
DIR="`dirname $0`"

. "${DIR}/toolconfig"

$DIR/train_all.sh
$DIR/run_trial.sh
