#!/usr/bin/env python
from twisted.internet import protocol, reactor
import json
import logging
from utils import Utils
from config import Config

log = logging.getLogger(__name__)


class Relay(protocol.Protocol):

    def dataReceived(self, data):
        try:
            type = data.split(':')[0]
            data = json.loads(':'.join(data.split(':')[1:]))
            payload = (type, data)

            for thread, queue in self.factory.backends.items():
                queue.put(payload)
        except Exception as e:
            log.debug(e)
            pass


class RelayFactory(protocol.Factory):
    protocol = Relay

    def __init__(self, backends):
        self.backends = backends

if __name__ == '__main__':
    logging.basicConfig(level=logging.DEBUG)
    backends = Utils().startBackends()

    try:
        port = Config().getint('general', 'port')
    except ConfigParser.NoOptionError:
        port = 0

    srv = reactor.listenTCP(port, RelayFactory(backends))
    Utils().takeoverActiveNode(srv.getHost().port)
    reactor.run()

    for thread, queue in backends.items():
        log.debug('Stopping thread')
        thread.stop()
        thread.join()
