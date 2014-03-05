#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR/
echo $(hostname -f) > /data/project/cluebot/.current_core_node
export LD_LIBRARY_PATH=$DIR/src/
exec ./cluebotng -l -m live_run
