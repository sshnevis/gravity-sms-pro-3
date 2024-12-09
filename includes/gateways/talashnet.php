<?php
/**
 * Class Name : GFHANNANSMS_Pro_{strtoupper( php file name) }	
 */
class GFHANNANSMS_Pro_Talashnet {

	/*
	* Gateway title	
	*/
	public static function name($gateways) {
	
		$name = __('Talashnet', 'GF_SMS' );
		
		$gateway = array( strtolower( str_replace( 'GFHANNANSMS_Pro_', '', get_called_class())) => $name );
		return array_unique( array_merge( $gateways , $gateway ) );
	}
	
	/*
	* Gateway parameters
	*/
	public static function options(){
		return array(
			'username'  		=> __('Username','GF_SMS'),
			'password' 			=> __('Password','GF_SMS'),
		);
	}

	/*
	* Gateway credit
	*/
	public static function credit(){
		return true;
	}

	/*
	* Gateway action
	*/
	public static function process( $options, $action, $from, $to, $message ){
		
		if ( $action == 'credit' && !self::credit() ) {
			return false;
		}
		
		$reciever = str_replace('+','', $to );
		
		$username = urlencode($options['username']);
		$password = urlencode($options['password']);
		$response = '';
		
		ini_set("soap.wsdl_cache_enabled", "0");
		
		if ($action == "send") {
		
			$sms_client = new SoapClient('http://api.payamak-panel.com/post/send.asmx?wsdl', array('encoding'=>'UTF-8'));
			
			$parameters['username'] = $username;
			$parameters['password'] = $password;
			$parameters['to'] = $reciever;
			$parameters['from'] = $from;
			$parameters['text'] = $message;
			$parameters['isflash'] =false;
			
			$result = $sms_client->SendSimpleSMS2($parameters);
			if ($result->SendSimpleSMS2Result > 100)
				return 'OK';
			else
				return $result->SendSimpleSMS2Result;
		
		}
		
		if ($action == "credit") {
			ini_set("soap.wsdl_cache_enabled", "0");
			$sms_client = new SoapClient('http://api.payamak-panel.com/post/Send.asmx?wsdl', array('encoding'=>'UTF-8'));
			
			$parameters['username'] = $username;
			$parameters['password'] = $password;

			
			return $sms_client->GetCredit($parameters)->GetCreditResult;
		}

		if ($action == "range"){
			$min = 6;
			$max = 20;
			return array("min" => $min, "max" => $max);
		}

	}
	
}