#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR/
echo $(hostname -f) > /data/project/cluebot/.current_redis_relay_node
exec /data/project/cluebot/node/bin/node relay_redis.js
