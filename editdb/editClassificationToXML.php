<?PHP
	include '../bot/cbng.php';
	
	date_default_timezone_set( 'UTC' );
	
	function xmlPart( $xml, $name, $data ) {
		if( !is_array( $data ) ) {
			$xml->writeElement( $name, $data );
			return;
		}
		$xml->startElement( $name );
		
		foreach( $data as $key => $value )
			xmlPart( $xml, $key, $value );
		
		$xml->endElement();
	}
	
	$xml = new XMLWriter();
	$xml->openURI( 'php://output' );
	$xml->setIndent( true );
	$xml->startDocument( '1.0', 'UTF-8' );
	$xml->startElement( 'WPEditSet' );

	$in = fopen( 'php://stdin', 'r' );
	while( !feof( $in ) ) {
		$line = trim( fgets( $in, 512 ) );
		
		if( !$line )
			continue;
		
		list( $id, $type ) = explode( ' ', $line, 2 );
		
		switch( $type[ 0 ] ) {
			case 'V': list( $isVand, $isActive ) = Array( 1, 1 ); break;
			case 'S':
			case 'U': list( $isVand, $isActive ) = Array( 0, 0 ); break;
			case 'C': list( $isVand, $isActive ) = Array( 0, 1 ); break;
		}

		if( !$isActive )
			continue;
		
		$data = oldData( $id );
		xmlPart( $xml, 'WPEdit', $data );
	}
	fclose( $in );
	
	$xml->endElement();
	$xml->endDocument();
	$xml->flush();
?>