#!/bin/bash
DIR="`dirname $0`"

. "${DIR}/toolconfig"

$DIR/train_bayes.sh
$DIR/train_ann.sh

