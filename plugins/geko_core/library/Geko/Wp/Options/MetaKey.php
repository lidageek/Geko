<?php

// static class container for functions relating to the term_taxonomy table
class Geko_Wp_Options_MetaKey
{
	private static $bCalledInit = FALSE;
	private static $bCalledInstall = FALSE;
	
	private static $aMetaKeyCache = NULL;
	private static $aMetaKeyHash = NULL;
	
	private static $oSqlTable = NULL;
	
	//
	public static function init() {
		if ( !self::$bCalledInit ) {	
			
			global $wpdb;
			
			Geko_Wp_Db::addPrefix( 'geko_meta_key' );
			
			// create tables
			$oSqlTable = new Geko_Sql_Table();
			$oSqlTable
				->create( $wpdb->geko_meta_key, 'm' )
				->fieldMediumInt( 'mkey_id', array( 'unsgnd', 'notnull', 'autoinc', 'prky' ) )
				->fieldVarChar( 'meta_key', array( 'size' => 255, 'unq' ) )
			;			
			
			self::$oSqlTable = $oSqlTable;
			
			self::$bCalledInit = TRUE;
		}	
	}
	
	// create table
	public static function install() {
		if ( !self::$bCalledInstall && is_admin() ) {
			Geko_Wp_Db::createTable( self::$oSqlTable );			
			self::$bCalledInstall = TRUE;
		}
	}
	
	
	//
	public static function loadFromDb() {
		
		global $wpdb;
		
		$aRes = $wpdb->get_results(
			strval( self::$oSqlTable->getSelect() )
		);
		
		foreach ( $aRes as $oItem ) {
			self::$aMetaKeyCache[ $oItem->meta_key ] = $oItem->mkey_id;
			self::$aMetaKeyHash[ $oItem->mkey_id ] = $oItem->meta_key;
		}	
	}
	
	
	//
	public static function getId( $sMetaKey ) {
		
		global $wpdb;
		
		if ( NULL === self::$aMetaKeyCache ) {
			self::loadFromDb();
		}
		
		if ( !isset( self::$aMetaKeyCache[ $sMetaKey ] ) ) {
			
			$wpdb->insert(
				$wpdb->geko_meta_key,
				array( 'meta_key' => $sMetaKey )
			);
			
			$iMkeyId = $wpdb->insert_id;
			self::$aMetaKeyCache[ $sMetaKey ] = $iMkeyId;
			self::$aMetaKeyHash[ $iMkeyId ] = $sMetaKey;
		}
		
		return self::$aMetaKeyCache[ $sMetaKey ];
	}
	
	//
	public static function getKey( $iMkeyId ) {
		
		if ( NULL === self::$aMetaKeyHash ) {
			self::loadFromDb();
		}
				
		return self::$aMetaKeyHash[ $iMkeyId ];
	}
	
}



