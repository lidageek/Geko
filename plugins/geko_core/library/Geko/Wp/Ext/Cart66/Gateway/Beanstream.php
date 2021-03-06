<?php

//
class Geko_Wp_Ext_Cart66_Gateway_Beanstream extends Geko_Wp_Ext_Cart66_Gateway
{
	
	protected $_sTitle = 'Beanstream';
	protected $_sSlug = 'beanstream';
	protected $_sUrl = 'https://www.beanstream.com/scripts/process_transaction.asp';
	
	
	
	
	//
	public function preInitCheckout() {

		// initialize error arrays
		$this->_errors = array();
		$this->_jqErrors = array();
		
		
		$bTestMode = $this->getSettingValue( 'test_mode' ) ? TRUE : FALSE ;
		
		$this->clearErrors();
		
		// Set end point and api credentials
		
		$apiEndPoint = Cart66Setting::getValue( 'auth_url' );
		
		$apiMerchantId = $this->getSettingValue( 'merchant_id' );		
		
		if ( $bTestMode ) {
			$apiMerchantId = $this->getSettingValue( 'test_merchant_id' );		
		}
		
		$this->_apiEndPoint = $apiEndPoint;
		
		// Set api data
		$this->_apiData[ 'MERCHANTID' ] = $apiMerchantId;
		
		if ( !$this->_apiData[ 'MERCHANTID' ] ) {
			throw new Cart66Exception( sprintf( 'Invalid %s Configuration', $this->_sTitle ), 66520 ); 
		}
		
	}
	
	
	
	//
	public function initCheckout( $total ) {
	
		$p = $this->getPayment();
		$b = $this->getBilling();
		$ship = $this->getShipping();
		
		$sComments = '';
		
		Cart66Common::log( 'Payment info for checkout: ' . print_r( $p, true ) );
		
		// $extData = $this->generateExtendedData();
	
		$expMonth = $p[ 'cardExpirationMonth' ];
		$expYear = substr( $p[ 'cardExpirationYear' ], -2 );
		
		// $this->addField( 'Username', $this->_apiData[ 'APIUSERNAME' ] );
		// $this->addField( 'Password', $this->_apiData[ 'TRANSACTIONKEY' ] );
		
		$sBillName = sprintf( '%s %s', $b[ 'firstName' ], $b[ 'lastName' ] );
		
		$this->addField( 'requestType', 'BACKEND' );
		$this->addField( 'merchant_id', $this->_apiData[ 'MERCHANTID' ] );
		$this->addField( 'trnCardOwner', $sBillName );
		$this->addField( 'trnCardNumber', $p[ 'cardNumber' ] );
		$this->addField( 'trnExpMonth', $expMonth );
		$this->addField( 'trnExpYear', $expYear );
		// $this->addField( 'trnOrderNumber', '' );
		
		$this->addField( 'trnAmount', $total );
		$this->addField( 'Amount', $total );
		
		
		// billing
		$this->addField( 'ordEmailAddress', $p[ 'email' ] );
		$this->addField( 'ordName', $sBillName );
		$this->addField( 'ordPhoneNumber', preg_replace( '/\D/', '', $p[ 'phone' ] ) );
		$this->addField( 'ordAddress1', $b[ 'address' ] );
		$this->addField( 'ordCity', $b[ 'city' ] );
		$this->addField( 'ordProvince', $b[ 'state' ] );
		$this->addField( 'ordPostalCode', $b[ 'zip' ] );
		$this->addField( 'ordCountry', $b[ 'country' ] );
		
		
		
		// shipping
		$this->addField( 'shipName', sprintf( '%s %s', $ship[ 'firstName' ], $ship[ 'lastName' ] ) );
		$this->addField( 'shipAddress1', $ship[ 'address' ] );
		$this->addField( 'shipAddress2', $ship[ 'address2' ] );
		$this->addField( 'shipCity', $ship[ 'city' ] );
		$this->addField( 'shipProvince', $ship[ 'state' ] );
		$this->addField( 'shipPostalCode', $ship[ 'zip' ] );
		$this->addField( 'shipCountry', $ship[ 'country' ] );

		
		
		// optional fields
		
		if ( $oCart = Cart66Session::get( 'Cart66Cart' ) ) {
			
			$items = $oCart->getItems();
			
			foreach ( $items as $i => $item ) {
				
				$idx = $i + 1;
				$sProdTitle = sprintf( '%s - %s', $item->getFullDisplayName(), $item->getProductPriceDescription() );
				
				$this->addField( 'prod_id_' . $idx, $item->getItemNumber() );
				$this->addField( 'prod_name_' . $idx, $sProdTitle );
				$this->addField( 'prod_quantity_' . $idx, $item->getQuantity() );
				$this->addField( 'prod_cost_' . $idx, $item->getProductPrice() );
			}
		}
		
		
		if ( $oCalculation = $this->_oCalculation ) {
			
			$this->addField( 'ordItemPrice', $oCalculation->getSubTotal() );							// sub-total
			$this->addField( 'ordTax1Price', $oCalculation->getTax() );
			$this->addField( 'ordShippingPrice', $oCalculation->getShipping() );
			
			$sComments = sprintf( "Discount: -%s\n", number_format( $oCalculation->getDiscount(), 2 ) );					// discount
		}
		
		
		// comments
		if ( $sComments = trim( $sComments ) ) {
			$this->addField( 'trnComments', $sComments );
		}
		
		//
		$this->addField( 'ExpDate', $expMonth . $expYear );
		$this->addField( 'trnCardCvd', $p[ 'securityId' ] );
		
	}
	
	
	
