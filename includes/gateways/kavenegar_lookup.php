<?php
/*
 * Class Name : GFHANNANSMS_Pro_{strtoupper( php file name) }	
 */
class GFHANNANSMS_Pro_Kavenegar_lookup {

	/*
	* Gateway title	
	*/
	public static function name($gateways) {
	
		$name = __('(lookup)کاوه نگار', 'GF_SMS' );
		
		$gateway = array( strtolower( str_replace( 'GFHANNANSMS_Pro_', '', get_called_class())) => $name );
		return array_unique( array_merge( $gateways , $gateway ) );
	}
	
	
	/*
	* Gateway parameters
	*/
	public static function options(){
		return array(
			'username'  => __('شناسه API_KEY','GF_SMS'),
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
		
		
if ($action == "credit") {
$url = "https://api.kavenegar.com/v1/$username/account/info.json";
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

		
		$arr = json_decode($credit_response, true);

		if ( false !== $credit_response ) {
			$json_response = json_decode( $credit_response );
			if ( ! empty( $json_response->return->status ) && $json_response->return->status == 200 ) {
				return $arr['entries']['remaincredit'];
			}
		}

		if ( $json_response !== true ) {
			$json_response = $credit_response;
		}

}
		if ($action == "send") {

		
		
		$regex_template = '/(?<=template=)(.*?)(?=token\d*=|$)/is';

		$regex_tokens = '/(token=|token\d=|token\d\d=)/is';

		$regex_variables = '/(?<=token=|token\d=|token\d\d=)(.*?)(?=token\d*=|$|template)/is';

		preg_match_all($regex_template, $messages, $template_matches, PREG_PATTERN_ORDER, 0);
		preg_match_all($regex_tokens, $messages, $tokens_matches, PREG_PATTERN_ORDER, 0);
		preg_match_all($regex_variables, $messages, $variables_matches, PREG_PATTERN_ORDER, 0);
		
		
		$templateName=$template_matches[0][0];
		$tokensParam="";
		
		
		for ($i = 0; $i <= count($tokens_matches[0])-1 ; $i++) 
		{
			$tokenName=$tokens_matches[0][$i];
			$lookupval= html_entity_decode($variables_matches[0][$i]);
			
			if((strcasecmp($tokenName, 'token10=') != 0) && (strcasecmp($tokenName, 'token20=') != 0))
			{
				$lookupval=str_replace(' ', '-', $lookupval);
			}

			$tokensParam.= "&".$tokenName.rawurlencode(html_entity_decode($lookupval,ENT_QUOTES,'UTF-8'));
					
		} 
		
				
		$templateName=trim($templateName);

	    //$numbers = implode(",", $this->mobile );
		$numbers = explode(",", $to );
		
	if(count($numbers)>15)
	{
	    return "تعداد گیرنده ها بیش از حد مجاز می باشد(حداکثر تعداد گیرنده 15 عدد می باشد)";
	}
	
	
	$result="";
	foreach ($numbers as $number) { 
		    
		    $url = "http://api.kavenegar.com/v1/$username/verify/lookup.json?receptor=$number&template=$templateName".		  $tokensParam;
		
		if ( extension_loaded( 'curl' ) ) {
			$ch = curl_init( $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
			$sms_response = curl_exec( $ch );
			curl_close( $ch );
		} else {
			$sms_response = @file_get_contents( $url );
		}


$arr = json_decode($sms_response, true);
if($arr['return']['status']=='200')
{

$result= true;
}
else	
{
echo $arr['return']['status'];
$result= false;
}
		    
		}
		return $result;

		
		
		}
		

	}
}