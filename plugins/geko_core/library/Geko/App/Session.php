<?php

//
class Geko_App_Session extends Geko_Singleton_Abstract
{
	
	const ENCODE_NONE = 0;
	const ENCODE_JSON = 1;
	const ENCODE_SERIALIZE = 2;
	
	
	protected $_iSessionId;
	protected $_sSessionKey;
	protected $_bNewSession = FALSE;
	
	protected $_aDbParams = array();
	protected $_oDb;
	
	protected $_bCalledInit = FALSE;
	protected $_bInitDb = FALSE;
	protected $_bCalledSet = FALSE;
	
	protected $_iChunkSize = 200;
	
	protected $_iLastGetIdx = NULL;
	
	
	//
	public function init() {
		
		if ( !$this->_bCalledInit ) {
						
			Zend_Session::start();
						
			$this->_sSessionKey = Zend_Session::getId();
			
			$this->initDb();
			
			$this->_bCalledInit = TRUE;
		}
		
		return $this;
	}
	
	
	// defer until needed
	public function initDb() {

		if ( !$this->_bInitDb ) {
			
			if ( !$oDb = $this->_oDb ) {
				$oDb = call_user_func_array( array( 'Geko_Db', 'factory' ), $this->_aDbParams );
			}
			
			
			//// initialize session tables, if needed
			
			// session table
			
			$oSessTable = new Geko_Sql_Table( $oDb );
			$oSessTable
				->create( '##pfx##session', 's' )
				->fieldBigInt( 'sess_id', array( 'unsgnd', 'notnull', 'autoinc', 'prky' ) )
				->fieldChar( 'sess_key', array( 'size' => 32, 'unq' ) )
				->fieldLongText( 'user_agent' )
				->fieldDateTime( 'date_created' )
				->fieldDateTime( 'date_modified' )
			;
			
			$oDb->tableCreateIfNotExists( $oSessTable );
				
			
			// session data table
			
			$oSessDataTable = new Geko_Sql_Table( $oDb );
			$oSessDataTable
				->create( '##pfx##session_data', 'd' )
				->fieldBigInt( 'sess_id', array( 'unsgnd', 'notnull', 'key' ) )
				->fieldVarChar( 'namespace', array( 'size' => 255 ) )
				->fieldVarChar( 'var_name', array( 'size' => 255 ) )
				->fieldInt( 'idx' )
				->fieldLongText( 'payload' )
				->fieldSmallInt( 'encode' )
				->fieldDateTime( 'date_created' )
				->indexUnq( 'sess_data_idx', array( 'sess_id', 'namespace', 'var_name', 'idx' ) )
			;

			$oDb->tableCreateIfNotExists( $oSessDataTable );
			
			
			
			
			//// check if session id is in database
			
			$sSql = 'SELECT s.sess_id FROM ##pfx##session s WHERE sess_key = ?';
			
			$iSessId = $oDb->fetchOne( $sSql, $this->_sSessionKey );
			
			if ( !$iSessId ) {
				
				// create new entry
				
				$sDateTime = $oDb->getTimestamp();
				$aData = array(
					'sess_key' => $this->_sSessionKey,
					'user_agent' => $_SERVER[ 'HTTP_USER_AGENT' ],
					'date_created' => $sDateTime,
					'date_modified' => $sDateTime
				);
				
				$oDb->insert( '##pfx##session', $aData );
				
				$iSessId = $oDb->lastInsertId();
				
				$this->_bNewSession = TRUE;
			}
			
			
			
			//// assign stuff
			
			$this->_iSessionId = $iSessId;
			$this->_oDb = $oDb;
			
			$this->_bInitDb = TRUE;
			
		}
		
		return $this->_oDb;
	}
	
	
	//
	public function regenerateSessionKey() {
		
		$oDb = $this->initDb();
		
		// if already an existing session, then regenerate session key and update
		if ( !$this->_bNewSession ) {
			
			Zend_Session::regenerateId();	// regenerate
			
			$sRegenSessKey = Zend_Session::getId();
			
			$oDb->update( '##pfx##session', array(
				'sess_key' => $sRegenSessKey,
				'date_modified' => $oDb->getTimestamp()
			), array(
				'sess_id = ?' => $this->_iSessionId
			) );
			
			$this->_sSessionKey = $sRegenSessKey;
		}
		
		return $this;
	}
	
	
	
	//// accessors
	
	
	//
	public function getSessionKey() {
		$this->initDb();
		return $this->_sSessionKey;
	}
	
	//
	public function getSessionId() {
		$this->initDb();
		return $this->_iSessionId;
	}
	
	
	
	//
	public function setDbParams() {
		$this->_aDbParams = func_get_args();
		return $this;
	}
	
	//
	public function setDb( $oDb ) {
		$this->_oDb = $oDb;
		return $this;
	}
	
	//
	public function setChunkSize( $iChunkSize ) {
		$this->_iChunkSize = $iChunkSize;
		return $this;
	}
	
	
	//
	public function set( $sKey, $mValue, $iIdx = NULL, $sNamespace = NULL, $iForceEncoding = NULL ) {
		
		$oDb = $this->initDb();
		
		//// format value
		
		$sValue = '';
		
		$iEncode = $iForceEncoding;
		if ( NULL === $iEncode ) {
			$iEncode = ( !is_scalar( $mValue ) ) ? self::ENCODE_JSON : self::ENCODE_NONE ;
		}
		
		$sValue = $this->encode( $mValue, $iEncode );
		
		
		//// delete old values
		
		$this->delete( $sKey, $iIdx, $sNamespace );
		
		
		
		//// insert
		
		$sDateTime = $oDb->getTimestamp();
		$aData = array(
			'sess_id' => $this->_iSessionId,
			'var_name' => $sKey,
			'payload' => $sValue,
			'encode' => $iEncode,
			'date_created' => $sDateTime
		);

		if ( NULL !== $iIdx ) {
			$aData[ 'idx' ] = $iIdx;
		}

		if ( NULL !== $sNamespace ) {
			$aData[ 'namespace' ] = $sNamespace;
		}
		
		$oDb->insert( '##pfx##session_data', $aData );
		
		$this->_bCalledSet = TRUE;
		
		return $this;
	}
	
