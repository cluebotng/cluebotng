<?PHP
	function checkMySQL() {
		if( !Globals::$mysql or !mysql_ping( Globals::$mysql ) ) {
			Globals::$mysql = mysql_pconnect( Config::$mysqlhost . ':' . Config::$mysqlport, Config::$mysqluser, Config::$mysqlpass );
			mysql_select_db( Config::$mysqldb, Globals::$mysql );
		}
	}
	
	