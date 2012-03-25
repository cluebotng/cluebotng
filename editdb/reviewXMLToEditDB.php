<?PHP
	include 'editdbMasterFunctions.php';

	function processEdit( &$stack, &$frame ) {
		$id = $frame[ 'ID' ];
		$isVand = $frame[ 'RealClassification' ] == 'V' ? 1 : 0;
		if( $isVand or $frame[ 'RealClassification' ] == 'C' )
			$isActive = 1;
		else
			$isActive = 0;
		$reviewers = $frame[ 'Vandalism' ] + $frame[ 'Constructive' ] + $frame[ 'Skipped' ];
		$reviewers_agreeing = max( $frame[ 'Vandalism' ], $frame[ 'Constructive' ], $frame[ 'Skipped' ] );
		
		$source = 'Review Interface';
		foreach( $stack as &$stackFrame )
			if( isset( $stackFrame[ 'Name' ] ) )
				$source = $stackFrame[ 'Name' ];
		
		$mysql = getMasterMySQL();
		$row = mysql_fetch_assoc( mysql_query( 'SELECT `isvandalism`, `isactive`, `reviewers`, `reviewers_agreeing` FROM `editset` WHERE `editid` = \'' . mysql_real_escape_string( $id ) . '\'' ) );
		
		if( !$row ) {
			echo 'Inserting ' . $id . ' ...';
			$ret = insertEdit( $id, $isVand, $isActive, $reviewers, $reviewers_agreeing, $source );
			if( $ret )
				echo ' Done.' . "\n";
			else
				echo ' Failed.' . "\n";
		} else {
			$updates = Array();
			if( $row[ 'isvandalism' ] != $isVand )
				$updates[] = '`isvandalism` = \'' . mysql_real_escape_string( $isVand ) . '\'';
			if( $row[ 'isactive' ] != $isActive )
				$updates[] = '`isactive` = \'' . mysql_real_escape_string( $isActive ) . '\'';
			if( $row[ 'reviewers' ] != $reviewers )
				$updates[] = '`reviewers` = \'' . mysql_real_escape_string( $reviewers ) . '\'';
			if( $row[ 'reviewers_agreeing' ] != $reviewers_agreeing )
				$updates[] = '`reviewers_agreeing` = \'' . mysql_real_escape_string( $reviewers_agreeing ) . '\'';
			if( count( $updates ) > 0 ) {
				echo 'Updating ' . $id . ' ...';
				$ret = mysql_query( 'UPDATE `editset` SET ' . implode( ', ', $updates ) . ' WHERE `editid` = \'' . mysql_real_escape_string( $id ) . '\'' );
				if( $ret )
					echo ' Done.' . "\n";
				else
					echo ' Failed.' . "\n";
			}
		}
	}
	
	function processXML( &$xml, &$stack, $name ) {
		$frame = Array();
		$value = null;
		while( $xml->read() )
			switch( $xml->nodeType ) {
				case XMLReader::ELEMENT:
					$elementName = $xml->name;
//					echo str_repeat( "\t", count( $stack ) ) . $elementName . ' {' . "\n";
					if( !$xml->isEmptyElement ) {
						$stack[] = &$frame;
						$return = processXML( $xml, $stack, $elementName );
						if( $return !== null )
							$frame[ $elementName ] = $return;
						array_pop( $stack );
					}/* else
						echo str_repeat( "\t", count( $stack ) ) . '} //' . $elementName . "\n";*/
					break;
					
				case XMLReader::END_ELEMENT:
//					echo str_repeat( "\t", count( $stack ) - 1 ) . '} //' . $name . "\n";
					
					if( $name == 'Edit' )
						processEdit( $stack, $frame );
					
					if( $value !== null )
						return $value;
					
					return null;
					
				case XMLReader::TEXT:
				case XMLReader::CDATA:
					$value = $xml->value;
			}
	}
	
	function processStdin() {
		$xml = new XMLReader(); 
		$xml->open( 'php://stdin' );
		
		$stack = Array();
		processXML( $xml, $stack, 'root' );
	}
	
	processStdin();
?>