	//
	public function get( $sKey, $iIdx = NULL, $sNamespace = NULL ) {
		
		$oDb = $this->initDb();
		
		$this->_iLastGetIdx = NULL;
		$mValue = NULL;
		
		$oSql = new Geko_Sql_Select( $oDb );
		$oSql
			->field( '*' )
			->from( '##pfx##session_data', 'd' )
			->where( 'd.sess_id = ?', $this->_iSessionId )
			->where( 'd.var_name = ?', $sKey )
			->order( 'd.idx', 'ASC' )
			->limit( 1 )
		;
		
		if ( NULL !== $iIdx ) {
			$oSql->where( 'd.idx = ?', $iIdx );
		}

		if ( NULL !== $sNamespace ) {
			$oSql->where( 'd.namespace = ?', $sNamespace );
		}
		
		$aRes = $oDb->fetchRow( strval( $oSql ) );
		
		if ( $aRes ) {
			
			$mValue = $this->decode(
				$aRes[ 'payload' ],
				intval( $aRes[ 'encode' ] )
			);
			
			$this->_iLastGetIdx = $aRes[ 'idx' ];
		}
		
		return $mValue;
	}
	
	
	//
	public function delete( $sKey, $iIdx = NULL, $sNamespace = NULL ) {
		
		$oDb = $this->initDb();

		//// delete old values
		
		$aDelParams = array(
			'sess_id = ?' => $this->_iSessionId,
			'var_name = ?' => $sKey
		);
		
		if ( NULL !== $iIdx ) {
			$aDelParams[ 'idx = ?' ] = $iIdx;
		}
		
		if ( NULL !== $sNamespace ) {
			$aDelParams[ 'namespace = ?' ] = $sNamespace;		
		}
		
		$oDb->delete( '##pfx##session_data', $aDelParams );
		
		return $this;
	}
	
	
	// get the value then delete from db
	public function pluck( $sKey, $iIdx = NULL, $sNamespace = NULL ) {
		
		$mValue = $this->get( $sKey, $iIdx, $sNamespace );
		
		if ( NULL === $iIdx ) {
			$iIdx = $this->_iLastGetIdx;
		}
		
		$this->delete( $sKey, $iIdx, $sNamespace );
		
		return $mValue;
	}
	
	//
	public function setChunked( $sKey, $aValues, $sNamespace = NULL, $iForceEncoding = NULL, $iChunkSize = NULL ) {
		
		if ( NULL === $iChunkSize ) {
			$iChunkSize = $this->_iChunkSize;
		}
		
		if ( is_array( $aValues ) ) {
			
			$i = 0;
			while ( TRUE ) {
				
				$aChunk = Geko_Array::chop( $aValues, $iChunkSize );
				
				$this->set( $sKey, $aChunk, $i, $sNamespace, $iForceEncoding );
				
				if ( 0 == count( $aValues ) ) break;
								
				$i++;
			}
			
		} else {
			throw new InvalidArgumentException( sprintf( 'Provided values must be an array. Type given: %s', gettype( $aValues ) ) );
		}
		
		return $this;
	}
	
	
	//
	public function encode( $mValue, $iEncode ) {

		if ( self::ENCODE_JSON === $iEncode ) {
			$sValue = Zend_Json::encode( $mValue );
		} elseif ( self::ENCODE_SERIALIZE === $iEncode ) {
			$sValue = serialize( $mValue );
		} else {
			$sValue = $mValue;
		}
		
		return $sValue;
	}
	
	//
	public function decode( $sValue, $iEncode ) {

		if ( self::ENCODE_JSON === $iEncode ) {
			$mValue = Zend_Json::decode( $sValue );
		} elseif ( self::ENCODE_SERIALIZE === $iEncode ) {
			$mValue = unserialize( $sValue );
		} else {
			$mValue = $sValue;
		}
		
		return $mValue;
	}
	
	//
	public function destroySession() {

		// update session time
		$oDb = $this->_oDb;
		
		$oDb->delete( '##pfx##session_data', array( 'sess_id = ?' => $this->_iSessionId ) );
		$oDb->delete( '##pfx##session', array( 'sess_id = ?' => $this->_iSessionId ) );
		
		return $this;
	}
	
	//
	public function __destruct() {

		$oDb = $this->_oDb;
		
		if ( $this->_bCalledSet ) {
			
			// update session time
			$oDb->update( '##pfx##session', array(
				'date_modified' => $oDb->getTimestamp()
			), array(
				'sess_id = ?' => $this->_iSessionId
			) );
			
		}
		
		// TO DO: This should be run once a day, and not every request
		// clean-up old session data
		
		$oSql = new Geko_Sql_Select( $oDb );
		$oSql
			->field( 'sess_id' )
			->from( '##pfx##session', 's' )
			->where( 's.date_modified < DATE_SUB( ?, INTERVAL 2 DAY )', $oDb->getTimestamp() )
		;
		
		$aStale = $oDb->fetchCol( strval( $oSql ) );
		if ( count( $aStale ) > 0 ) {
			$sArg = sprintf( 'sess_id IN ( %s )', implode( ', ', $aStale ) );
			$oDb->delete( '##pfx##session_data', array( $sArg ) );
			$oDb->delete( '##pfx##session', array( $sArg ) );		
		}
		
	}
	
	
	
}



