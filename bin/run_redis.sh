#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" && pwd )"
cd $DIR/relay_redis/
mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s51109__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "redis")'
exec /data/project/cluebot/node/bin/node relay_redis.js
