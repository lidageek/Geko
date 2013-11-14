<?php

//
class Geko_Wp_Cart66_View_Checkout extends Geko_Wp_Cart66_View
{

	
	//
	public function render( $data = NULL, $notices = TRUE, $minify = FALSE ) {
		
		$this->_sThisFile = __FILE__;
		
		$errors = array();
		$createAccount = FALSE;
		
		// Object instance inherited from Cart66GatewayAbstract
		$gateway = $data[ 'gateway' ];
		$gatewayName = ( is_object( $gateway ) ) ? get_class( $gateway ) : NULL ;
		
		
		if ( 'POST' == $_SERVER[ 'REQUEST_METHOD' ] ) {
		
			$cart = Cart66Session::get( 'Cart66Cart' );
			
			$account = FALSE;
			
			if ( $cart->hasMembershipProducts() || $cart->hasSpreedlySubscriptions() ) {
				
				// Set up a new Cart66Account and start by pre-populating the data or load the logged in account
				if ( $accountId = Cart66Common::isLoggedIn() ) {
					
					$account = new Cart66Account( $accountId );
				
				} else {
				
					$account = new Cart66Account();
					
					if ( isset( $_POST[ 'account' ] ) ) {
						
						$acctData = Cart66Common::postVal( 'account' );
						
						$this->logMsg( __LINE__, 'New Account Data', print_r( $acctData, TRUE ) );
						
						$account->firstName = $acctData[ 'first_name' ];
						$account->lastName = $acctData[ 'last_name' ];
						$account->email = $acctData[ 'email' ];
						$account->username = $acctData[ 'username' ];
						$account->password = md5( $acctData[ 'password' ] );
						$errors = $account->validate();
						$jqErrors = $account->getJqErrors();
						
						if ( $acctData[ 'password' ] != $acctData[ 'password2' ] ) {
							$errors[] = __( 'Passwords do not match', 'cart66' );
							$jqErrors[] = 'account-password';
							$jqErrors[] = 'account-password2';
						}
						
						if ( 0 == count( $errors ) ) { 
							
							$createAccount = TRUE;
						
						} else {
							
							if ( count( $errors ) ) {
								
								try {
									
									$this->logMsg( __LINE__, 'Unable to process order', print_r( $errors, TRUE ) );
									throw new Cart66Exception( __( 'Your order could not be processed for the following reasons:', 'cart66' ), 66500 );
									
								} catch ( Cart66Exception $e ) {
									$exception = Cart66Exception::exceptionMessages( $e->getCode(), $e->getMessage(), $errors );
									echo Cart66Common::getView( 'views/error-messages.php', $exception );
								}
							}
						}
						
						// An account should be created and the account data is valid
					}
				}
			}
			
			if ( $gateway instanceof Cart66GatewayAbstract ) {
				
				// get calculation object, if available
				$oCalculation = NULL;
				if ( method_exists( $gateway, 'getCalculation' ) ) {
					$oCalculation = $gateway->getCalculation();
				}
				
				$gateway->validateCartForCheckout();
				
				$gateway->setBilling( Cart66Common::postVal( 'billing' ) );
				$gateway->setPayment( Cart66Common::postVal( 'payment' ) );
				
				// Note that mijireh does not have a "same as billing" checkbox
				
				if ( isset( $_POST[ 'sameAsBilling' ] ) ) {
					$gateway->setShipping( Cart66Common::postVal( 'billing' ), TRUE );
				} elseif ( isset( $_POST[ 'shipping' ] ) ) {
					$gateway->setShipping( Cart66Common::postVal( 'shipping' ) );
				}
				
				$s = $gateway->getShipping();
				
				if ( $s[ 'state' ] && $s[ 'zip' ] ) {
					
					$taxLocation = $gateway->getTaxLocation();
					$rate = $gateway->getTaxRate();
					
					if ( $oCalculation ) {
						$tax = $oCalculation->getTax();
					} else {
						$tax = $gateway->getTaxAmount();
					}
					
					Cart66Session::set( 'Cart66Tax', $tax );
					Cart66Session::set( 'Cart66TaxRate', Cart66Common::tax( $rate ) );
					
					$this->logMsg( __LINE__, 'Tax PreCalculated', $tax );
				}
				
				if ( 0 == count( $errors ) ) {
					
					$errors = $gateway->getErrors();     // Error info for server side error code
					
					if ( count( $errors ) ) {
						
						try {
							
							$this->logMsg( __LINE__, 'Unable to process order', print_r( $errors, TRUE ) );
							throw new Cart66Exception(__('Your order could not be processed for the following reasons:', 'cart66'), 66500);
							
						} catch( Cart66Exception $e ) {
							$exception = Cart66Exception::exceptionMessages( $e->getCode(), $e->getMessage(), $errors );
							echo Cart66Common::getView( 'views/error-messages.php', $exception );
						}
					}
					
					$jqErrors = $gateway->getJqErrors(); // Error info for client side error code
				}
				
				if ( count( $errors ) == 0 ) {
					
					// Calculate final billing amounts
					
					$subscriptionAmt = Cart66Session::get( 'Cart66Cart' )->getSubscriptionAmount();
					
					if ( $oCalculation ) {
						
						// using calculation object
						$fDiscount = $oCalculation->getDiscount();
						$total = $oCalculation->getTotal();
						
					} else {
						
						// standard calculation
						$total = Cart66Session::get( 'Cart66Cart' )->getGrandTotal() + $tax;
					}
					
					$oneTimeTotal = $total - $subscriptionAmt;
					
					$this->logMsg( __LINE__, 'Billing', sprintf(
						'Tax: %s | Total: %s | Subscription Amount: %s | One Time Total: %s',
						$tax, $total, $subscriptionAmt, $oneTimeTotal
					) );
					
					// Throttle checkout attempts
					if ( !Cart66Session::get( 'Cart66CheckoutThrottle' ) ) {
						Cart66Session::set( 'Cart66CheckoutThrottle', Cart66CheckoutThrottle::getInstance(), TRUE );
					}
					
					try {
						
						if ( !Cart66Session::get( 'Cart66CheckoutThrottle' )->isReady( $gateway->getCardNumberTail(), $oneTimeTotal ) ) {
							$this->logMsg( __LINE__, 'Unable to process order', print_r( $errors, TRUE ) );
							throw new Cart66Exception( __( 'Your order could not be processed for the following reasons:', 'cart66' ), 66500 );
						}
						
					} catch( Cart66Exception $e ) {
						
						$exception = Cart66Exception::exceptionMessages(
							$e->getCode(), $e->getMessage(), array( __( sprintf(
								'You must wait %d more seconds before trying to checkout again',
								Cart66Session::get( 'Cart66CheckoutThrottle' )->getTimeRemaining()
							), 'cart66' ) )
						);
						
						echo Cart66Common::getView( 'views/error-messages.php', $exception );
						$errors[] = ''; // Add an error so that the transaction will not be processed
					}
				}
				
				// Charge credit card for one time transaction using Authorize.net API
				if ( count( $errors ) == 0 && !Cart66Session::get( 'Cart66InventoryWarning' ) ) {
					
					$this->logMsg( __LINE__, 'start working on charging the credit card' );
					
					// =============================
					// = Start Spreedly Processing =
					// =============================
					
					if ( Cart66Session::get( 'Cart66Cart' )->hasSpreedlySubscriptions() ) {
						
						$accountErrors = $account->validate();

						if ( count( $accountErrors ) == 0 ) {
							
							$account->save(); // Save account data locally which will create an account id and/or update local values
							
							$this->logMsg( __LINE__, 'Account data validated and saved for account id', $account->id );
							
							try {
							
								$spreedlyCard = new SpreedlyCreditCard();
								$spreedlyCard->hydrateFromCheckout();
								$subscriptionId = Cart66Session::get('Cart66Cart')->getSpreedlySubscriptionId();
								$productId = Cart66Session::get('Cart66Cart')->getSpreedlyProductId();
								Cart66Common::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] About to create a new spreedly account subscription: Account ID: $account->id | Subscription ID: $subscriptionId");
								$accountSubscription = new Cart66AccountSubscription();
								$accountSubscription->createSpreedlySubscription($account->id, $subscriptionId, $productId, $spreedlyCard);

							} catch ( SpreedlyException $e ) {
							
								Cart66Common::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Failed to checkout: " . $e->getCode() . ' ' . $e->getMessage());
								$errors['spreedly failed'] = $e->getMessage();
								$accountSubscription->refresh();
								
								if(empty($accountSubscription->subscriberToken)) {
									Cart66Common::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] About to delete local account after spreedly failure: " . print_r($account->getData(), true));
									$account->deleteMe();
								} else {
									// Set the subscriber token in the session for repeat attempts to create the subscription
									Cart66Session::set('Cart66SubscriberToken', $account->subscriberToken);
								}
            
								if(count($errors)) {
									try {
										Cart66Common::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Unable to process order: " . print_r($errors, true));
										throw new Cart66Exception(__('Your order could not be processed for the following reasons:', 'cart66'), 66500);
									} catch(Cart66Exception $e) {
										$exception = Cart66Exception::exceptionMessages($e->getCode(), $e->getMessage(), $errors);
										echo Cart66Common::getView('views/error-messages.php', $exception);
									}
								}
							}
							
						} else {
							
							$errors = $account->getErrors();
							
							if ( count( $errors ) ) {
								try {
									Cart66Common::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Unable to process order: " . print_r($errors, true));
									throw new Cart66Exception(__('Your order could not be processed for the following reasons:', 'cart66'), 66500);
								} catch(Cart66Exception $e) {
									$exception = Cart66Exception::exceptionMessages($e->getCode(), $e->getMessage(), $errors);
									echo Cart66Common::getView('views/error-messages.php', $exception);
								}
							}
							
							$jqErrors = $account->getJqErrors();
							
							$this->logMsg( __LINE__, 'Account validation failed', print_r( $errors, TRUE ) );
						}
					}
      				
					// ===========================
					// = End Spreedly Processing =
					// ===========================
					
					if ( 0 == count( $errors ) ) {
        				
						// Look for constant contact opt-in
						if ( CART66_PRO ) {
							include( CART66_PATH . '/pro/Cart66ConstantContactOptIn.php' );
						}
						
						// Look for mailchimp opt-in
						if ( CART66_PRO ) {
							include( CART66_PATH . '/pro/Cart66MailChimpOptIn.php');
						}
						
						$gateway->initCheckout( $oneTimeTotal );
						
						if ( ( $oneTimeTotal > 0 ) || ( 'Cart66ManualGateway' == $gatewayName ) ) {
							$transactionId = $gateway->doSale();
						} else {
							// Do not attempt to charge $0.00 transactions to live gateways
							$transactionId = $transId = 'MT-' . Cart66Common::getRandString();
						}
        
						if ( $transactionId ) {
							
							// Set order status based on Cart66 settings
							$statusOptions = Cart66Common::getOrderStatusOptions();
							$status = $statusOptions[ 0 ];
							
							// Check for account creation
							$accountId = 0;
							
							$this->logMsg( __LINE__, 'Should an account be created? ', print_r( $createAccount, TRUE ) );
							
							if ( $createAccount ) { 
								$account->save(); 
								$accountId = $account->id;
								$this->logMsg( __LINE__, 'Just created account with id', $accountId );
							}
          					
							if ( $mp = Cart66Session::get( 'Cart66Cart' )->getMembershipProduct() ) { 
								$account->attachMembershipProduct( $mp, $account->firstName, $account->lastName );
								$accountId = $account->id;
								$this->logMsg( __LINE__, 'Attached membership to account id', $accountId );
							}
							
							// Save the order locally
							if ( $fDiscount && method_exists( $gateway, 'saveDiscountedOrder' ) ) {
								$orderId = $gateway->saveDiscountedOrder( $fDiscount, $total, $tax, $transactionId, $status, $accountId );							
							} else {
								$orderId = $gateway->saveOrder( $total, $tax, $transactionId, $status, $accountId );
							}
							
							Cart66Session::drop( 'Cart66SubscriberToken' );
							Cart66Session::set( 'order_id', $orderId );
							Cart66Session::drop( 'Cart66ProRateAmount' );
							
							$receiptLink = Cart66Common::getPageLink( 'store/receipt' );
							$newOrder = new Cart66Order( $orderId );
          					
							// Send email receipts
							if ( CART66_PRO && CART66_EMAILS && ( 1 == Cart66Setting::getValue( 'enable_advanced_notifications' ) ) ) {
								
								$notify = new Cart66AdvancedNotifications( $orderId );
								$notify->sendAdvancedEmailReceipts();
								
							} elseif ( CART66_EMAILS ) {
								
								$notify = new Cart66Notifications( $orderId );
								$notify->sendEmailReceipts();
							}
          					
							// Send buyer to receipt page
							$receiptVars = strpos( $receiptLink, '?' ) ? '&' : '?';
							$receiptVars .= 'ouid=' . $newOrder->ouid;
							wp_redirect( $receiptLink . $receiptVars );
							exit;
        					
						} else {
							// Attempt to discover reason for transaction failure
          
							try {
								throw new Cart66Exception(__('Your order could not be completed for the following reasons:', 'cart66'), 66500);
							} catch(Cart66Exception $e) {
								$gatewayResponse = $gateway->getTransactionResponseDescription();
								$exception = Cart66Exception::exceptionMessages($e->getCode(), $e->getMessage(), array('error_code' => 'Error: ' . $gatewayResponse['errorcode'], strtolower($gatewayResponse['errormessage'])));
								echo Cart66Common::getView('views/error-messages.php', $exception);
							}
							
							//$errors['Could Not Process Transaction'] = $gateway->getTransactionResponseDescription();
						}
					}
				
				}
			} // End if supported gateway
		} // End if POST


		// Show inventory warning if there is one
		if ( Cart66Session::get( 'Cart66InventoryWarning' ) ) {
			echo Cart66Session::get( 'Cart66InventoryWarning' );
			Cart66Session::drop( 'Cart66InventoryWarning' );
		}
		
		
		// Build checkout form action URL
		$checkoutPage = get_page_by_path('store/checkout');
		$ssl = Cart66Setting::getValue('auth_force_ssl');
		$url = get_permalink($checkoutPage->ID);
		
		if(Cart66Common::isHttps()) {
			$url = str_replace('http:', 'https:', $url);
		}

		// Make it easier to get to payment, billing, and shipping data
		$p = $gateway->getPayment();
		$b = $gateway->getBilling();
		$s = $gateway->getShipping();
		
		// Set initial country codes for billing and shipping addresses
		$billingCountryCode =  (isset($b['country']) && !empty($b['country'])) ? $b['country'] : Cart66Common::getHomeCountryCode();
		$shippingCountryCode = (isset($s['country']) && !empty($s['country'])) ? $s['country'] : Cart66Common::getHomeCountryCode();

		// Include the HTML markup for the checkout form
		$checkoutFormFile = CART66_PATH . '/views/checkout-form.php';
		
		if ( 'Cart66Mijireh' == $gatewayName ) {
			$checkoutFormFile =  CART66_PATH . '/views/mijireh/shipping_address.php';
		} elseif($gatewayName == 'Cart662Checkout') {
			$checkoutFormFile =  CART66_PATH . '/views/2checkout.php';
		} else {
			$userViewFile = get_stylesheet_directory() . '/cart66-templates/views/checkout-form.php';
			if(file_exists($userViewFile) && filesize($userViewFile) > 10 && CART66_PRO && Cart66Common::isRegistered()) {
				$checkoutFormFile = $userViewFile;
			}
		}
		
		Cart66Common::log('[' . basename(__FILE__) . ' - line ' . __LINE__ . "] Using Checkout Form File :: $checkoutFormFile");

		ob_start();
		include($checkoutFormFile);
		$checkoutFormFileContents = ob_get_contents();
		ob_end_clean();
		echo Cart66Common::minifyMarkup($checkoutFormFileContents);
		
		// Include the client side javascript validation
		$same_as_billing = FALSE;
		
		if($_SERVER['REQUEST_METHOD'] == 'GET' && Cart66Setting::getValue('sameAsBillingOff') != 1) {
			$same_as_billing = TRUE;
		} elseif(isset($_POST['sameAsBilling']) && $_POST['sameAsBilling'] == '1') {
			$same_as_billing = TRUE;
		}

		$shipping_address_display = (!$same_as_billing || $gatewayName == 'Cart66Mijireh' || $gatewayName == 'Cart662Checkout') ? 'block' : 'none';

		$billing_country = '';
		
		if(isset($b['country']) && !empty($b['country'])) {
			$billing_country = $b['country'];
			$shipping_country = isset($s['country']) ? $s['country'] : $b['country'];
		}

		$error_field_names = array();
		
		if(isset($jqErrors) && is_array($jqErrors)) {
			foreach($jqErrors as $field_name) {
				$error_field_names[] = '#' . $field_name;
			}
		}

		$checkout_data = array(
			'zones' => Cart66Common::getZones(),
			'same_as_billing' => $same_as_billing,
			'shipping_address_display' => $shipping_address_display,
			'billing_country' => $billing_country,
			'shipping_country' => $shipping_country,
			'billing_state' => isset($b['state']) ? $b['state'] : '',
			'shipping_state' => $s['state'],
			'card_type' => isset($p['cardType']) ? $p['cardType'] : '',
			'form_name' => '#' . $gatewayName . '_form',
			'error_field_names' => $error_field_names,
			'text_state' => __('State', 'cart66'),
			'text_zip_code' => __('Zip code', 'cart66'),
			'text_post_code' => __('Post code', 'cart66'),
			'text_province' => __('Province', 'cart66')
		);

		$path = CART66_URL . '/js/checkout.js';
		wp_enqueue_script('checkout_js', $path, array('jquery'), false, true);
		wp_localize_script('checkout_js', 'C66', $checkout_data);
		
	}
	

}