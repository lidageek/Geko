<?php

//
class Geko_Wp_Log_Manage extends Geko_Wp_Initialize
{
	private static $sManagementCapability = '';
	private static $sManagementPageTitle = 'Access Log Reports';	
	private static $sManagementMenuTitle = 'Logs';	
	private static $bHasManagementCapability = FALSE;
	private static $aLogInstances = array();
	
	protected $_sTableSuffix = '';
	protected $_sTableName = '';
	protected $_sMetaTableName = '';
	protected $_sLogTitle = '';
	protected $_bReportOnly = FALSE;
	
	protected $_bCalledInstall = FALSE;
	protected $_bUseMetaTable = FALSE;
	
	protected $_sEntityClass;
	protected $_sQueryClass;
	protected $_sExportExcelHelperClass;
	
	protected $_sExportCapability = '';
	protected $_bHasExportCapability = FALSE;
	
	protected $_aPlugins = array();
	
	protected $_sMinDateCreated = '';
	protected $_sMaxDateCreated = '';
	
	protected $_oPrimaryTable;
	protected $_oMetaTable;
	
	
	//
	protected function __construct() {
		
		if ( $this->_sTableSuffix && !$this->_sTableName ) {
			$this->_sTableName = 'geko_logs_' . $this->_sTableSuffix;
		}
		
		if ( $this->_sTableName && !$this->_sMetaTableName && $this->_bUseMetaTable ) {
			$this->_sMetaTableName = $this->_sTableName . '_meta';
		}
		
		parent::__construct();
		
		if ( !$this->_sLogTitle && $this->_sTableSuffix ) {
			$this->_sLogTitle = ucwords( str_replace( '_', ' ', $this->_sTableSuffix ) );
		}
		
		if ( $this->_sTableSuffix ) {
			self::$aLogInstances[ $this->_sTableSuffix ] = $this;
		}
	}
	
	
	//// init
	
	
	
	//
	public function init() {
		Geko_Wp_Options_MetaKey::init();
		return parent::init();
	}
	
	//
	public function startSession() {
		@session_start();
		return $this;
	}
	
	//
	public function add() {
		
		parent::add();
		
		global $current_user, $wpdb;
		
		////
		
		$this->_sEntityClass = Geko_Class::resolveRelatedClass(
			$this, '_Manage', ''
		);
		
		////
		
		if ( !$this->_bReportOnly ) {
			
			$sTableName = $this->_sTableName;
			Geko_Wp_Db::addPrefix( $sTableName );
			
			$oSqlTable = new Geko_Sql_Table();
			$oSqlTable
				->create( $wpdb->$sTableName, 'l' )
				->fieldBigInt( 'log_id', array( 'unsgnd', 'notnull', 'autoinc', 'prky' ) )
				->fieldInt( 'remote_ip', array( 'unsgnd' ) )
				->fieldLongText( 'url' )
				->fieldLongText( 'user_agent' )
				->fieldBigInt( 'user_id', array( 'unsgnd' ) )
				->fieldDateTime( 'date_created' )
				->option( 'engine', 'archive' )
			;
			
			$this->_oPrimaryTable = $this->modifyPrimaryTable( $oSqlTable );
			
			if ( $this->_bUseMetaTable ) {
				
				$sMetaTableName = $this->_sMetaTableName;
				Geko_Wp_Db::addPrefix( $this->_sMetaTableName );
				
				$oSqlTable = new Geko_Sql_Table();
				$oSqlTable
					->create( $wpdb->$sMetaTableName, 'lm' )
					->fieldBigInt( 'log_id', array( 'unsgnd' ) )
					->fieldSmallInt( 'mkey_id', array( 'unsgnd' ) )
					->fieldLongText( 'meta_value' )
					->option( 'engine', 'archive' )
				;
				
				$this->_oMetaTable = $this->modifyMetaTable( $oSqlTable );
				
			}
		
		}
		
		if ( !$this->_sExportCapability ) {
			$this->_sExportCapability = 'export_' . $this->_sTableSuffix . '_logs';
		}
		
		if (
			is_user_logged_in() && 
			$current_user && 
			$current_user->has_cap( $this->_sExportCapability ) 
		) {
			$this->_bHasExportCapability = TRUE;
		}
		
		return $this;
	}
	
	// hook method
	public function modifyPrimaryTable( $oSqlTable ) {
		return $oSqlTable;
	}

	// hook method
	public function modifyMetaTable( $oSqlTable ) {
		return $oSqlTable;
	}
	
