<?PHP
	function read() {
		global $in;
		return trim( fgets( $in, 512 ) );
	}
	
	function write( $str ) {
		global $out;
		fwrite( $out, $str );
		fflush( $out );
	}
	
	function error( $str ) {
		global $err;
		fwrite( $err, $str );
		fflush( $err );
		die();
	}
	
	function prompt( $str ) {
		write( $str );
		return read();
	}
	
	function clear() {
		system( 'clear; reset' );
	}
	
	$in = fopen( 'php://stdin', 'r' );
	$out = fopen( 'php://stdout', 'w' );
	$err = fopen( 'php://stderr', 'w' );
	
	write( 'I will need some MySQL credentials to begin.' . "\n" );
	write( 'Anything you type will be echoed back to you.' . "\n" );
	prompt( 'Press ENTER to continue.' );
	
	clear();
	
	do {
		$retry = true;
		$host = prompt( 'Host: ' );
		$user = prompt( 'User: ' );
		$pass = prompt( 'Pass: ' );
		
		clear();
		
		$mysql = mysql_connect( $host, $user, $pass );
		if( !$mysql )
			write( 'I could not connect to mysql with those credentials.' . "\n" . 'Error: ' . mysql_error() . "\n" );
		else if( !mysql_select_db( 'cbng_editdb_master' ) )
			write( 'I could not use cbng_editdb_master with those credentials.' . "\n" . 'Error: ' . mysql_error() . "\n" );
		else
			$retry = false;
	} while( $retry );
	
	write( 'Updating dumps table ...' );
	mysql_query( 'INSERT INTO `dumps` (`time`) VALUES (CURRENT_TIMESTAMP)' );
	write( ' Done.' . "\n" );
	
	write( 'Dumping to cbng_editdb_bootstrap.sql ...' . "\n" );
	system( 'mysqldump --skip-opt --skip-triggers --compact -eCntqQv --host=' . escapeshellarg( $host ) . ' -u ' . escapeshellarg( $user ) . ( $pass == '' ? '' : ' -p' . escapeshellarg( $pass ) ) . ' cbng_editdb_master editset lastupdated dumps > cbng_editdb_bootstrap.sql' );
	write( 'Done dumping.' . "\n" );
	
	write( 'Compressing ...' . "\n" );
	system( 'lzma -z7vv cbng_editdb_bootstrap.sql' );
	write( 'Compressed.' . "\n" );
	
	write( 'All finished.' . "\n" );
?>