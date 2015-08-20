import logging
from socket import gethostname
import MySQLdb
import ConfigParser
import Queue
import os
from backends.redis_backend import Redis
from backends.irc_backend import IRC

log = logging.getLogger(__name__)


class Utils:

    def takeoverActiveNode(self, port):
        hostname = gethostname()

        my_cnf = os.path.join(os.path.expanduser("~"), '.cbng.cnf')
        if not os.path.isfile(my_cnf):
            return

        try:
            config = ConfigParser.RawConfigParser()
            config.read(my_cnf)
            user = config.get('discovery_mysql', 'user')
            password = config.get('discovery_mysql', 'password')
            db = config.get('discovery_mysql', 'name')
            host = config.get('discovery_mysql', 'host')

            print port
            db = MySQLdb.connect(user=user, passwd=password, db=db, host=host)
            c = db.cursor()
            c.execute(
                'replace into `cluster_node` values (%s, %s, "ng_relay");', (hostname, port))
            c.close()
            db.commit()
            db.close()
        except Exception as e:
            log.debug('Could not takeover active node', e)

    def startBackends(self):
        backends = {}

        log.debug('Starting irc thread')
        irc_queue = Queue.Queue(0)
        irc = IRC(irc_queue)
        irc.start()
        backends[irc] = irc_queue

        log.debug('Starting redis thread')
        redis_queue = Queue.Queue(0)
        redis = Redis(redis_queue)
        redis.start()
        backends[redis] = redis_queue

        return backends