	//
	public function affix() {
		
		self::$sManagementCapability = 'view_log_reports';
		
		if (
			is_user_logged_in() && 
			$current_user && 
			$current_user->has_cap( self::$sManagementCapability ) 
		) {
			self::$bHasManagementCapability = TRUE;
		}
		
		return $this;
	}
	
	//
	public function addAdmin() {
		
		parent::addAdmin();
		
		$this->install();
		
		////
		
		$this->_sQueryClass = Geko_Class::resolveRelatedClass(
			$this->_sEntityClass, '', '_Query', $this->_sQueryClass
		);

		$this->_sExportExcelHelperClass = Geko_Class::resolveRelatedClass(
			$this->_sEntityClass, '', '_ExportExcelHelper', $this->_sExportExcelHelperClass
		);
		

		$oWpRole = get_role( 'administrator' );
		if ( !$oWpRole->has_cap( $this->_sExportCapability ) ) {
			$oWpRole->add_cap( $this->_sExportCapability );
			// $oWpRole->remove_cap( 'manage_geko_wp_log_manage' );
		}
		
		
		////
		add_action( 'admin_init', array( $this, 'doActions' ) );
		
		return $this;
	}
	
	//
	public function enqueueAdmin() {
		
		parent::enqueueAdmin();
		
		wp_enqueue_style( 'geko-jquery-ui-wp' );
		
		wp_enqueue_script( 'geko-jquery-ui-datepicker' );
		
		return $this;
	}

	
	//
	public function affixAdmin() {
		
		// assign management capabilities to the admin role
		$oWpRole = get_role( 'administrator' );
		if ( !$oWpRole->has_cap( self::$sManagementCapability ) ) {
			$oWpRole->add_cap( self::$sManagementCapability );
			// $oWpRole->remove_cap( 'manage_geko_wp_log_manage' );
		}
				
		add_action( 'admin_menu', array( $this, 'attachPage' ) );
		
		return $this;
	}
	
	
	// create table
	public function install() {
		
		if ( $this->_sTableName && !$this->_bReportOnly ) {
			
			Geko_Wp_Options_MetaKey::install();
			Geko_Wp_Db::createTable( $this->_oPrimaryTable );
			
			if ( $this->_bUseMetaTable && $this->_oMetaTable ) {
				Geko_Wp_Db::createTable( $this->_oMetaTable );
			}
		}
		
		return $this;
	}
	
	
	//
	public function attachPage() {
		add_submenu_page( 'tools.php', self::$sManagementPageTitle, self::$sManagementMenuTitle, self::$sManagementCapability, __CLASS__, array( $this, 'displayPage' ) );
	}
	
	//
	public function initDateCreated() {
		
		global $wpdb;
		
		if ( !$this->_sMinDateCreated || !$this->_sMaxDateCreated ) {
			
			$sTableName = $this->_sTableName;
			
			$oQuery = new Geko_Sql_Select();
			$oQuery
				->field( 'MIN( l.date_created )', 'min_date_created' )
				->field( 'MAX( l.date_created )', 'max_date_created' )
				->from( $wpdb->$sTableName, 'l' )
			;
			
			$oQuery = $this->modifyDateCreatedQuery( $oQuery );
			
			$oRes = $wpdb->get_row( strval( $oQuery ) );
			
			$this->_sMinDateCreated = $oRes->min_date_created;
			$this->_sMaxDateCreated = $oRes->max_date_created;
		}
	}
	
	//
	public function modifyDateCreatedQuery( $oQuery ) {
		return $oQuery;
	}
	
	
	
	//// accessors
		
	//
	public function getTableSuffix() {
		return $this->_sTableSuffix;
	}

	//
	public function getLogTitle() {
		return $this->_sLogTitle;
	}
	
	//
	public function getTableName() {
		return $this->_sTableName;
	}

	//
	public function getMetaTableName() {
		return $this->_sMetaTableName;
	}
	
	//
	public function getPrefixedTableName() {
		global $wpdb;
		$sTableName = $this->_sTableName;
		return $wpdb->$sTableName;
	}

	//
	public function getPrefixedMetaTableName() {
		global $wpdb;
		$sTableName = $this->_sMetaTableName;
		return $wpdb->$sTableName;
	}



	//
	public function getPrimaryTable() {
		return $this->_oPrimaryTable;
	}
	
