<?php

//
class Geko_Sysomos_Heartbeat
{
		
	protected static $_aContentFilter = array(
		'http://hb.sysomos.com/hb2/sidebar', 'fuck', 'bitch', 'niga', 'nigga', 'gangbang',
		'gang bang', 'pussy', 'eat ass'
	);
		
	
	
	protected $_iHbId;
	
	protected $_oClient = NULL;
	
	protected $_aDefaultParams = array(
		'measure' => array(
			'max' => 20,
			'dRg' => 7,
			'fTs' => 'me'
		),
		'rsscontent' => array(
			'startid' => 0,
			'max' => 120,
			'dRg' => 7,
			'fTs' => 'me'
		)
	);
	
	
	
	//
	public function __construct( $iHbId = NULL ) {
		
		$this->setHbId( $iHbId );
		
	}
	
	
	//// accessors
	
	//
	public function setHbId( $iHbId ) {
		$this->_iHbId = $iHbId;
		return $this;
	}
	
	//
	public static function getContentFilter() {
		return self::$_aContentFilter;
	}
	
	//
	public static function setContentFilter( $aFilter, $bMerge = TRUE ) {
		
		if ( $bMerge ) {
			self::$_aContentFilter = array_merge( self::$_aContentFilter, $aFilter );
		} else {
			self::$_aContentFilter = $aFilter;
		}
		
		return $this;
	}
	
	
	
	//// functional stuff
	
	//
	protected function _getClient() {
		
		if ( !$this->_oClient ) {
			
			$oClient = new Zend_Http_Client();
			$oClient->setConfig( array(
				'timeout' => 240,
				'keepalive' => TRUE
			) );
			
			$this->_oClient = $oClient;
		}
		
		return $this->_oClient;
	}
	
	
	//
	protected function _getResponseBody() {

		try {
			
			$oClient = $this->_getClient();
			
			$oResponse = $oClient->request();
			
			if ( 200 == intval( $oResponse->getStatus() ) ) {
				if ( $sBody = $oResponse->getBody() ) {
					return $sBody;
				}
			}
			
		} catch( Exception $e ) {
		
		}
		
		return FALSE;
	}
	
	
	//
	public function _getResult( $sPage, $aParams = array() ) {
		
		$aDefParams = $this->_aDefaultParams[ $sPage ];
		
		if ( is_array( $aDefParams ) ) {
			$aParams = array_merge( $aDefParams, $this->formatFilterParams( $aParams ) );
		}
		
		
		//// start fixes
		
		if ( !$aParams[ 'dRg' ] ) {
			unset( $aParams[ 'dRg' ] );		// unset if 0, otherwise it will cause problems
		}
		
		$sToday = date( 'Y-m-d' );
		
		if ( !$aParams[ 'dRg' ] ) {
			
			// start day given, but no end day
			if ( $aParams[ 'sDy' ] && !$aParams[ 'eDy' ] ) {
				$aParams[ 'eDy' ] = $sToday;
			}

			// end day given, but no start day
			if ( $aParams[ 'eDy' ] && !$aParams[ 'sDy' ] ) {
				$aParams[ 'sDy' ] = $sToday;
			}
		}
		
		
		//// end fixes
		
		$oClient = $this->_getClient();
		
		$oClient->resetParameters();
		
		$oUrl = new Geko_Uri( sprintf( '%s/%s', Geko_Sysomos::getUrl( 'heartbeat' ), $sPage ) );
		$oUrl->setVars( array_merge( array(
			'apiKey' => Geko_Sysomos::getValue( 'api_key' ),
			'hid' => $this->_iHbId
		), $aParams ) );
		
		$sUrl = strval( $oUrl );
		
		$oClient->setUri( $sUrl );
		
		
		$aRes = array( '_uri' => $sUrl );
		
		if ( $sBody = $this->_getResponseBody() ) {
			return array( $aRes, new SimpleXMLElement( $sBody ) );
		}
		
		return NULL;
	}
	
	
	
