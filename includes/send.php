<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Form_Send {

	public static function construct() {

		add_filter( 'gform_confirmation', array( __CLASS__, 'after_submit' ), 9999999, 4 );
		add_action( 'gform_post_payment_status', array( __CLASS__, 'after_payment' ), 999999, 4 );
		add_action( 'gform_paypal_fulfillment', array( __CLASS__, 'paypal_fulfillment' ), 999999, 4 );
		add_filter( 'gform_replace_merge_tags', array( __CLASS__, 'tags' ), 99999, 7 );

		/*
		if ( version_compare(GFCommon::$version, '1.9.10.21', '>=')) {
			add_action('gform_post_payment_action', array(__CLASS__, 'payment_action'), 10, 2);
		}
		*/
	}

	public static function Send( $to, $msg, $from = '', $form_id = '', $entry_id = '', $verify_code = '' ) {
		$settings = GFHANNANSMS_Pro::get_option();
		//$default_froms = array();
		$default_froms = explode( ',', $settings["from"] );
		$default_from  = $default_froms[0];
		$to            = self::change_mobile( $to, '' );
		$from          = ( ! empty( $from ) && $from != '' ) ? $from : $default_from;
		$result        = GFHANNANSMS_Pro_WebServices::action( $settings, "send", $from, $to, $msg );
		if ( $result == 'OK' ) {
			GFHANNANSMS_Pro_SQL::save_sms_sent( $form_id, $entry_id, $from, $to, $msg, $verify_code );
		}

		return $result;
	}


	public static function change_mobile( $mobile = '', $code = '' ) {

		if ( empty( $mobile ) ) {
			return '';
		}

		if ( empty( $code ) ) {
			$settings = GFHANNANSMS_Pro::get_option();
			if ( ! empty( $settings["code"] ) ) {
				$code = $settings["code"];
			}
		}

		if ( strpos( $mobile, ',' ) === false ) {
			return self::change_mobile_separately( $mobile, $code );
		} else {
			$mobiles = explode( ',', $mobile );
			$changed = array();
			foreach ( (array) $mobiles as $mobile ) {
				$changed[] = self::change_mobile_separately( $mobile, $code );
			}

			return str_replace( ',,', ',', implode( ',', $changed ) );
		}

	}

	public static function change_mobile_separately( $mobile = '', $code = '' ) {

		if ( empty( $mobile ) ) {
			return '';
		}

		if ( empty( $code ) ) {
			$settings = GFHANNANSMS_Pro::get_option();
			if ( ! empty( $settings["code"] ) ) {
				$code = $settings["code"];
			}
		}

		$phone = '';
		preg_match_all( '/\d+/', $mobile, $matches );
		if ( ! empty( $matches[0] ) ) {
			foreach ( (array) $matches[0] as $number ) {
				$phone .= $number;
			}
		}

		if ( strpos( $mobile, '+' ) !== false || stripos( $mobile, '%2B' ) !== false ) {
			return '+' . $phone;
		} else if ( substr( $phone, 0, 2 ) == '00' ) {
			return '+' . substr( $phone, 2 );
		} else if ( substr( $phone, 0, 1 ) == '0' ) {
			$phone = substr( $phone, 1 );
		}

		$code = substr( $code, 0, 1 ) == '+' ? $code : '+' . $code;

		return $code . $phone;
	}


	public static function save_number_to_meta( $entry, $form ) {

		if ( ! is_numeric( $form["id"] ) ) {
			return;
		}

		$feeds = GFHANNANSMS_Pro_SQL::get_feed_via_formid( $form["id"], true );

		$numbers = array();
		foreach ( (array) $feeds as $feed ) {

			if ( ! is_numeric( $feed["id"] ) ) {
				break;
			}

			$code = $static_code = '';
			if ( ! empty( $feed["meta"]["gf_sms_change_code"] ) ) {

				if ( empty( $feed["meta"]["gf_change_code_type"] ) || ( ! empty( $feed["meta"]["gf_change_code_type"] ) && $feed["meta"]["gf_change_code_type"] != 'dyn' ) ) {
					$static_code = $code = ! empty( $feed["meta"]["gf_code_static"] ) ? $feed["meta"]["gf_code_static"] : '';
				} else {
					$code = sanitize_text_field( rgpost( 'input_' . str_replace( ".", "_", $feed["meta"]["gf_code_dyn"] ) ) );
				}
			}

			$client1 = $client2 = $sep = '';

			if ( isset( $feed["meta"]["customer_field_clientnum"] ) && rgpost( 'input_' . str_replace( ".", "_", $feed["meta"]["customer_field_clientnum"] ) ) ) {
				$client1 = self::change_mobile( sanitize_text_field( rgpost( 'input_' . str_replace( ".", "_", $feed["meta"]["customer_field_clientnum"] ) ) ), $code );
			}

			if ( ! empty( $feed["meta"]["to_c"] ) ) {
				$client2 = self::change_mobile( $feed["meta"]["to_c"], $static_code );
			}

			if ( ! empty( $client1 ) && ! empty( $client2 ) ) {
				$sep = ",";
			}

			$client = $client1 . $sep . $client2;
			if ( $client != '' ) {
				$numbers[] = $client;
				gform_update_meta( $entry["id"], "client_mobile_number_" . $feed["id"], $client );
			}

		}

		$numbers = array_unique( $numbers );
		$numbers = str_replace( ',,', ',', implode( ',', $numbers ) );
		if ( ! empty( $numbers ) ) {
			gform_update_meta( $entry["id"], "client_mobile_numbers", $numbers );
		}
	}


	public static function has_credit_card( $form ) {
		foreach ( $form["fields"] as $field ) {
			if ( $field["type"] == "creditcard" ) {
				return true;
			}
		}

		return false;
	}

	public static function after_submit( $confirmation, $form, $entry, $ajax ) {
		self::save_number_to_meta( $entry, $form );
		self::send_sms_form( $entry, $form, '-', 'immediately' );

		return $confirmation;
	}

	public static function after_payment( $config, $entry, $status, $transaction_id ) {
		$form = RGFormsModel::get_form_meta( $entry['form_id'] );
		//	self::save_number_to_meta($entry, $form);
		self::send_sms_form( $entry, $form, strtolower( $status ), 'after_payment' );
	}

	public static function paypal_fulfillment( $entry, $config, $transaction_id, $amount ) {
		$form = RGFormsModel::get_form_meta( $entry['form_id'] );
		//	self::save_number_to_meta($entry, $form);
		self::send_sms_form( $entry, $form, 'completed', 'fulfillment' );
	}

	/*
	public static function payment_action( $entry, $action ) {
		$form = GFAPI::get_form( $entry['form_id'] );
		GFAPI::send_notifications( $form, $entry, rgar( $action, 'type' ) );
	}
	*/

	public static function send_sms_form( $entry, $form, $status, $function_time ) {

		if ( ! is_numeric( $form["id"] ) ) {
			return;
		}

		$settings = GFHANNANSMS_Pro::get_option();

		if ( empty( $settings["ws"] ) || $settings["ws"] == 'no' ) {
			RGFormsModel::add_note( $entry["id"], 0, __( 'SMS Pro', 'GF_SMS' ), __( 'No Gateway found.', 'GF_SMS' ) );

			return;
		}

		$feeds  = GFHANNANSMS_Pro_SQL::get_feed_via_formid( $form["id"], true );
		$status = strtolower( $status );

		foreach ( (array) $feeds as $feed ) {

			if ( ! is_numeric( $feed["id"] ) ) {
				break;
			}

			$sent = gform_get_meta( $entry["id"], "gf_hannansms_sent_" . $feed["id"] );
			if ( $sent == 'yes' ) {
				continue;
			}

			$feed_when = isset( $feed["meta"]["when"] ) ? $feed["meta"]["when"] : '';
			$sending   = 'yes';

			if ( $feed_when == 'after_pay_success' ) {
				if ( ( $function_time == 'immediately' ) || ! ( $status == 'completed' || $status == 'complete' || $status == 'paid' || $status == 'active' || $status == 'actived' || $status == 'approved' || $status == 'approve' ) ) {
					$sending = 'no';
				}
			}

			if ( $feed_when == 'after_pay' ) {
				if ( $function_time == 'immediately' ) {
					$sending = 'no';
				}
			}

			if ( $function_time == 'fulfillment' ) {
				$sending = 'yes';
			}

			if ( self::has_credit_card( $form ) ) {
				$sending = 'yes';
				if ( $feed_when == 'after_pay_success' ) {
					$payment_status = ! empty( $entry["payment_status"] ) ? strtolower( $entry["payment_status"] ) : '';
					if ( ! empty( $payment_status ) && ! ( $payment_status == 'completed' || $payment_status == 'complete' || $payment_status == 'paid' || $payment_status == 'active' || $payment_status == 'actived' || $payment_status == 'approved' || $payment_status == 'approve' ) ) {
						$sending = 'no';
					}
				}
			}

			$gateway = gform_get_meta( $entry['id'], 'payment_gateway' ) ? 'yes' : 'no';
			if ( $gateway == 'no' && empty( $entry['payment_method'] ) ) {
				$sending = 'yes';
			}

			if ( ! ( isset( $feed["meta"]["gf_sms_is_gateway_checked"] ) && $feed["meta"]["gf_sms_is_gateway_checked"] ) ) {
				$sending = 'yes';
			}

			$sending = apply_filters( 'gf_sms_sending_control', $sending );

			if ( $sending == 'no' ) {
				continue;
			}

			gform_update_meta( $entry["id"], "gf_hannansms_sent_" . $feed["id"], "yes" );
			$from    = isset( $feed["meta"]["from"] ) ? $feed["meta"]["from"] : '';
			$from_db = get_option( "gf_sms_last_sender" );
			if ( $from and ( $from_db != $from ) ) {
				update_option( "gf_sms_last_sender", $from );
			}


			if ( self::check_condition( $entry, $form, $feed, 'admin' ) ) {

				$admin_msg    = GFCommon::replace_variables( $feed["meta"]["message"], $form, $entry, false, true, false, 'text' );
				$admin_number = isset( $feed["meta"]["to"] ) ? $feed["meta"]["to"] : '';
				$admin_number = self::change_mobile( $admin_number, '' );
				if ( $admin_number and $admin_number != '' ) {

					$result = GFHANNANSMS_Pro_WebServices::action( $settings, 'send', $from, $admin_number, $admin_msg );

					if ( $result == 'OK' ) {
						$admin_sender = $from;
						$admin_fault  = '';
						$admin_note   = __( 'Feed %s => SMS sent to Admin successfully. Admin Number : %s | Sender Number : %s %s | Message Body : %s', 'GF_SMS' );
						GFHANNANSMS_Pro_SQL::save_sms_sent( $form['id'], $entry['id'], $from, $admin_number, $admin_msg, '' );
					} else {
						$admin_sender = $from;
						$admin_fault  = $result;
						$admin_note   = __( 'Feed %s => The sending of the message to Admin encountered an error. Admin Number : %s | Sender Number : %s | Reason : %s | Message Body : %s', 'GF_SMS' );
					}
				} else {
					$admin_sender = '';
					$admin_fault  = '';
					$admin_number = '';
					$admin_msg    = '';
					$admin_note   = __( 'Feed %s => Admin SMS is disabled or Admin number is empty.%s%s%s%s', 'GF_SMS' );
				}

				$admin_msg = str_ireplace( array( '<br>', '<br/>', '<br />' ), '', $admin_msg );
				RGFormsModel::add_note( $entry["id"], 0, __( 'SMS Pro', 'GF_SMS' ), sprintf( ( $admin_note ), $feed["id"], $admin_number, $admin_sender, $admin_fault, $admin_msg ) );
			}

			if ( self::check_condition( $entry, $form, $feed, 'client' ) ) {

				$client_msg    = GFCommon::replace_variables( $feed["meta"]["message_c"], $form, $entry, false, true, false, 'text' );
				$client_number = "client_mobile_number_" . $feed["id"];

				if ( gform_get_meta( $entry["id"], $client_number ) ) {

					$client_number = gform_get_meta( $entry["id"], $client_number );
					$result        = GFHANNANSMS_Pro_WebServices::action( $settings, 'send', $from, $client_number, $client_msg );
					$result        = ( isset( $result ) || $result ) ? $result : '';

					if ( $result == 'OK' ) {
						$client_sender = $from;
						$client_fault  = '';
						$client_note   = __( 'Feed %s => SMS sent to user successfully. User Number : %s | Sender Number : %s %s | Message Body : %s', 'GF_SMS' );
						GFHANNANSMS_Pro_SQL::save_sms_sent( $form['id'], $entry['id'], $from, $client_number, $client_msg, '' );
					} else {
						$client_sender = $from;
						$client_fault  = $result;
						$client_note   = __( 'Feed %s => The sending of the message to user encountered an error. User Number : %s | Sender Number : %s | Reason : %s | Message Body : %s', 'GF_SMS' );
					}
				} else {
					$client_sender = '';
					$client_fault  = '';
					$client_number = '';
					$client_msg    = '';
					$client_note   = __( 'Feed %s => User SMS is disabled or user number is empty.%s%s%s%s', 'GF_SMS' );
				}

				$client_msg = str_ireplace( array( '<br>', '<br/>', '<br />' ), '', $client_msg );
				RGFormsModel::add_note( $entry["id"], 0, __( 'SMS Pro', 'GF_SMS' ), sprintf( ( $client_note ), $feed["id"], $client_number, $client_sender, $client_fault, $client_msg ) );
			}
		}
	}

	public static function tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {

		$payment_gateway = '{payment_gateway}';
		$payment_status  = '{payment_status}';
		$transaction_id  = '{transaction_id}';

		if ( strpos( $text, $payment_gateway ) === false && strpos( $text, $payment_status ) === false && strpos( $text, $transaction_id ) === false ) {
			return $text;
		}

		$entry_id = rgar( $entry, 'id' );
		$entry    = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			$entry = false;
		}

		if ( strpos( $text, $payment_gateway ) === false ) {
			$payment_gateway = '';
		} else {
			$payment_gateway = gform_get_meta( $entry['id'], 'payment_gateway' ) ? gform_get_meta( $entry['id'], 'payment_gateway' ) : ( isset( $entry['payment_method'] ) ? $entry['payment_method'] : '' );
		}


		if ( strpos( $text, $transaction_id ) === false ) {
			$transaction_id = '';
		} else {
			$transaction_id = isset( $entry['transaction_id'] ) ? $entry['transaction_id'] : '';
		}

		if ( strpos( $text, $payment_status ) === false ) {
			$payment_status = '';
		} else {
			$payment_status = isset( $entry['payment_status'] ) ? $entry['payment_status'] : '';
		}

		$custom_merge_tag = array(
			'{payment_gateway}',
			'{payment_status}',
			'{transaction_id}',
		);

		$values = array(
			ucfirst( $payment_gateway ),
			ucfirst( $payment_status ),
			$transaction_id,
		);

		$text = str_replace( $custom_merge_tag, $values, $text );

		return $text;
	}

	public static function check_condition( $entry, $form, $config, $who = '' ) {

		if ( empty( $config['meta'] ) ) {
			return false;
		}

		if ( empty( $config['meta'][ $who . 'sms_conditional_enabled' ] ) ) {
			return true;
		}

		if ( ! empty( $config['meta'][ $who . 'sms_conditional_field_id' ] ) ) {
			$conditions = $config['meta'][ $who . 'sms_conditional_field_id' ];
			if ( ! is_array( $conditions ) ) {
				$conditions = array( '1' => $conditions );
			}
		} else {
			return true;
		}

		if ( ! empty( $config['meta'][ $who . 'sms_conditional_value' ] ) ) {
			$condition_values = $config['meta'][ $who . 'sms_conditional_value' ];
			if ( ! is_array( $condition_values ) ) {
				$condition_values = array( '1' => $condition_values );
			}
		} else {
			$condition_values = array( '1' => '' );
		}

		if ( ! empty( $config['meta'][ $who . 'sms_conditional_operator' ] ) ) {
			$condition_operators = $config['meta'][ $who . 'sms_conditional_operator' ];
			if ( ! is_array( $condition_operators ) ) {
				$condition_operators = array( '1' => $condition_operators );
			}
		} else {
			$condition_operators = array( '1' => 'is' );
		}

		$type = ! empty( $config['meta'][ $who . 'sms_conditional_type' ] ) ? strtolower( $config['meta'][ $who . 'sms_conditional_type' ] ) : '';
		$type = $type == 'all' ? 'all' : 'any';

		foreach ( $conditions as $i => $field_id ) {

			if ( empty( $field_id ) ) {
				continue;
			}

			$field = RGFormsModel::get_field( $form, $field_id );
			if ( empty( $field ) ) {
				continue;
			}

			$value    = ! empty( $condition_values[ '' . $i . '' ] ) ? $condition_values[ '' . $i . '' ] : '';
			$operator = ! empty( $condition_operators[ '' . $i . '' ] ) ? $condition_operators[ '' . $i . '' ] : 'is';

			$is_visible     = ! RGFormsModel::is_field_hidden( $form, $field, array() );
			$field_value    = GFFormsModel::get_lead_field_value( $entry, $field );
			$is_value_match = RGFormsModel::is_value_match( $field_value, $value, $operator );

			$check = $is_value_match && $is_visible;

			if ( $type == 'any' && $check ) {
				return true;
			} else if ( $type == 'all' && ! $check ) {
				return false;
			}
		}

		if ( $type == 'any' ) {
			return false;
		} else {
			return true;
		}
	}
}