	//
	public function getMetaTable() {
		return $this->_oMetaTable;	
	}
	
	
	//
	public function getTableFields( $mSqlTable ) {
		
		if ( is_string( $mSqlTable ) ) {
			$oSqlTable = $this->getTable( $mSqlTable );
		} else {
			$oSqlTable = $mSqlTable;
		}
		
		if ( $oSqlTable ) {
			
			$aRet = array();

			// format db fields as Geko_Wp_Options_Field
			$aDbFields = $oSqlTable->getFields( TRUE );
			
			foreach ( $aDbFields as $sField => $oDbField ) {
				$aRet[ $sField ] = Geko_Wp_Options_Field::wrapSqlField( $oDbField );
			}
			
			return $aRet;
		}
		
		return array();
	}
	
	//
	public function getPrimaryTableFields() {
		return $this->getTableFields( $this->getPrimaryTable() );
	}
	
	//
	public function getMetaTableFields() {
		return $this->getTableFields( $this->getMetaTable() );
	}
	
	//
	public function getTablePrimaryKeyField( $mSqlTable ) {

		if ( is_string( $mSqlTable ) ) {
			$oSqlTable = $this->getTable( $mSqlTable );
		} else {
			$oSqlTable = $mSqlTable;
		}
		
		if ( $oSqlTable ) {
			if ( $oPkf = $oSqlTable->getPrimaryKeyField() ) {
				return Geko_Wp_Options_Field::wrapSqlField( $oPkf );
			}
		}
		
		return NULL;
	}
	
	//
	public function getPrimaryTablePrimaryKeyField() {
		return $this->getTablePrimaryKeyField( $this->getPrimaryTable() );
	}
	
	
	
	
	
	//
	public function getMinDateCreated() {
		$this->initDateCreated();
		return $this->_sMinDateCreated;
	}

	//
	public function getMaxDateCreated() {
		$this->initDateCreated();
		return $this->_sMaxDateCreated;
	}
	
	//
	public function getExportCapability() {
		return $this->_bHasExportCapability;
	}
	
	
	
	//
	public function isCurrentPage() {
		return (
			( __CLASS__ == $_REQUEST[ 'page' ] ) && 
			( $this->_sTableSuffix == $_REQUEST[ 'log_report' ] )
		);
	}
	
	
	
	
	
	
	//// plugin management
	
	//
	public function registerPlugin( $oPlugin ) {
		$this->_aPlugins[] = $oPlugin;
		return $this;
	}
	
	//
	public function registerPlugins() {
		
		$aArgs = func_get_args();
		
		foreach ( $aArgs as $sPlugin ) {
			if ( $sPluginClass = Geko_Class::resolveRelatedClass(
				$this->_sEntityClass, '', '_Plugin_' . $sPlugin
			) ) {
				Geko_Singleton_Abstract::getInstance( $sPluginClass )->init();
			}
		}
		
		return $this;
	}
	
	
	
	//// page display
	