	//
	public function doSale() {
    	
    	$sale = false;
    	
    	if ( $this->fields[ 'Amount' ] > 0 ) {
    		
			$oClient = new Zend_Http_Client( $this->_apiEndPoint );
			$oClient
				->setHeaders( array(
					'MIME-Version' => '1.0',
					'Content-type' => 'application/x-www-form-urlencoded',
					'Contenttransfer-encoding' => 'text'
				) )
				->setParameterPost( $this->fields )
			;
			
			$oResponse = $oClient->request( 'POST' );
			
			$this->response = array();
			
			if ( 200 == $oResponse->getStatus() ) {
				
				$this->response_string = $oResponse->getBody();
				
				$responseVars = array();
				
				parse_str( $this->response_string, $responseVars );
				
				$this->response = array_merge( $this->response, array(
					'Response Reason Text' => $responseVars[ 'messageText' ],
					'Transaction ID' => $responseVars[ 'trnId' ],
					'Response Code' => $responseVars[ 'errorType' ],
					'Approved' => $responseVars[ 'trnApproved' ]
				) );
				
			} else {
				
				$this->response[ 'Response Reason Text' ] = sprintf(
					'%d: %s', $oResponse->getStatus(), $oResponse->getMessage()
				);
			}
			
			
			// Prepare to return the transaction id for this sale.
			
			if ( 1 == $this->response[ 'Approved' ] ) {
				$sale = $this->response[ 'Transaction ID' ];
			}
			
		} else {
			
			// Process free orders without sending to the Auth.net gateway
			$this->response[ 'Transaction ID' ] = 'MT-' . Cart66Common::getRandString();
			$sale = $this->response[ 'Transaction ID' ];
			
		}
	
		return $sale;
	}
	
	//
	public function getResponseReasonText() {
		return $this->response[ 'Response Reason Text' ];
	}
	
	//
	public function getTransactionId() {
		return $this->response[ 'Transaction ID' ];
	}
	  
	
	
	//// settings form manipulation methods
	
	//
	public function settingsForm( $oDoc ) {
		
		$sOption = sprintf(
			'<option id="%s_url" value="%s">%s</option>',
			$this->_sSlug,
			$this->_sUrl,
			$this->_sTitle
		);
		
		$oAfter = $oDoc->find( 'option#authorize_test_url' );
		$oSel = $oDoc->find( 'select#auth_url' );
		$oSettingsDiv = $oDoc->find( '#gateway-other_gateways' );
		$oTable = $oSettingsDiv->find( 'table.form-table' );
		
		if ( $oAfter->length() > 0 ) {
			$oAfter->after( $sOption );
		} else {
			$oSel->append( $sOption );
		}
		
		// logo
		$oSettingsDiv->prepend( sprintf( '
			<a class="%srow" target="_blank" href="#" style="display: inline;">
				<img align="left" alt="%s" src="%s/beanstream_logo.png" />
			</a>		
		', $this->_sPrefix, $this->_sTitle, Geko_Uri::getUrl( 'geko_ext_images' ) ) );
		
		
		// fields
		$oTable->find( 'tbody' )->append(
			Geko_String::fromOb( array( $this, 'outputFields' ) )
		);
		
		// populate form values
		$oTable = Geko_Html::populateForm( $oTable, $this->getFormValues( array(
			'merchant_id', 'test_mode', 'test_merchant_id'
		) ), TRUE );
		
		return $oDoc;
	}
	
	
	//
	public function outputFields() {
		
		$sTitle = $this->_sTitle;
		$sPrefix = $this->_sPrefix;
		
		?>
		<tr class="<?php echo $sPrefix; ?>row" valign="top" style="display: table-row;">
			<th scope="row">Merchant ID</th>
			<td>
				<input id="<?php echo $sPrefix; ?>merchant_id" class="regular-text" type="text" value="" name="<?php echo $sPrefix; ?>merchant_id">
			</td>
		</tr>
		<tr class="<?php echo $sPrefix; ?>row" valign="top" style="display: table-row;">
			<th scope="row"><?php echo $sTitle; ?> Test Mode</th>
			<td>
				<input id="<?php echo $sPrefix; ?>test_mode_yes" type="radio" value="1" name="<?php echo $sPrefix; ?>test_mode">
				<label for="<?php echo $sPrefix; ?>test_mode_yes">Yes</label>
				<input id="<?php echo $sPrefix; ?>test_mode_no" type="radio" value="0" name="<?php echo $sPrefix; ?>test_mode">
				<label for="<?php echo $sPrefix; ?>test_mode_no">No</label>
			</td>
		</tr>
		<tr class="<?php echo $sPrefix; ?>row" valign="top" style="display: table-row;">
			<th scope="row">Test Merchant ID</th>
			<td>
				<input id="<?php echo $sPrefix; ?>test_merchant_id" class="regular-text" type="text" value="" name="<?php echo $sPrefix; ?>test_merchant_id">
			</td>
		</tr>
		<?php
	}
	
	
	//
	public function settingsScript( $oDoc ) {
		
		$oFirst = $oDoc->find( ':first' );
		
		$sJs = $oFirst->text();
		
		$sFind = 'function setGatewayDisplay() {';
		
		$sReplace = $sFind . sprintf( "
			
			\$jq( '.%s_row' ).hide();
			if ( \$jq( '#auth_url :selected' ).attr( 'id' ) == '%s_url' ) {
				\$jq( '.%s_row' ).show();
			}
			
		", $this->_sSlug, $this->_sSlug, $this->_sSlug );
		
		$oFirst->text( str_replace( $sFind, $sReplace, $sJs ) );
		
		return $oDoc;
	}
	
	
	
}


