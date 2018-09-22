#!/usr/bin/env node
var irc = require('irc');
var dgram = require("dgram");

var relay = dgram.createSocket("udp4");
var config = require("./relay_irc.conf.js");
var is_connected = false;

var client = new irc.Client( config.server, config.nick, {
    userName: config.nick,
    realName: config.nick,
    debug: true,
    showErrors: true,
    autoRejoin: true,
    autoConnect: true,
    secure: false,
    channels: [
        '#wikipedia-en-cbngfeed'
    ]
});

relay.on('message', function(data, info) {
    data = data.toString().substring( 0, 450 );
    try {
        if (is_connected) {
            client.say( '#wikipedia-en-cbngfeed', data );
        }
    } catch ( e ){
        console.error( e )
    }
});

client.addListener('error', function(message) {
    console.error('ERROR: %s: %s', message.command, message.args.join(' '));
});

client.addListener('raw', function(message) {
    if (!is_connected && message.command == '378') {
        console.info('Sending extra')
        for (var i = 0; i < config.extra.length; i++) {
            client.conn.write(config.extra[i] + "\r\n")
        }
        is_connected = true;
    }
});

relay.bind( 3334 );
