import threading
import logging
from Queue import Empty
import time
import socket
import os
from config import Config
import ConfigParser
import errno
import fcntl

log = logging.getLogger(__name__)


class IRC(threading.Thread):
    running = True
    config = Config()
    irc_bufer = ''
    irc_first_ping = False

    def __init__(self, queue):
        self.queue = queue
        threading.Thread.__init__(self)

    def run(self):
        self.connect()
        while self.running:
            self.process_irc()
            self.process_queue()

    def stop(self):
        self.running = False
        self.irc_client.close()

    def connect(self):
        self.irc_client = socket.socket()
        self.irc_client.connect(
            (self.config.get('irc', 'host'), self.config.getint('irc', 'port')))
        fcntl.fcntl(self.irc_client, fcntl.F_SETFL, os.O_NONBLOCK)
        self.irc_client.send(
            bytes("NICK %s\r\n" % self.config.get('irc', 'nick')))
        self.irc_client.send(bytes(
            "USER %s localhost localhost :ClueBotNG Relay\r\n" % self.config.get('irc', 'nick')))

    def process_irc(self):
        try:
            self.irc_bufer += self.irc_client.recv(1024).decode("UTF-8")
        except socket.error, e:
            if e.args[0] == errno.EAGAIN or e.args[0] == errno.EWOULDBLOCK:
                return

        lines = self.irc_bufer.split("\n")
        self.irc_bufer = lines.pop()

        for line in lines:
            line = line.strip().split(' ')

            log.debug('IRC line: %s' % line)
            if line[0] == 'PING':
                if not self.irc_first_ping:
                    self.irc_first_ping = True
                    log.debug('Running oper')
                    try:
                        oper_user = self.config.get('irc', 'oper_user')
                        oper_pass = self.config.get('irc', 'oper_pass')
                        self.irc_client.send(
                            bytes("OPER %s %s\r\n" % (oper_user, oper_pass)))
                    except ConfigParser.NoOptionError:
                        log.info('No oper in config')
                        pass

                    log.debug('Joining channels')
                    try:
                        for channel in self.config.get('irc', 'channels').split(','):
                            self.irc_client.send(
                                bytes("JOIN #%s\r\n" % channel.strip()))
                    except ConfigParser.NoOptionError:
                        log.info('No channels in config')
                        pass
                self.irc_client.send(bytes("PONG %s\r\n" % line[1]))

    def process_queue(self):
        try:
            (type, data) = self.queue.get_nowait()
        except Empty:
            return

        dispatcher = {
            'test': self.logger
        }
        if type in dispatcher.keys():
            try:
                dispatcher[type](data)
            except Exception as e:
                log.error('Dispatch failed', e)

    # Handlers
    def logger(self, data):
        log.info(data)
