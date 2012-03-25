<?PHP
	include 'editdbMasterFunctions.php';
	
	function compareArray( $arr1, $arr2, $path = 'root' ) {
		if( !is_array( $arr1 ) or !is_array( $arr2 ) ) {
			if( $arr1 != $arr2 )
				echo 'Mismatch at ' . $path . '.  ' . $arr1 . ' != ' . $arr2 . '.  ';
			return $arr1 == $arr2;
		}
		
		foreach( $arr1 as $key => $value )
			if( isset( $arr2[ $key ] ) ) {
				if( !compareArray( $value, $arr2[ $key ], $path . '->' . $key ) ) {
					echo 'Mismatch at ' . $path . '.  compareArray() returned false.  ';
					return false;
				}
			} else {
				echo 'Mismatch at ' . $path . '.  No corrisponding key.  ';
				return false;
			}
		
		return true;
	}
	
	function processRow( $id, $isVand, $isActive, $reviewers, $reviewers_agreeing, $source ) {
		echo "Getting master data\033[1;33m ... \033[1;32m";
		$dbData = getMasterData( $id );
		echo "Getting Wikipedia data\033[1;33m ... \033[1;32m";
		$apiData = oldData( $id );
		
		if( !compareArray( $dbData, $apiData ) ) {
			echo "Inserting\033[1;33m ... \033[1;32m";
			insertEdit( $id, $isVand, $isActive, $reviewers, $reviewers_agreeing, $source, $apiData );
		} else
			echo "Nothing to do\033[1;33m ... \033[1;32m";
	}
	
	echo "\033[0;36m================================================================================\033[0m\n";
	echo "\033[0;36m==================================\033[1;31m Warning! \033[0;36m====================================\033[0m\n";
	echo "\033[0;36m================================================================================\033[0m\n";
	echo "\033[0;36m=\033[0;32m This will likely take a very long time to run, and will possibly update a    \033[0;36m=\033[0m\n";
	echo "\033[0;36m=\033[0;32m lot of records.  If that happens, it will also flood all of the slaves with  \033[0;36m=\033[0m\n";
	echo "\033[0;36m=\033[0;32m very large updates.  Please consider this before continuing.  Thanks.        \033[0;36m=\033[0m\n";
	echo "\033[0;36m================================================================================\033[0m\n";
	echo "\n";
	echo "\033[1;31mIf you do not wish to go through with this, hit CTRL+C now!\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 10 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 9 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 8 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 7 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 6 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 5 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 4 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 3 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 2 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 1 \033[1;31m<<<\x07\033[0m\n";
	sleep( 1 );
	echo "\033[1;31m>>>\033[1;32m 0 \033[1;31m<<<\x07\033[0m\n";
	sleep( 2 );
	echo 'Please wait while the master database is refreshed.' . "\n";
	
	
	$mysql = getMasterMySQL();
	$total = mysql_fetch_assoc( mysql_query( 'SELECT COUNT(*) AS `count` FROM `editset`' ) );
	$total = $total[ 'count' ];
	$count = 0;
	
	$results = mysql_query( 'SELECT `editid`, `isvandalism`, `isactive`, `reviewers`, `reviewers_agreeing`, `source` FROM `editset`' );
	while( $row = mysql_fetch_assoc( $results ) ) {
		$count++;
		echo "\033[1;32m" . round( $count * 100 / $total ) . "\033[1;31m% (\033[1;32m" . $count . "\033[1;31m/\033[1;32m" . $total . "\033[1;31m)\033[1;36m: \033[1;32m" . $row[ 'editid' ] . "\033[1;33m ... \033[1;32m";
		processRow( $row[ 'editid' ], $row[ 'isvandalism' ], $row[ 'isactive' ], $row[ 'reviewers' ], $row[ 'reviewers_agreeing' ], $row[ 'source' ] );
		echo "\033[1;32m Done!\033[0m\n";
	}
?>