import logging
import ConfigParser
import os

log = logging.getLogger(__name__)


class Config(ConfigParser.SafeConfigParser):

    def __init__(self):
        ConfigParser.SafeConfigParser.__init__(self)

        cnf = os.path.join(
            os.path.dirname(os.path.realpath(__file__)), 'relay.cnf')
        if os.path.isfile(cnf):
            self.read(cnf)
