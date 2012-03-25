<?PHP
	function run( $epochs, $error, $size ) {
		system( 'PARAMS="' . implode( ' ', array( $epochs, $error, $size ) ) . '" make ann_train_only___auto trial' );
		$report = explode( "\n", file_get_contents( 'trialreport/report.txt' ) );
		$falseLine = $report[ 7 ];
		$falsePercent = 100;
		if( preg_match( '/^False positives: (\d+) \((.*)% of legit edits\)$/', $falseLine, $m ) )
			$falsePercent = $m[ 2 ];
		if( $falsePercent > 0.5 )
			return 0;
		$trueLine = $report[ 8 ];
		if( preg_match( '/^Correct positives: (\d+) \((.*)% of vandal edits\)$/', $trueLine, $m ) )
			return $m[ 2 ];
		return 0;
	}

	function iteration( &$percent, &$epochs, &$error, &$size, $adjEpochs, $adjError, $adjSize ) {
		$iterEpochs = $epochs + rand( -$adjEpochs, $adjEpochs );
		$iterError = $error + rand( -$adjError*10000, $adjError*10000 ) / 10000.0;
		$iterSize = $size + rand( -$adjSize, $adjSize );

		$iterPercent = run( $iterEpochs, $iterError, $iterSize );

		if( $iterPercent > $percent ) {
			$epochs = $iterEpochs;
			$error = $iterError;
			$size = $iterSize;
			$percent = $iterPercent;
		}
	}

	$epochs = $argv[ 1 ];
	$error = $argv[ 2 ];
	$size = $argv[ 3 ];

	$adjEpochs = $argv[ 4 ];
	$adjError = $argv[ 5 ];
	$adjSize = $argv[ 6 ];

	$percent = run( $epochs, $error, $size );
	for(;;) {
		file_put_contents( 'trialreport/auto.txt', "Current:\npercent=$percent%\nepochs=$epochs error=$error size=$size\nadjEpochs=$adjEpochs adjError=$adjError adjSize=$adjSize\n" );
		iteration( $percent, $epochs, $error, $size, $adjEpochs, $adjError, $adjSize );
	}
?>
