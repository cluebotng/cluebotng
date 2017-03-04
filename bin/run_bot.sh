#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/" && pwd )"
cd $DIR/
if [ "$(whoami)" == "tools.cluebot" ];
then
    mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s51109__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "bot")'
fi
if [ "$(whoami)" == "tools.cluebotng" ];
then
    cd /data/project/cluebotng/apps/bot
    mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s52585__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "bot")'
fi
exec /usr/bin/php -f $DIR/bot/cluebot-ng.php
