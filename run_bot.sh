#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd $DIR/
echo $(hostname -f) > /data/project/cluebot/.current_bot_node
exec /usr/bin/php -f $DIR/bot/cluebot-ng.php
