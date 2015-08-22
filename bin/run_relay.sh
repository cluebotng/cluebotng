#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" && pwd )"
cd $DIR/relay_irc/
mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s51109__cb -e 'insert into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "relay") on duplicate key update node=node;'
exec /data/project/cluebot/node/bin/node relay_irc.js
