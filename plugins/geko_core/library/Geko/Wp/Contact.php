<?php

//
class Geko_Wp_Contact extends Geko_Wp_Entity
{
	

	//// object oriented functions
		
	//
	public function init() {
		
		parent::init();
		
		$this->setEntityMapping( 'id', 'contact_id' );
		
		return $this;
	}
	
	
	//
	public function getAltEmailAddress2() {
		return $this->getEntityPropertyValue( 'alt_email_address_2' );
	}
	
	//
	public function getAltEmailAddress3() {
		return $this->getEntityPropertyValue( 'alt_email_address_3' );
	}
	
	
}


