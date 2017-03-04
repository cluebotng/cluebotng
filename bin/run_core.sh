#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/" && pwd )"
cd $DIR/
if [ "$(whoami)" == "tools.cluebot" ];
then
    mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s51109__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "core")'
fi
if [ "$(whoami)" == "tools.cluebotng" ];
then
    mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s52585__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "core")'
fi
exec ./cluebotng -l -m live_run
