<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro_Entries_Sidebar {

	public static function construct() {

		if ( is_admin() && ! empty( GFCommon::$version ) ) {

			add_action( 'gform_entry_info', array( __CLASS__, 'client_number_edit' ), 10, 2 );
			add_action( 'gform_after_update_entry', array( __CLASS__, 'save_client_number_update' ), 10, 2 );

			if ( version_compare( GFCommon::$version, '2.0', '>=' ) ) {
				add_filter( 'gform_entry_detail_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 8, 3 );
			} else {
				add_action( 'gform_entry_detail_sidebar_middle', array( __CLASS__, 'send_sms_sidebar' ), 10, 2 );
			}

			add_action( 'wp_ajax_nopriv_sms_merge_tage_var_ajax', array( __CLASS__, 'sms_merge_tage_var_ajax' ) );
			add_action( 'wp_ajax_sms_merge_tage_var_ajax', array( __CLASS__, 'sms_merge_tage_var_ajax' ) );
		}
	}

	public static function get_phone_numbers( $form_id, $entry ) {

		if ( gform_get_meta( $entry['id'], 'client_mobile_numbers' ) ) {
			$clients = explode( ',', gform_get_meta( $entry['id'], 'client_mobile_numbers' ) );
		} else {

			$clients = $numbers = array();
			$feeds   = GFHANNANSMS_Pro_SQL::get_feed_via_formid( $form_id, true );

			foreach ( (array) $feeds as $feed ) {

				$client1 = $client2 = $sep = $static_code = $code = $code_field_id = '';

				if ( ! empty( $feed['meta']['gf_sms_change_code'] ) ) {

					if ( empty( $feed['meta']['gf_change_code_type'] ) || $feed['meta']['gf_change_code_type'] != 'dyn' ) {
						$static_code = $code = ! empty( $feed['meta']['gf_code_static'] ) ? $feed['meta']['gf_code_static'] : '';
					} else {
						$code_field_id = isset( $feed['meta']['gf_code_dyn'] ) ? $feed['meta']['gf_code_dyn'] : '';
					}
				}

				if ( ! empty( $feed['meta']['customer_field_clientnum'] ) ) {

					$form = GFAPI::get_form( $form_id );

					if ( empty( $code ) && ! empty( $code_field_id ) && ! empty( $feed['meta']['gf_sms_change_code'] ) ) {
						$code = self::get_field_value( $form, $entry, $code_field_id );
					}

					$mobile = self::get_field_value( $form, $entry, $feed['meta']['customer_field_clientnum'] );

					$client1 = ! empty( $mobile ) ? GFHANNANSMS_Form_Send::change_mobile( $mobile, $code ) : '';
				}

				if ( ! empty( $feed['meta']['to_c'] ) ) {
					$client2 = GFHANNANSMS_Form_Send::change_mobile( $feed['meta']['to_c'], $static_code );
				}

				if ( ! empty( $client1 ) && ! empty( $client2 ) ) {
					$sep = ',';
				}

				$client3 = $client1 . $sep . $client2;
				if ( $client3 != '' ) {
					$numbers[] = $client3;
				}


				if ( gform_get_meta( $entry['id'], 'client_mobile_number_' . $feed['id'] ) ) {
					$client_e = explode( ',', gform_get_meta( $entry['id'], 'client_mobile_number_' . $feed['id'] ) );
					foreach ( (array) $client_e as $client ) {
						$clients[] = $client;
					}
				}

			}
		}

		$clients = ! empty( $clients ) ? $clients : $numbers;
		$clients = array_unique( $clients );
		$clients = str_replace( ',,', ',', implode( ',', $clients ) );

		if ( ! empty( $clients ) ) {
			gform_update_meta( $entry['id'], 'client_mobile_numbers', sanitize_text_field( $clients ) );
		}

		return ! empty( $clients ) ? $clients : '';
	}


	public static function client_number_edit( $form_id, $entry ) {
		$client_nums = self::get_phone_numbers( $form_id, $entry );
		if ( rgpost( "save" ) && ( RGForms::post( "screen_mode" ) == "edit" || RGForms::post( "action" ) != "update" ) ) { ?>
            <label for="hannansms_client_edit"><?php _e( 'Mobile number: ', 'GF_SMS' ) ?></label>
            <input type="text" name="hannansms_client_edit" id="hannansms_client_edit"
                   style="width:100%; text-align:left !important; direction:ltr !important;  padding:3px 5px;"
                   value="<?php echo $client_nums; ?>" autocomplete="off"/>
		<?php } else if ( $client_nums ) {
			echo '<hr/>' . sprintf( __( 'Mobile number: %s', 'GF_SMS' ), '<br/><br/><div style="text-align:left !important;direction:ltr !important;word-wrap: break-word;">' . $client_nums . '</div><hr/>' );
		} else {
			echo '<hr/>' . __( 'Mobile number: -', 'GF_SMS' ) . '<hr/>';
		}

		echo '<br/><br/>';
	}


	public static function save_client_number_update( $form, $entry_id ) {
		if ( ! rgpost( "hannansms_client_edit" ) ) {
			return;
		}

		check_admin_referer( 'gforms_save_entry', 'gforms_save_entry' );


		$entry = GFAPI::get_entry( $entry_id );
		if ( is_wp_error( $entry ) ) {
			$entry = false;
		}

		$client_nums = self::get_phone_numbers( $form["id"], $entry );

		if ( $client_nums != rgpost( "hannansms_client_edit" ) ) {
			global $current_user;
			$user_id   = 0;
			$user_name = __( 'SMS Pro', 'GF_SMS' );
			if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
				$user_id   = $current_user->ID;
				$user_name = $user_data->display_name;
			}
			$mobile = rgpost( "hannansms_client_edit" ) ? sanitize_text_field( rgpost( "hannansms_client_edit" ) ) : ' ';
			RGFormsModel::add_note( $entry["id"], $user_id, $user_name, sprintf( __( 'User mobile number changed from %s to %s .', 'GF_SMS' ), $client_nums, $mobile ) );
			gform_update_meta( $entry["id"], "client_mobile_numbers", $mobile );
		}
	}


	public static function add_meta_boxes( $meta_boxes, $entry, $form ) {

		$new_meta_boxes = array();

		foreach ( $meta_boxes as $key => $val ) {

			$new_meta_boxes[ $key ] = $val;

			if ( $key == 'submitdiv' ) {
				$new_meta_boxes['send_sms'] = array(
					'title'    => esc_html__( ' Send SMS ', 'GF_SMS' ),
					'callback' => array( __CLASS__, 'send_sms_sidebar' ),
					'context'  => 'side',
				);
			}
		}

		return $new_meta_boxes;
	}


	public static function send_sms_sidebar( $arg_1, $arg_2 ) {
		if ( version_compare( GFCommon::$version, '2.0', '>=' ) ) {
			$ver = '2';
		} else {
			$ver = '1';
		}

		if ( $ver == '2' ) {
			$form  = $arg_1['form'];
			$entry = $arg_1['entry'];
		} else {
			$form  = $arg_1;
			$entry = $arg_2;
		}

		$settings = GFHANNANSMS_Pro::get_option();

		$is_OK = ( ! empty( $settings["ws"] ) && $settings["ws"] != 'no' );

		if ( rgpost( "hannansms_send" ) && rgpost( "gf_hannan_sms_sideber" ) && wp_verify_nonce( rgpost( "gf_hannan_sms_sideber" ), "send" ) ) {

			$from = sanitize_text_field( rgpost( 'hannansms_from' ) );

			$from_db = get_option( "gf_sms_last_sender" );
			if ( $from and $from_db != $from ) {
				update_option( "gf_sms_last_sender", $from );
			}

			$to  = sanitize_text_field( rgpost( 'hannansms_client' ) );
			$msg = GFCommon::replace_variables( wp_kses( rgpost( 'hannansms_text' ), array( 'br' => array() ) ), $form, $entry );
			$msg = str_replace( array( "<br>", "<br/>", "<br />" ), array( "", "", "" ), $msg );

			global $current_user;
			$user_id   = 0;
			$user_name = __( 'SMS Pro', 'GF_SMS' );
			if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
				$user_id   = $current_user->ID;
				$user_name = $user_data->display_name;
			}

			if ( ! $is_OK ) {
				RGFormsModel::add_note( $entry["id"], $user_id, $user_name, __( 'No Gateway found .', 'GF_SMS' ) );

				return;
			}

			if ( $to ) {
				$result = GFHANNANSMS_Pro_WebServices::action( $settings, 'send', $from, $to, $msg );
				if ( $result == 'OK' ) {
					GFHANNANSMS_Pro_SQL::save_sms_sent( $form['id'], $entry['id'], $from, $to, $msg, '' );
					RGFormsModel::add_note( $entry["id"], $user_id, $user_name, sprintf( __( 'SMS sent to number successfully. Number : %s | Sender Number : %s | Message Body : %s .', 'GF_SMS' ), $to, $from, $msg ) );
					echo '<div class="updated fade" style="padding:6px;">' . sprintf( __( 'SMS sent to number successfully. Number : %s . see details in Notes .', 'GF_SMS' ), $to ) . '</div>';
				} else {
					RGFormsModel::add_note( $entry["id"], $user_id, $user_name, sprintf( __( 'The sending of the message encountered an error. Number : %s | Sender Number : %s | Reason : %s | Message Body : %s.', 'GF_SMS' ), $to, $from, $result, $msg ) );
					echo '<div class="error fade" style="padding:6px;">' . sprintf( __( 'The sending of the message encountered an error. Number :%s - Reason :%s . see details in Notes .', 'GF_SMS' ), $to, $result ) . '</div>';
				}
			} else {
				echo '<div class="error fade" style="padding:6px;">' . __( "The sending of the message encountered an error because number is empty .", "GF_SMS" ) . '</div>';
			}
		}
		?>

		<?php if ( $ver == '1' ) { ?>
            <div id="send_sms_1" class="stuffbox">
            <h3 style="border-bottom:1px solid #ededed;padding:7px 14px"><?php echo __( ' Send SMS ', 'GF_SMS' ) . GFHANNANSMS_Pro::show_credit( $settings["cr"], false ); ?></h3>
            <div class="inside">
		<?php } ?>

		<?php if ( $is_OK ) { ?>

			<?php wp_nonce_field( "send", "gf_hannan_sms_sideber" ) ?>

            <div id="minor-publishing" style="padding:10px;">

                <label for="hannansms_client"><?php _e( 'Reciever numbers : ', 'GF_SMS' ); ?></label>
                <input type="text" name="hannansms_client"
                       style="width:100%; text-align:left; direction:ltr !important;  padding:3px 5px;"
                       id="hannansms_client"
                       value="<?php echo self::get_phone_numbers( $form["id"], $entry ); ?>"
                       autocomplete="off"/>
                <br/>

                <div id="sms_sidebar_loading" style="padding:5px;height:10px;text-align:center"></div>


                <label for="hannansms_text"><?php _e( 'Message : ', 'GF_SMS' ); ?></label>
                <select style="width:100%" id="hannansms_text_variable_select"
                        onchange="InsertMegeTag_SMS( 'hannansms_text' , 'variable_select', jQuery(this).val());">
					<?php $form_meta = RGFormsModel::get_form_meta( $form['id'] );
					echo GFHANNANSMS_Pro_Configurations::get_form_fields_merge( $form_meta ); ?>
                </select>

                <textarea id="hannansms_text" class="input-text"
                          style="width: 100%; height: 100px; padding:5px;" name="hannansms_text"></textarea>
            </div>

            <div id="major-publishing-actions">

                <div id="delete-action" style="width:70%">

                    <select id="hannansms_from" name="hannansms_from" style="width:100%">
                        <option value=""><?php _e( "Select Sender Number", "GF_SMS" ); ?></option>
						<?php
						$sender_num = ! empty( $settings["from"] ) ? $settings["from"] : '';
						if ( $sender_num == '' || strpos( $settings["from"], ',' ) === false ) {
							if ( $sender_num and $sender_num != '' ) {
								$last_from = get_option( "gf_sms_last_sender" );
								$selected  = ( $sender_num == $last_from ) ? "selected='selected'" : "";
								?>
                                <option
                                        value="<?php echo $sender_num ?>" <?php echo $selected ?> ><?php echo $sender_num ?></option>
								<?php
							}
						} else {
							unset( $sender_num );
							$sender_nums = array();
							$sender_nums = explode( ',', $settings["from"] );
							foreach ( (array) $sender_nums as $sender_num ) {
								$last_from = get_option( "gf_sms_last_sender" );
								$selected  = ( $sender_num == $last_from ) ? "selected='selected'" : "";
								?>
                                <option value="<?php echo $sender_num ?>" <?php echo $selected ?> ><?php echo $sender_num ?></option>
								<?php
							}
						}
						?>
                    </select>
                </div>

                <div id="publishing-action" style="width:25%">
                    <input class="button button-large button-primary" type="submit" name="hannansms_send"
                           value="<?php _e( 'Send', 'GF_SMS' ); ?>">
                </div>

                <div class="clear"></div>
            </div>

			<?php
			$ajax = empty( $settings["sidebar_ajax"] ) || esc_attr( $settings["sidebar_ajax"] ) != 'No';
			self::InsertMegeTag_SMS_JS( $ajax, $form['id'], $entry['id'] );
		} else { ?>
            <p><?php _e( 'Checkup SMS General settings .', 'GF_SMS' ); ?></p>
            <a class="button add-new-h2" href="admin.php?page=gf_settings&subview=gf_sms_pro"
               style="margin:3px 9px;"><?php _e( 'SMS General settings', 'GF_SMS' ); ?></a>
			<?php
		}
		?>

		<?php if ( $ver == '1' ) { ?>
            </div>
            </div>
		<?php }
	}


	public static function InsertMegeTag_SMS_JS( $ajax = false, $form_id = 0, $entry_id = 0 ) { ?>
        <script type="text/javascript">
            function InsertMegeTag_SMS(element_id, ex_id, variable) {

                ex_id = '_' + ex_id;

				<?php if ($ajax) : ?>
                //jQuery('#' + element_id + ex_id).after('<span id="sms_sidebar_loading"></span>');
                jQuery("#sms_sidebar_loading").html('<img src="<?php echo esc_url( GFCommon::get_base_url() ) ?>/images/spinner.gif" />');
                jQuery.ajax({
                    url: "<?php echo admin_url( 'admin-ajax.php' ) ?>",
                    type: "post",
                    data: {
                        action: "sms_merge_tage_var_ajax",
                        security: "<?php echo wp_create_nonce( "sms_sidebar_entry_ajax" ); ?>",
                        form_id: "<?php echo $form_id ?>",
                        entry_id: "<?php echo $entry_id ?>",
                        variable: variable,
                    },
                    success: function (response) {
                        jQuery("#sms_sidebar_loading").html('');
                        variable = response;
                        InsertMegeTag_SMS_Value(element_id, variable, ex_id);
                    }
                });
				<?php else : ?>
                InsertMegeTag_SMS_Value(element_id, variable, ex_id);
				<?php endif; ?>
            }

            function InsertMegeTag_SMS_Value(element_id, variable, ex_id) {

                if (typeof(tinyMCE) != "undefined") {
                    if (tinyMCE.get(element_id) != null && tinyMCE.get(element_id).isHidden() != true) {
                        tinyMCE.get(element_id).execCommand('mceInsertContent', false, variable);
                    }
                }

                var messageElement = jQuery("#" + element_id);
                if (document.selection) {
                    messageElement[0].focus();
                    document.selection.createRange().text = variable;
                }
                else if (messageElement[0].selectionStart) {
                    obj = messageElement[0]
                    obj.value = obj.value.substr(0, obj.selectionStart) + variable + obj.value.substr(obj.selectionEnd, obj.value.length);
                }
                else {
                    messageElement.val(variable + messageElement.val());
                }
                jQuery('#' + element_id + ex_id)[0].selectedIndex = 0;
                if (callback && window[callback])
                    window[callback].call();
            }
        </script>
        <style type="text/css">
            #send_sms .inside, #send_sms h3 {
                padding: 0px !important;
                margin: 0px !important;
            }
        </style>
		<?php
	}

	public static function sms_merge_tage_var_ajax() {

		check_ajax_referer( 'sms_sidebar_entry_ajax', 'security' );

		$variable = isset( $_POST['variable'] ) ? trim( $_POST['variable'] ) : '';
		$form_id  = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		if ( ob_get_length() ) {
			ob_clean();
		}

		if ( $form_id && $entry_id ) {

			$form  = RGFormsModel::get_form_meta( $form_id );
			$entry = GFAPI::get_entry( $entry_id );
			if ( is_wp_error( $entry ) ) {
				$entry = false;
			}

			echo GFCommon::replace_variables( $variable, $form, $entry, false, true, false );
		} else {
			echo $variable;
		}
		die();
	}

	public static function get_field_value( $form, $entry, $field_id ) {

		$field = RGFormsModel::get_field( $form, $field_id );

		if ( ! $field instanceof GF_Field ) {
			$field = GF_Fields::create( $field );
		}

		$value = RGFormsModel::get_lead_field_value( $entry, $field );
		if ( is_array( $value ) ) {
			$value = rgar( $value, $field_id );
		}


		return $value = GFCommon::format_variable_value( $value, false, true, 'html', true );
	}

}