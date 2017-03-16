#!/usr/bin/env node
var irc = require('irc');
var dgram = require("dgram");

var relay1 = dgram.createSocket("udp4");
var relay2 = dgram.createSocket("udp4");
var relay3 = dgram.createSocket("udp4");
var config = require("./relay_irc.conf.js");
var is_connected = false;

function in_array( array, obj ) {
    for( var i = 0; i < array.length; i++ ) {
        if( array[i] == obj ) {
            return true;
        }
    }
    return false;
}

var client = new irc.Client( config.server, config.nick, {
    userName: config.nick,
    realName: config.nick,
    debug: true,
    showErrors: true,
    autoRejoin: true,
    autoConnect: true,
    secure: false,
    channels: [
        '#cluebotng',
        '#cluebotng-spam'
    ]
});

relay1.on('message', function(data, info) {
    data = data.toString().substring( 0, 450 );
    try {
        if (is_connected) {
            client.say( '#cluebotng', data );
        }
    } catch ( e ){
        console.error( e )
    }
});

relay2.on('message', function(data, info) {
    data = data.toString().substring( 0, 450 );
    try {
        if (is_connected) {
            client.say( '#cluebotng-spam', data );
        }
    } catch ( e ){
        console.error( e )
    }
});

relay3.on('message', function(data, info) {
    data = data.toString().split(' :', 2);
    try {
        chan = data[0].toString();
        msg = data[1].toString().substring( 0, 450 );

        if( !in_array( client.opt.channels, chan ) ) {
            try {
                client.join( chan );
            } catch( e ) { console.error( e ); }
        }

        if (is_connected) {
            client.say( chan, msg );
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
        console.info('Sending OPER')
        for (var i = 0; i < config.extra.length; i++) {
            client.conn.write(config.extra[i] + "\r\n")
        }
        is_connected = true;
    }
});

relay1.bind( 3333 );
relay2.bind( 3334 );
relay3.bind( 1337 );
