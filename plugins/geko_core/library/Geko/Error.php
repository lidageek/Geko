<?php

// configure error reporting
class Geko_Error
{
	
	//
	public static function start() {
		
		if ( $_REQUEST[ '__enable_error_reporting' ] ) {
			ini_set( 'display_errors', 1 );
			ini_set( 'scream.enabled', 1 );
			error_reporting( E_ALL );
		} else {
			ini_set( 'display_errors', 1 );
			error_reporting( E_ALL�^�E_NOTICE );
		}
		
	}
	

}


