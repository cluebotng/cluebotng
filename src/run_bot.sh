#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR/../
echo $(hostname -f) > $DIR/.current_node
export LD_LIBRARY_PATH=$DIR
exec ./cluebotng -l -m live_run
