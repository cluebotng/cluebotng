#!/bin/bash
mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s52585__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "relay")'
cd /data/project/cluebotng/apps/bot/relay_irc
exec /data/project/cluebotng/node/bin/node relay_irc.js
