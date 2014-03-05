#!/usr/bin/env node
var redis = require("redis");
var dgram = require("dgram");
var relay = dgram.createSocket("udp4");
var config = require("./relay_redis.conf.js");

var client = redis.createClient(config.port, config.server);

relay.on('message', function(data, info) {
	for ( var i = 0; i < config.keys.length; i++ ) {
		client.publish(config.keys[i], data);
	}
});

relay.bind( 1345 )
