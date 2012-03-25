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
		$host = prompt( 'Host: ' );
		$user = prompt( 'User: ' );
		$pass = prompt( 'Pass: ' );
		
		clear();
		
		$mysql = mysql_connect( $host, $user, $pass );
		if( !$mysql )
			write( 'I could not connect to mysql with those credentials.' . "\n" . 'Error: ' . mysql_error() . "\n" );
	} while( !$mysql );
	
	$engines = Array();
	$result = mysql_query( 'SHOW ENGINES' );
	while( $row = mysql_fetch_assoc( $result ) )
		$engines[ $row[ 'Engine' ] ] = $row[ 'Support' ];
	
	if( !isset( $engines[ 'FEDERATED' ] ) )
		error( 'It looks like your MySQL server does not support FEDERATED tables.  Please fix this, then run this script again.' . "\n" );
	if( $engines[ 'FEDERATED' ] == 'NO' )
		error( 'You need to edit your my.cnf file and add the line "federated" to the end of the [mysqld] section.  Please fix this, then run this script again.' . "\n" );
	
	write( 'Creating tables ...' );
	system( 'mysql -h ' . escapeshellarg( $host ) . ' -u ' . escapeshellarg( $user ) . ( $pass == '' ? '' : ' -p' . escapeshellarg( $pass ) ) . ' < cbngslave.sql' );
	write( ' Done.' . "\n" );
	
	if( !mysql_select_db( 'cbng_editdb', $mysql ) )
		error( 'I could not find the database that was supposed to have been created.' . "\n" );
	
	write( 'Now, we will need to populate the database.' . "\n" );
	write( 'There are two ways to do this:' . "\n" );
	write( '1. Use a bootstrap sql file then sync to the master.' . "\n" );
	write( '   This is the recommended way.  You will need to fetch a compressed bootstrap sql file from the master database.' . "\n" );
	write( '   You can ask Crispy or Cobi for this file.' . "\n" );
	write( '   This file is usually less than 200MB compressed (at the time of this writing).' . "\n" );
	write( '2. Sync from scratch to the master.' . "\n" );
	write( '   This is not recommended.  It will take a very long time and a lot of bandwidth.' . "\n" );
	write( '   This requires at least 1.3GB of bandwidth (at the time of this writing), and is stressful on the master.' . "\n" );
	do {
		$retry = false;
		if( strtolower( prompt( 'Do you have a bootstrap file [Y/n]? ' ) ) != 'n' ) {
			write( 'If it is compressed, you need to decompress it before continuing.' . "\n" );
			$filename = prompt( 'Where is this file (path)? ' );
			if( !file_exists( $filename ) ) {
				$retry = true;
				continue;
			}
			
			write( 'Importing bootstrap file ...' );
			system( 'mysql -h ' . escapeshellarg( $host ) . ' -u ' . escapeshellarg( $user ) . ( $pass == '' ? '' : ' -p' . escapeshellarg( $pass ) ) . ' cbng_editdb < ' . escapeshellarg( $filename ) );
			write( ' Done.' . "\n" );
			
			write( 'Adjusting timestamps ...' );
			
			$thisdump = mysql_fetch_assoc( mysql_query( 'SELECT `id` FROM `dumps` ORDER BY `id` DESC LIMIT 1' ) );
			$thisdump = $thisdump[ 'id' ];
			
			$dumptime = mysql_fetch_assoc( mysql_query( 'SELECT `time` FROM `dumps_remote` WHERE `id` = ' . $thisdump ) );
			$dumptime = $dumptime[ 'time' ];
			
			mysql_query( 'UPDATE `editset` SET `updated` = \'' . mysql_real_escape_string( $dumptime ) . '\'' );
			mysql_query( 'UPDATE `lastupdated` SET `lastupdated` = \'' . mysql_real_escape_string( $dumptime ) . '\'' );
			
			write( ' Done.' . "\n" );
			
			write( 'Updating to master ...' );
			mysql_query( 'CALL update_data()' );
			write( ' Done.' . "\n" );
		} else {
			write( 'Importing lastupdated table ... ' );
			mysql_query( 'INSERT INTO `lastupdated` SELECT * FROM `lastupdated_remote`' );
			write( 'Done.' . "\n" );
			
			write( 'Importing editset table (this will take a while) ... ' );
			mysql_query( 'INSERT INTO `editset` SELECT * FROM `editset_remote`' );
			write( 'Done.' . "\n" );
		}
	} while( $retry );
	
	write( 'You now have a slave copy of the ClueBot NG EditDB.' . "\n" );
?>