	//
	public function displayPage() {
		
		$oUrl = new Geko_Uri();
		$oUrl->unsetVar( 'log_report' );
		
		$sHiddenFields = $oUrl->getVarsAsHiddenFields();
		
		if ( $sLogReport = $_GET[ 'log_report' ] ) {
			$oCurLog = self::$aLogInstances[ $sLogReport ];
			$sCurLogClass = get_class( $oCurLog );
			$oUrl->setVar( 'page', $sCurLogClass );
			$sCurLogHiddenFields = $oUrl->getVarsAsHiddenFields();
		}
		
		$oUrl->unsetVars();
		$sAction = strval( $oUrl );
				
		?>
		<div class="wrap">
			
			<div id="icon-tools" class="icon32"><br /></div>		
			<h2><?php echo self::$sManagementPageTitle; ?></h2>
			
			<h3>Generate Report</h3>
						
			<script type="text/javascript">
				jQuery( document ).ready( function( $ ) {
					$( '#log_report' ).change( function() {
						$( '#log_report_form' ).submit();
					} );
				} );
			</script>
			
			<form id="log_report_form" action="<?php echo $sAction; ?>" method="get">
				<table class="form-table">
					<tr>
						<th><label for="log_report">Report Type</label></th>
						<td>
							<select name="log_report" id="log_report">
								<option value="">Select a Log Report Type</option>
								<?php foreach ( self::$aLogInstances as $oLog ):
									if ( $oLog->getExportCapability() ):
										$sTableSuffix = $oLog->getTableSuffix();
										$sSelected = ( $sLogReport == $sTableSuffix ) ? ' selected="selected" ' : ''; 
										?><option value="<?php echo $sTableSuffix; ?>" <?php echo $sSelected; ?> ><?php echo $oLog->getLogTitle(); ?></option><?php
									endif;
								endforeach; ?>
							</select><br />
							<span class="description">Select the log report type to be generated.</span>
						</td>
					</tr>
				</table>
				<?php echo $sHiddenFields; ?>
			</form>
			
			<?php if ( $oCurLog && $oCurLog->getMinDateCreated() && $oCurLog->getMaxDateCreated() ):
				
				$sOpAction = 'export';
				$sNonceField = $sCurLogClass . $sOpAction;
				
				$aJsonParams = array(
					'date' => array(
						'min' => $this->formatDateForRange( $oCurLog->getMinDateCreated() ),
						'max' => $this->formatDateForRange( $oCurLog->getMaxDateCreated() )
					)
				);
				
				?><script type="text/javascript">
					
					jQuery( document ).ready( function( $ ) {
						
						var oParams = <?php echo Zend_Json::encode( $aJsonParams ); ?>;
						var dmn = oParams.date.min;
						var dmx = oParams.date.max;
						
						$( '#min_date, #max_date' ).datepicker( {
							minDate: new Date( dmn[ 0 ], dmn[ 1 ], dmn[ 2 ] ),
							maxDate: new Date( dmx[ 0 ], dmx[ 1 ], dmx[ 2 ] ),
							appendText: '(yyyy/mm/dd)',
							dateFormat: 'yy/mm/dd'
						} );
						
					} );
					
				</script>
				<h3>Report for: <?php echo $oCurLog->getLogTitle(); ?></h3>
				<form id="export_report_form" action="<?php echo $sAction; ?>" method="post">
					<?php if ( function_exists( 'wp_nonce_field' ) ) wp_nonce_field( $sNonceField ); ?>
					<table class="form-table">
						<tr>
							<th><label for="min_date">Start Date</label></th>
							<td><input type="text" id="min_date" name="min_date" /></td>
						</tr>
						<tr>
							<th><label for="max_date">End Date</label></th>
							<td><input type="text" id="max_date" name="max_date" /></td>
						</tr>
						<?php $oCurLog->outputFilters( $oCurLog ); ?>
					</table>
					<input type="hidden" name="action" value="<?php echo $sOpAction; ?>" />
					<?php echo $sCurLogHiddenFields; ?>
					<p class="submit"><input type="submit" class="button-primary" name="submit" value="Download Report (Excel)"></p>
				</form>
			
			<?php else: ?>
				
				<p>There are no logs to display.</p>
				
			<?php endif; ?>
			
		</div>
		<?php
	}
	
	// date range helper
	
	//
	public function formatDateForRange( $sMysqlDatetime ) {
		$aRet = explode( '-', mysql2date( 'Y-n-j', $sMysqlDatetime ) );
		$aRet[ 1 ] = $aRet[ 1 ] - 1;
		return $aRet;
	}
	
	
	// hook
	public function outputFilters( $oCurLog ) { }
	
	
	
	
	//// export methods
	
	//
	public function doActions() {
		
		@session_start();
		
		$sAction = Geko_String::coalesce( $_POST[ 'action' ], $_GET[ 'action' ] );
		$sCurPage = Geko_String::coalesce( $_POST[ 'page' ], $_GET[ 'page' ] );
		
		if (
			( $this->_sInstanceClass == $sCurPage ) && 
			( 'export' == $sAction ) && 
			( $this->_bHasExportCapability ) &&
			( check_admin_referer( $this->_sInstanceClass . $sAction ) )
		) {
			
			$aParams = array();
			
			$aParams[ 'showposts' ] = -1;
			
			if ( $sMinDate = $_POST[ 'min_date' ] ) {
				$aParams[ 'min_date' ] = str_replace( '/', '-', $sMinDate ) . ' 00:00:00';
			}

			if ( $sMaxDate = $_POST[ 'max_date' ] ) {
				$aParams[ 'max_date' ] = str_replace( '/', '-', $sMaxDate ) . ' 23:59:59';	
			}
			
			$aParams = $this->modifyExportParams( $aParams );
			
			$sQueryClass = $this->_sQueryClass;
			$sHelperClass = $this->_sExportExcelHelperClass;
			
			$aRes = new $sQueryClass( $aParams );
			$oHelper = new $sHelperClass( $aParams );
			
			$oHelper->exportToExcel( $aRes );
			
			die();
		}
		
	}
	
	
	// hook method
	public function modifyExportParams( $aParams ) {
		return $aParams;
	}
	
	
	
	
	
