<?php
/*
 * Class Name : GFHANNANSMS_Pro_{strtoupper( php file name) }	
 */
class GFHANNANSMS_Pro_Asanak {

	/*
	* Gateway title	
	*/
	public static function name($gateways) {
		$name = __('آسانک', 'GF_SMS' );
		$gateway = array( strtolower( str_replace( 'GFHANNANSMS_Pro_', '', get_called_class())) => $name );
		return array_unique( array_merge( $gateways , $gateway ) );
	}
	
	
	/*
	* Gateway parameters
	*/
	public static function options(){
		return array(
			'username'  => __('نام کاربری','GF_SMS'),
			'password'  => __('رمزعبور','GF_SMS'),
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
	public static function process( $options, $action, $from, $to, $messages ){
	
		if ( $action == 'credit' && !self::credit() ) {
			return false;
		}
		$username = $options['username'];
		$password = $options['password'];
		$source = $options['source'];
		if ($action == "credit") {
			$url = "https://panel.asanak.com/webservice/v1rest/getcredit?username=" . $username . "&password=" . $password;
			if ( extension_loaded( 'curl' ) ) {
				$ch = curl_init( $url );
				curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
				curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
				$credit_response = curl_exec( $ch );
				curl_close( $ch );
			} else {
				$credit_response = @file_get_contents( $url );
			}

			if (isset($credit_response)) {
				return $credit_response;
			}	
		}
		if ($action == "send") {
			$messg= rawurlencode($messages);
			if ( extension_loaded( 'curl' ) ) {
				$ch = curl_init( $url );
				curl_setopt_array($curl, array(
					CURLOPT_URL => "https://panel.asanak.com/webservice/v1rest/sendsms",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_MAXREDIRS => 10,
					CURLOPT_TIMEOUT => 10,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_POSTFIELDS => array(
						'username' => $username,
						'password' => $password,
						'Source' => $from,
						'Message' => $messg,
						'destination' => $to
					),
					)
				);
				$sms_response = curl_exec( $ch );
				curl_close( $ch );
			} else {
				return false;
			}
		}
	}
}
?>