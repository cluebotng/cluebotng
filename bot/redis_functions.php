<?PHP
	class RedisProxy {
		public static function send( $change ) {
			$data = json_encode( $change );
			$udp = fsockopen( 'udp://' . trim(file_get_contents(getenv("HOME") . '/.current_redis_relay_node')), 1345);
			fwrite( $udp, $data );
			fclose( $udp );
		}
	}
?>	
