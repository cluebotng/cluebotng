#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/" && pwd )"
cd $DIR/
if [ "$(whoami)" == "tools.cluebot" ];
then
    mysql --defaults-file="${HOME}"/replica.my.cnf -h tools.labsdb -A s51109__interface -e 'replace into `cbng_backend_clusternode` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "core")'
fi
if [ "$(whoami)" == "tools.cluebotng" ];
then
    mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s52585__interface -e 'replace into `cbng_backend_clusternode` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "core")'
fi
exec ./cluebotng -l -m live_run
