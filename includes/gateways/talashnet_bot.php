<?php
/**
 * Class Name : GFHANNANSMS_Pro_{strtoupper( php file name) }	
 */
class GFHANNANSMS_Pro_Talashnet_Bot {

	/*
	* Gateway title	
	*/
	public static function name($gateways) {
	
		$name = __('TalashnetBot', 'GF_SMS' );
		
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
		
		
		if ($action == "send") {
		    
		    $message = urlencode($message);
			//$url = "https://talashnet-bot.sina-shiri.workers.dev/?to=$reciever&text=$message";
			//$url = "https://telegram.nikshow.ir/talashnet_bot/webservice.php?to=$reciever&text=$message";
			
			$url = "https://exiryab.com/talashnet_bot.php?to=$reciever&text=$message";
		
			if ( extension_loaded( 'curl' ) ) {
				$ch = curl_init( $url );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
                curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );
				$sms_response = curl_exec( $ch );
				curl_close( $ch );
			} else {
				$sms_response = @file_get_contents( $url );
			}

			return 'OK';
		}
		
		if ($action == "credit") {
		
			
			return 70;

			return 100;
		}

		if ($action == "range"){
			$min = 6;
			$max = 20;
			return array("min" => $min, "max" => $max);
		}

	}
	
}
