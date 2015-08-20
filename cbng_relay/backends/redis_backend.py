import threading
import logging
from Queue import Empty
from redis import StrictRedis
from config import Config

log = logging.getLogger(__name__)


class Redis(threading.Thread):
    running = True
    config = Config()
    prefix = ''

    def __init__(self, queue):
        self.queue = queue
        threading.Thread.__init__(self)
        self.prefix = self.config.get('redis', 'prefix')

    def run(self):
        self.client = StrictRedis(host=self.config.get('redis', 'host'),
                                  port=self.config.getint('redis', 'port'),
                                  db=self.config.getint('redis', 'db'))

        while self.running:
            try:
                (type, data) = self.queue.get_nowait()
            except Empty:
                continue

            dispatcher = {
                'test': self.logger
            }
            if type in dispatcher.keys():
                try:
                    dispatcher[type](data)
                except Exception as e:
                    log.error('Dispatch failed', e)

    def stop(self):
        self.running = False

    # Handlers
    def logger(self, data):
        self.client.set('%stest' % self.prefix, 'test')
        log.info(data)
