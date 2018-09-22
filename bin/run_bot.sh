#!/bin/bash
cd /data/project/cluebotng/apps/bot
mysql --defaults-file="${HOME}"/replica.my.cnf -h tools-db -A s52585__cb -e 'replace into `cluster_node` values ("'$(hostname -f | sed 's/[^A-Za-z0-9\._\-]+//g')'", "bot")'
exec /usr/bin/php -f /data/project/cluebotng/apps/bot/bot/cluebot-ng.php