	//// helpers
	
	
	// for consistency
	public function formatTag( $mValue ) {
		return $mValue;
	}
	
	//
	public function formatCountry( $mValue ) {
		
		if ( is_array( $mValue ) ) {
			return array_map( array( $this, 'formatCountry' ), $mValue );
		}
		
		return sprintf( '~COUNTRY~%s', strtoupper( $mValue ) );
	}
	
	//
	public function formatType( $mValue ) {
	
		if ( is_array( $mValue ) ) {
			return array_map( array( $this, 'formatType' ), $mValue );
		}
			
		$aTypes = array(
			'twitter' => 't',
			'blogpost' => 'b',
			'forum' => 'f',
			'facebook' => 'k',
			'news' => 'n',
			'youtube' => 'y'
		);
		
		if ( !$sTypeCode = $aTypes[ $mValue ] ) {
			$sTypeCode = $mValue;					// use the given value
		}
		
		return sprintf( '~SOURCE~%s', $sTypeCode );
	}
	
	// valid values: pos, neg, none
	public function formatSentiment( $mValue ) {
	
		if ( is_array( $mValue ) ) {
			return array_map( array( $this, 'formatSentiment' ), $mValue );
		}
			
		return sprintf( '~SENTIMENT~%s', strtolower( $mValue ) );
	}
	
	
	
	//
	public function formatFilterParams( $aParams ) {
		
		$aFilterTypes = array( 'tag', 'country', 'type', 'sentiment' );
		
		
		// only use filters if "fTs" not explicitly set
		if ( !$aParams[ 'fTs' ] ) {
			
			$aFilters = array();		// combine filters here
			
			// go through each filter type
			foreach ( $aFilterTypes as $sFilterType ) {
				
				$sFormatMethod = sprintf( 'format%s', ucfirst( $sFilterType ) );
				
				if ( $mValue = $aParams[ $sFilterType ] ) {
					
					$aFilters = array_merge( $aFilters,
						Geko_Array::wrap( $this->$sFormatMethod( $mValue ) )
					);
					
					unset( $aParams[ $sFilterType ] );
				}
				
			}
			
			if ( count( $aFilters ) > 0 ) {
				$aParams[ 'fTs' ] = implode( '%2C', $aFilters );
			}
		}
		
		
		// only use filters if "cTs" not explicitly set
		if ( !$aParams[ 'cTs' ] ) {
			
			$aSubFilters = array();
			
			$aSubParams = $aParams[ 'subfilter' ];
			
			// go through each filter type
			foreach ( $aFilterTypes as $sFilterType ) {
				
				$sFormatMethod = sprintf( 'format%s', ucfirst( $sFilterType ) );
				
				if ( $mValue = $aSubParams[ $sFilterType ] ) {
					
					$aSubFilters = array_merge( $aSubFilters,
						Geko_Array::wrap( $this->$sFormatMethod( $mValue ) )
					);
				}	
			}
			
			unset( $aParams[ 'subfilter' ] );
			
			if ( count( $aSubFilters ) > 0 ) {
				$aParams[ 'cTs' ] = implode( '%2C', $aSubFilters );
			}
		}
		
		
		
		//// report intervals
		
		// number of days
		if ( !$aParams[ 'dRg' ] ) {
			
			if ( isset( $aParams[ 'numdays' ] ) ) {
				
				$aParams[ 'dRg' ] = intval( $aParams[ 'numdays' ] );				
				
				unset( $aParams[ 'numdays' ] );
			}
		}
		
		// start day
		if ( !$aParams[ 'sDy' ] ) {
		
			if ( $sStartDay = $aParams[ 'startday' ] ) {
				$aParams[ 'sDy' ] = $sStartDay;
			}
			
			unset( $aParams[ 'startday' ] );
		}
		
		// end day
		if ( !$aParams[ 'eDy' ] ) {
		
			if ( $sEndDay = $aParams[ 'endday' ] ) {
				$aParams[ 'eDy' ] = $sEndDay;
			}
			
			unset( $aParams[ 'endday' ] );
		}
		
		
		
		return $aParams;
	}
	
	
	
