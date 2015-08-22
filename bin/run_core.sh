#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" && pwd )"
cd $DIR/
mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s51109__cb -e 'insert into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "core") on duplicate key update node=node;'
export LD_LIBRARY_PATH=$DIR/src/
exec ./cluebotng -l -m live_run
