#!/usr/bin/env python
import json
import socket

DATA = {
    'test': {},
}


def fire():
    s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    s.connect(('localhost', 8080))
    for key, data in DATA.items():
        msg = '%s:%s' % (key, json.dumps(data))
        print('Sending: %s' % msg)
        s.send(msg)
    s.close()


if __name__ == '__main__':
    fire()
