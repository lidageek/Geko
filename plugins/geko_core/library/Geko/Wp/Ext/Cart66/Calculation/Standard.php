<?php

//
class Geko_Wp_Ext_Cart66_Calculation_Standard extends Geko_Wp_Ext_Cart66_Calculation
{

	
	//
	public function calculate() {
		
		$aData = $this->_aData;
		
		$this->_fSubTotal = Cart66Session::get( 'Cart66Cart' )->getSubTotal();
		
		if ( CART66_PRO && Cart66Setting::getValue( 'use_live_rates' ) ) {
			$liveRates = Cart66Session::get( 'Cart66Cart' )->getLiveRates();
			$liveRates->getSelected();
		}
		
		$this->_fShipping = Cart66Session::get( 'Cart66Cart' )->getShippingCost();
		
		$this->_fPreTaxTotal = Cart66Session::get( 'Cart66Cart' )->getGrandTotal();
		
		$this->_fDiscountOne = Cart66Session::get( 'Cart66Cart' )->getDiscountAmount();
		
		
		
		// tax amount
		
		$taxData = 0;
		
		if ( isset( $aData[ 'tax' ] ) ){
			$taxData = $aData[ 'tax' ];
		}
		
		if ( Cart66Session::get( 'Cart66Tax' ) ){
			$taxData = Cart66Session::get( 'Cart66Tax' );
		}
		
		$this->_fTax = ( $taxData > 0 ) ? $taxData : Cart66Session::get( 'Cart66Cart' )->getTax( 'All Sales' );
		
		
		
		// IMPORTANT!!! This must be calculated after $taxData
		// tax rate
		
		$taxRate = isset( $aData[ 'rate' ] ) ? 
			Cart66Common::tax( $aData[ 'rate' ] ) : 
			Cart66Session::get( 'Cart66TaxRate' )
		;
		
		$this->_fTaxRate = $taxRate / 100 ;
		
	}
	
	
	
}