	//// crud methods
	
	//
	public function modifyParams( $aParams ) {
		return $aParams;
	}
	
	//
	public function getInsertData( $aParams ) {
		return $this->getFormattedPostData( $aParams );
	}
	
	//
	public function getInsertMetaData( $aParams ) {
		return $this->getFormattedMetaData( $aParams );
	}
	
	//
	public function insert( $aParams = array() ) {
		
		if ( $sTb = $this->_sTableName ) {

			global $wpdb, $user_ID;
			get_currentuserinfo();
			
			$aParams[ 'remote_ip' ] = ip2long( $_SERVER[ 'REMOTE_ADDR' ] );
			$aParams[ 'user_agent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
			$aParams[ 'date_created' ] = Geko_Db_Mysql::getTimestamp();
			$aParams[ 'url' ] = strval( Geko_Uri::getGlobal() );
			if ( $user_ID ) $aParams[ 'user_id' ] = $user_ID;
			
			$aParams = $this->modifyParams( $aParams );
			$aInsertData = $this->getInsertData( $aParams );
			
			// start transaction
			
			$wpdb->query( 'START TRANSACTION' );
			
			$bRes = $wpdb->insert(
				$wpdb->$sTb,
				$aInsertData[ 0 ],
				$aInsertData[ 1 ]
			);
			
			if ( $bRes && $this->_bUseMetaTable ) {
				if ( is_array( $aParams[ 'meta' ] ) ) {
					$iInsertId = $wpdb->insert_id;
					$sMTb = $this->_sMetaTableName;
					foreach ( $aParams[ 'meta' ] as $sKey => $mValue ) {
						$aParams = array(
							'log_id' => $iInsertId,
							'mkey_id' => Geko_Wp_Options_MetaKey::getId( $sKey ),
							'meta_value' => $mValue
						);
						$aInsertMetaData = $this->getInsertMetaData( $aParams );
						$bRes = $wpdb->insert(
							$wpdb->$sMTb,
							$aInsertMetaData[ 0 ],
							$aInsertMetaData[ 1 ]
						);
						if ( !$bRes ) break;
					}
				}
			}
			
			if ( $bRes ) {
				$wpdb->query( 'COMMIT' );
				return TRUE;
			}
			
			$wpdb->query( 'ROLLBACK' );
		}
		
		return FALSE;
	}
	
	
	
	// helpers
	
	// check $_POST, $_GET, $aParams (in that order for values matching the table field name)
	public function getFormattedPostData( $aParams ) {
		
		$aValues = array();
		$aFormat = array();
		
		$aFields = $this->getPrimaryTableFields();
		
		foreach ( $aFields as $oField ) {
			
			$sFieldName = $oField->getFieldName();
			$sKey = $sFieldName;
			// $sKey = $this->_sSomePrefix . '_' . $sFieldName; ???
			
			$bFoundValue = FALSE;
			$mValue = NULL;
			
			if ( isset( $_POST[ $sKey ] ) ) {
				$mValue = $_POST[ $sKey ];
				$bFoundValue = TRUE;
			}
			
			if ( !$bFoundValue && isset( $_GET[ $sKey ] ) ) {
				$mValue = $_GET[ $sKey ];
				$bFoundValue = TRUE;
			}
			
			if ( !$bFoundValue && isset( $aParams[ $sKey ] ) ) {
				$mValue = $aParams[ $sKey ];
				$bFoundValue = TRUE;
			}
			
			if ( $bFoundValue ) {
				$aValues[ $sFieldName ] = $oField->getFormattedValue( $mValue );
				$aFormat[] = $oField->getFormat();
			}
			
		}

		return array( $aValues, $aFormat );
	}
	
	//
	public function getFormattedMetaData( $aParams ) {

		$aValues = array();
		$aFormat = array();
		
		$aFields = $this->getMetaTableFields();
		
		foreach ( $aFields as $oField ) {
			
			$sFieldName = $oField->getFieldName();
			$sKey = $sFieldName;
			// $sKey = $this->_sSomePrefix . '_' . $sFieldName; ???
			
			if ( isset( $aParams[ $sKey ] ) ) {
				$aValues[ $sFieldName ] = $oField->getFormattedValue( $aParams[ $sKey ] );
				$aFormat[] = $oField->getFormat();
			}
			
		}
		
		return array( $aValues, $aFormat );
		
	}
	
	
}