	//// params
	
	// hid: Integer; heartbeat id
	// dRg: Integer; Number of days
	// sDy: Date (2010-06-08); Start day
	// eDy: Date (2010-06-08); End day
	// fTs: String; list of filters
	// cTs: String; Breakdown by filter
	
	
	
	//// business-end methods
	
	//
	public function getInfo( $aParams = array(), $aOptions = array() ) {
		
		list( $aRes, $oXml ) = $this->_getResult( 'info', $aParams );
		
		if ( $oXml ) {
			
			$oResponse = $oXml->response[ 0 ];
			
			$aTags = $oResponse->tag;
			
			$aTagsFmt = array();
			foreach ( $aTags as $oTag ) {
				$aTagsFmt[ strval( $oTag->name ) ] = strval( $oTag->displayName );
			}
			
			$aRes[ 'name' ] = strval( $oResponse->name );
			$aRes[ 'tags' ] = $aTagsFmt;
			$aRes[ 'count' ] = count( $aTagsFmt );
		}
		
		return $aRes;
	}
	
	
	//
	public function getMeasure( $aParams = array(), $aOptions = array() ) {
		
		//// do stuff
		
		list( $aRes, $oXml ) = $this->_getResult( 'measure', $aParams );
		
		if ( $oXml ) {
			
			$oResponse = $oXml->response[ 0 ];
			
			$aTagStats = $oResponse->tagStats;
			
			$aTags = array();
			
			$bFirst = TRUE;
			foreach ( $aTagStats as $oTagStat ) {
				
				$iMatchCount = intval( $oTagStat->matchCount );
				
				if ( $bFirst ) {
					
					$aRes[ 'mentions' ] = $iMatchCount;
					$aRes[ 'tag' ] = strval( $oXml->request[ 0 ]->filterTags );
					
					$bFirst = FALSE;
					
				} else {
					
					$sKey = strval( $oTagStat->tag );
					$sDisplayName = strval( $oTagStat->tagDisplayName );
					
					$aKeyParts = explode( '~', trim( $sKey, '~' ) );
					
					if ( count( $aKeyParts ) > 1 ) {
						list( $sCat, $sSubKey ) = $aKeyParts;
					} else {
						$sCat = 'tag';
						$sSubKey = $sKey;
					}
					
					$sCat = strtolower( $sCat );
					
					$aTags[ $sCat ][ $sSubKey ] = array(
						'title' => $sDisplayName,
						'mentions' => $iMatchCount
					);
					
				}
			}
			
			$aRes[ 'tags' ] = $aTags;	
		}
		
		return $aRes;
	}
	
	
	
