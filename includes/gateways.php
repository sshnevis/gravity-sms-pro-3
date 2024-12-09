<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GFHANNANSMS_Pro_WebServices' ) ) {

	class GFHANNANSMS_Pro_WebServices {

		public static function get() {
			return apply_filters( 'gf_sms_gateways', array( 'no' => __( 'Select a Gateway', 'GF_SMS' ) ) );
		}

		public static function action( $settings, $action, $from, $to, $messages ) {

			$gateway = isset( $settings["ws"] ) ? $settings["ws"] : '';

			if ( empty( $gateway ) || $gateway == 'no' ) {
				return __( 'No Gateway found.', 'GF_SMS' );
			}

			$GATEWAY = strtoupper( $gateway );
			$Gateway = 'GFHANNANSMS_Pro_' . $GATEWAY;

			$messages = str_replace( array( "<br>", "<br/>", "<br />", '&nbsp;' ), array(
				"\n",
				"\n",
				"\n",
				''
			), $messages );
			$messages = strip_tags( $messages );

			if ( ! class_exists( $Gateway ) && file_exists( GF_SMS_GATEWAY . strtolower( $gateway ) . '.php' ) ) {
				require_once( GF_SMS_GATEWAY . strtolower( $gateway ) . '.php' );
			}

			if ( class_exists( $Gateway ) && method_exists( $Gateway, 'process' ) ) {

				/*
				if ( function_exists( 'error_reporting' ) ) {
					$error = error_reporting( E_ALL ^ E_WARNING ^ E_DEPRECATED ^ E_NOTICE );
				}
				*/

				$options = get_option( "gf_hannansms_" . $GATEWAY );
				$result  = $Gateway::process( $options, $action, $from, $to, $messages );

				/*if ( ! empty( $error ) ) {
					error_reporting( $error );
				}*/

				return $result;
			}

			return __( 'No Gateway found.', 'GF_SMS' );
		}
	}
}