<?PHP
	include 'editdbFunctions.php';
	
	$script = array_shift( $argv );
	
	if( $argv[ 0 ] == '--help' )
		error(
			'Each parameter should either be a SQL WHERE condition, limit=N, randomize, or tablescan.' . "\n"
			. 'Example:' . "\n"
			. $script . ' "source = \'j.dealony_reverts\'" "isvandalism = 1" "limit = 10" "randomize"'
		);
	
	$limit = null;
	$conditions = Array();
	$randomize = false;
	
	foreach( $argv as $arg )
		if( substr( $arg, 0, 5 ) == 'limit' ) {
			$arg = explode( '=', $arg );
			$limit = trim( $arg[ 1 ] );
		} else if( $arg == 'randomize' )
			$randomize = true;
		else if( $arg == 'tablescan' )
			$randomize = null;
		else
			$conditions[] = $arg;
	
	queryAndStreamXML( $conditions, $randomize, $limit );
?>