	//
	public function getRssContent( $aParams = array(), $aOptions = array() ) {
		
		//// do stuff
		
		list( $aRes, $oXml ) = $this->_getResult( 'rsscontent', $aParams );
		
		$aOptions = array_merge( array(
			'truncate_length' => 120
		), $aOptions );
		
		if ( $oXml ) {
			
			$iTruncLen = intval( $aOptions[ 'truncate_length' ] );
			
			$aFeed = array();
			
			$aBeats = $oXml->beatResponse[ 0 ]->beat;
			
			foreach ( $aBeats as $oBeat ) {
				
				$sDocId = strval( $oBeat->docid );
				$aSeq = explode( ':', $sDocId );
				$sTime = strval( $oBeat->time );
				$sTitle = strval( $oBeat->title );
				$sContent = strip_tags( strval( $oBeat->content ) );
				$sType = strtolower( strval( $oBeat->mediaType ) );
				
				if ( 'twitter' == $sType ) {
					$sTwtJsonUrl = strval( $oBeat->tweetJsonLink );
					if ( $aTwtJson = $this->getTwtResponse( $sTwtJsonUrl ) ) {
						$sTitle = '@' . $aTwtJson[ 'user' ][ 'screen_name' ];
						$sContent = $aTwtJson[ 'text' ];				
					} else {
						$sTitle = '';
						$sContent = '';
					}
				}
				
				// clean-up
				$sContent = preg_replace( '/[\s_]+/ms', ' ', $sContent );
				$sContent = str_replace( ' ,', ',', $sContent );
				$sContent = str_replace( ' :', ':', $sContent );
				$sContent = str_replace( '-- -', '--', $sContent );
				$sContent = str_replace( ' .', '.', $sContent );
				
				if ( $this->okayContent( $sContent ) ) {
					$aFeed[] = array(
						'id' => strval( 'doc-' . ( str_replace( ':', '-', $sDocId ) ) ),
						'seq' => intval( $aSeq[ 1 ] ),
						'time' => $sTime,
						'ts' => strtotime( $sTime ),
						'type' => $sType,
						'title' => $sTitle,
						'content' => $sContent,
						'excerpt' => strval( Geko_String::truncate( $sContent, $iTruncLen ) ),
						'sentiment' => strtolower( strval( $oBeat->sentiment ) )
					);
				}
			}
				
			usort( $aFeed, 'sortFeed' );
			
			$aRes[ 'feed' ] = $aFeed;
			$aRes[ 'filtered_count' ] = count( $aFeed );
			$aRes[ 'unfiltered_count' ] = count( $aBeats );
			
		}
		
		return $aRes;		
	}
	

	// do some basic content filtering
	public function okayContent( $sContent ) {
		
		$sContent = strtolower( trim( $sContent ) );
		
		if ( $sContent ) {
			
			foreach ( self::$_aContentFilter as $sFilter ) {
				if ( FALSE !== strpos( $sContent, $sFilter ) ) {
					return FALSE;					// found bad string !!!
				}
			}
			
			return TRUE;
		}
		
		return FALSE;
	}
	
	//
	function getTwtResponse( $sJsonUrl ) {
		
		$aJson = NULL;
		
		$oClient = $this->_getClient();
		
		$oClient->resetParameters();
		
		$oClient->setUri( $sJsonUrl );
		
		$sJson = $this->_getResponseBody();
		
		// echo $sJsonUrl . '<br />';
		
		/* /
		$sHash = md5( $sJsonUrl );
		
		$sQuery = sprintf( 'SELECT content FROM twitter WHERE hash = "%s"', $sHash );
		$rQuery = mysql_query( $sQuery );
		
		if ( !$sJson = Geko_Db_Mysql::fetchValue( $rQuery ) ) {
	
			$bDoPoll = FALSE;
			
			$sQuery2 = 'SELECT val FROM vars WHERE name = "twt_next_poll"';
			$rQuery2 = mysql_query( $sQuery2 );
					
			if ( $sNextPollTime = Geko_Db_Mysql::fetchValue( $rQuery2 ) ) {
				$iUnixTs = strtotime( $sNextPollTime );
				if ( time() >= $iUnixTs ) {
					// unset the next poll time
					Geko_Db_Mysql::update(
						'vars',
						array( 'val' => '' ),
						array( 'name' => 'twt_next_poll' )
					);
					$bDoPoll = TRUE;		
				}
			} else {
				$bDoPoll = TRUE;
			}
			
			if ( $bDoPoll ) {
				if ( $sJson = getResponse( $sJsonUrl ) ) {
					Geko_Db_Mysql::insert(
						'twitter',
						array(
							'hash' => $sHash,
							'url' => $sJsonUrl,
							'content' => $sJson
						)
					);
				} else {
					// set next poll time value
					$sMysqlTs = Geko_Db_Mysql::getTimestamp( time() + ( 60 * 62 ) );
					Geko_Db_Mysql::update(
						'vars',
						array( 'val' => $sMysqlTs ),
						array( 'name' => 'twt_next_poll' )
					);
				}
			}
			
		}
		/* */
		
		if ( $sJson ) $aJson = Zend_Json::decode( $sJson );
		
		return $aJson;
	}

	
	
}


