<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro_Bulk {

	public static function construct() {

		if ( rgget( 'page' ) != 'gf_entries' || rgget( 'view' ) == 'entry' ) {
			return;
		}

		add_filter( 'gform_entry_apply_button', array( __CLASS__, 'bulk_action_old' ), 10, 1 );
		add_action( 'admin_footer', array( __CLASS__, 'bulk_action_new' ) );
		add_action( 'admin_footer', array( __CLASS__, 'bulk_action' ) );
	}


	public static function bulk_action_old( $apply_button ) {
		$pos          = is_rtl() ? 'right' : 'left';
		$apply_button .= '<span style="margin-' . $pos . ':10px"></span>
		<input type="submit" class="add-new-h2" style="cursor:pointer;padding: 5px 8px;top:-1px;" value="' . __( 'Send SMS to selected entries', 'GF_SMS' ) . '" onclick="return handleBulksms(\'bulk_action\');" />';

		return $apply_button;
	}


	public static function bulk_action_new() {
		$pos          = is_rtl() ? 'right' : 'left';
		$value        = __( 'Send SMS to selected entries', 'GF_SMS' );
		$apply_button = "<span style=\"margin-{$pos}:10px\"></span><input type=\"submit\" class=\"add-new-h2\" style=\"cursor:pointer;padding:5px 8px;top:-1px;\" value=\"{$value}\" onclick=\"return handleBulksms(\'bulk_action\');\" />";
		?>
        <script type="text/javascript">
            jQuery(function () {
                jQuery('<option>').val('sendsms').text('<?php echo __( 'Send SMS to selected entries', 'GF_SMS' ) ?>').appendTo("select[name='action']");
                jQuery('<option>').val('sendsms').text('<?php echo __( 'Send SMS to selected entries', 'GF_SMS' ) ?>').appendTo("select[name='action2']");
                jQuery('<option>').val('sendsms').text('<?php echo __( 'Send SMS to selected entries', 'GF_SMS' ) ?>').appendTo("select[name='bulk_action']");
                jQuery('<option>').val('sendsms').text('<?php echo __( 'Send SMS to selected entries', 'GF_SMS' ) ?>').appendTo("select[name='bulk_action2']");
                jQuery("input[id='doaction']").after('<?php echo str_replace( PHP_EOL, '', $apply_button ); ?>');
                jQuery("input[id='doaction2']").after('<?php echo str_replace( PHP_EOL, '', $apply_button ); ?>');
            });
        </script>
		<?php
	}

	public static function bulk_action() { ?>
        <script type="text/javascript">
            function handleBulksms(actionElement) {
                var entryIds;
                if (typeof getEntryIds == 'function') {
                    entryIds = getEntryIds();
                } else {
                    entryIds = getLeadIds();
                }

                if (entryIds.length == 0) {
                    alert('<?php _e( 'Please select at least one entry.', 'gravityforms' ); ?>');
                    return false;
                }
                jQuery("select[name='action']").val('sendsms');
                jQuery("select[name='action2']").val('sendsms');
                jQuery("select[name='bulk_action']").val('sendsms');
                jQuery("select[name='bulk_action2']").val('sendsms');
            }
        </script>
		<?php
		if ( rgpost( 'action' ) == 'sendsms' || rgpost( 'action2' ) == 'sendsms' || rgpost( 'bulk_action' ) == 'sendsms' || rgpost( 'bulk_action2' ) == 'sendsms' ) {

			check_admin_referer( 'gforms_entry_list', 'gforms_entry_list' );

			$form_id = rgget( "id" );

			if ( rgpost( "all_entries" ) ) {
				if ( GFHANNANSMS_Pro::entry_type( 'GFAPI', 'get_entry_ids' ) ) {
					$entries = GFAPI::get_entry_ids( $form_id, array() );
				} else {
					$entries = GFFormsModel::search_lead_ids( $form_id, array() );
				}
			} else {
				$entries = rgpost( "entry" ) ? rgpost( "entry" ) : rgpost( "lead" );
			}

			$clients = array();
			foreach ( (array) $entries as $entry_id ) {
				$entry = GFAPI::get_entry( $entry_id );
				if ( is_wp_error( $entry ) ) {
					$entry = false;
				}
				$clients[] = GFHANNANSMS_Pro_Entries_Sidebar::get_phone_numbers( $form_id, $entry );
			}
			$clients = str_replace( ',,', ',', implode( ',', $clients ) );
			$clients = $after_unigue = array_filter( array_unique( explode( ',', $clients ) ) );
			$clients = ! empty( $clients ) ? str_replace( ',,', ',', implode( ',', $clients ) ) : '';

			if ( ! empty( $clients ) ) { ?>
                <form id="bulk_confirmation" method="post" action="admin.php?page=gf_hannansms&view=send">
                    <input type="hidden" name="hannansms_bulk_numbers" value="<?php echo $clients; ?>"/>
                    <input type="hidden" name="hannansms_bulk_entries" value="<?php echo implode( ',', $entries ) ?>"/>
                    <input type="hidden" name="hannansms_bulk_form_id" value="<?php echo $form_id; ?>"/>
                    <input type="submit" name="hannansms_bulk_submit" class="button-primary"
                           style="display:none !important;" value=""/>
                </form>
                <script type='text/javascript'>
                    send_numbers();

                    function send_numbers() {
                        alert('<?php echo __( "Number of recievers (repeated numbers have been removed)", "GF_SMS" ) . ' = ' . count( $after_unigue ) ?>');
                    }

                    document.getElementById('bulk_confirmation').submit();
                </script>
			<?php } else { ?>
                <script type="text/javascript">
                    no_have_number();

                    function no_have_number() {
                        alert('<?php _e( "Selected entries don\'t have phone meta.", "GF_SMS" ); ?>');
                    }
                </script>
			<?php }
		}
	}


	public static function send_many_numbers() {
		$settings = GFHANNANSMS_Pro::get_option();
		$is_OK    = ( ! empty( $settings["ws"] ) && $settings["ws"] != 'no' );
		$entries  = array();
		$form_id  = '';

		if ( rgpost( "hannansms_bulk_numbers" ) ) {
			$form_id = rgpost( "hannansms_bulk_form_id" ) ? absint( rgpost( "hannansms_bulk_form_id" ) ) : "";
			$entries = rgpost( "hannansms_bulk_entries" ) ? explode( ',', sanitize_text_field( rgpost( "hannansms_bulk_entries" ) ) ) : array();
		} else if ( ! rgempty( "gf_hannansms_submit_send" ) && rgpost( "gf_hannansms_form_id_send" ) && rgpost( "gf_hannansms_entries_send" ) ) {
			$form_id = rgpost( "gf_hannansms_form_id_send" ) ? absint( rgpost( "gf_hannansms_form_id_send" ) ) : "";
			$entries = rgpost( "gf_hannansms_entries_send" ) ? explode( ',', sanitize_text_field( rgpost( "gf_hannansms_entries_send" ) ) ) : array();
		}

		?>
        <div class="wrap gforms_edit_form gf_browser_gecko">

            <h2 style="margin-bottom:10px;"><?php _e( "Send SMS to custom numbers", "GF_SMS" ) ?></h2>

            <a class="button"
               href="admin.php?page=gf_settings&subview=gf_sms_pro"><?php _e( "SMS General settings", "GF_SMS" ) ?></a>

			<?php if ( ! empty( $form_id ) ) { ?>
                <a class="button"
                   href="admin.php?page=gf_entries&id=<?php echo $form_id; ?>"><?php _e( "Back to enteries", "GF_SMS" ) ?></a>
			<?php }


			if ( ! rgempty( "gf_hannansms_submit_send" ) ) {

				check_admin_referer( "bulksend", "gf_hannansms_bulk_send" );

				global $current_user;
				$user_id   = 0;
				$user_name = __( "SMS Pro", "GF_SMS" );
				if ( $current_user && $user_data = get_userdata( $current_user->ID ) ) {
					$user_id   = $current_user->ID;
					$user_name = $user_data->display_name;
				}

				$from    = sanitize_text_field( rgpost( "gf_hannansms_from_send" ) );
				$from_db = get_option( "gf_sms_last_sender" );
				if ( $from and $from_db != $from ) {
					update_option( "gf_sms_last_sender", $from );
				}

				$to  = GFHANNANSMS_Form_Send::change_mobile( sanitize_text_field( rgpost( "gf_hannansms_to_send" ) ), '' );
				$msg = wp_kses( rgpost( "gf_hannansms_msg_send" ), array( 'br' => array() ) );
				$msg = str_replace( array( "<br>", "<br/>", "<br />" ), array( "", "", "" ), $msg );

				if ( $to ) {

					$result = GFHANNANSMS_Pro_WebServices::action( $settings, 'send', $from, $to, $msg );

					if ( $result == 'OK' ) {

						GFHANNANSMS_Pro_SQL::save_sms_sent( $form_id, $entries, $from, $to, $msg, '' );
						echo '<div class="updated fade" style="padding:6px">' . __( "Message sent successfully", "GF_SMS" ) . '</div>';

						if ( rgpost( "gf_hannansms_form_id_send" ) && rgpost( "gf_hannansms_entries_send" ) ) {
							foreach ( (array) $entries as $entry_id ) {
								$entry = GFAPI::get_entry( $entry_id );
								if ( is_wp_error( $entry ) ) {
									$entry = false;
								}
								$clients = GFHANNANSMS_Pro_Entries_Sidebar::get_phone_numbers( $form_id, $entry );
								if ( ! empty( $clients ) ) {
									$receiver_note = sprintf( __( "Bulk Action : SMS sent to numbers successfully. Numbers : %s | Sender Number : %s | Message Body : %s.", "GF_SMS" ), $clients, $from, $msg );
									RGFormsModel::add_note( $entry_id, $user_id, $user_name, $receiver_note );
								}
							}
						}


					} else {

						echo '<div class="error fade" style="padding:6px">' . sprintf( __( "The sending of the message encountered an error. Reason = %s", "GF_SMS" ), $result ) . '</div>';

						if ( rgpost( "gf_hannansms_form_id_send" ) && rgpost( "gf_hannansms_entries_send" ) ) {
							foreach ( (array) $entries as $entry_id ) {
								$entry = GFAPI::get_entry( $entry_id );
								if ( is_wp_error( $entry ) ) {
									$entry = false;
								}
								$clients = GFHANNANSMS_Pro_Entries_Sidebar::get_phone_numbers( $form_id, $entry );
								if ( ! empty( $clients ) ) {
									$reciever_note = sprintf( __( "Bulk Action : The sending of the message encountered an error. Numbers : %s | Sender Number : %s | Reason : %s | Message Body : %s.", "GF_SMS" ), $clients, $from, $result, $msg );
									RGFormsModel::add_note( $entry_id, $user_id, $user_name, $reciever_note );
								}
							}
						}

					}
				} else {
					echo '<div class="error fade" style="padding:6px">' . __( "The sending of the message encountered an error because number is empty.", "GF_SMS" ) . '</div>';
				}
			}

			GFHANNANSMS_Pro::show_credit( $settings["cr"], true ); ?>

            <div class="postbox" style="padding:0 30px 20px; margin-top:10px;background:#F6FBFD;">

                <h3 class="hndle" style="padding-bottom:15px"><?php _e( "Send SMS to custom numbers", "GF_SMS" ) ?></h3>

                <form method="post" action="">

					<?php if ( $is_OK ) {

						wp_nonce_field( "bulksend", "gf_hannansms_bulk_send" ) ?>

                        <div class="margin_vertical_10">

                            <p class="gf_hannansms_bulk"><?php _e( "SMS Sender Number", "GF_SMS" ) ?></p>

                            <select id="gf_hannansms_from_send" name="gf_hannansms_from_send">
                                <option value=""><?php _e( "Select Sender Number", "GF_SMS" ); ?></option>
								<?php
								$sender_num = ! empty( $settings["from"] ) ? $settings["from"] : '';
								if ( $sender_num == '' || strpos( $settings["from"], ',' ) === false ) {
									if ( $sender_num and $sender_num != '' ) {
										$last_from = get_option( "gf_sms_last_sender" );
										$selected  = ( $sender_num == $last_from ) ? "selected='selected'" : "";
										?>
                                        <option
                                        value="<?php echo $sender_num ?>" <?php echo $selected ?>><?php echo $sender_num ?></option><?php
									}
								} else {
									unset( $sender_num );
									$sender_nums = array();
									$sender_nums = explode( ',', $settings["from"] );
									foreach ( (array) $sender_nums as $sender_num ) {
										$last_from = get_option( "gf_sms_last_sender" );
										$selected  = ( $sender_num == $last_from ) ? "selected='selected'" : "";
										?>
                                        <option
                                        value="<?php echo $sender_num ?>" <?php echo $selected ?> ><?php echo $sender_num ?></option><?php
									}
								}
								?>
                            </select>

                        </div>

                        <div class="margin_vertical_10">
                            <p class="gf_hannansms_bulk"><?php _e( "Reciever numbers. separate with commas (,). +16175551212,+16175551213", "GF_SMS" ) ?></p>
							<?php $reciver = rgpost( "hannansms_bulk_numbers" ) ? sanitize_text_field( rgpost( "hannansms_bulk_numbers" ) ) : sanitize_text_field( rgpost( "gf_hannansms_to_send" ) ); ?>
                            <input type="text" style="direction:ltr; text-align:left;width:100%" class="fieldwidth-1"
                                   name="gf_hannansms_to_send" value="<?php echo $reciver; ?>"/>
                        </div>

                        <div class="margin_vertical_10">
                            <p class="gf_hannansms_bulk"><?php _e( "Message", "GF_SMS" ) ?></p>

                            <textarea style="height: 150px; width:415px;"
                                      name="gf_hannansms_msg_send"><?php echo wp_kses( rgpost( "gf_hannansms_msg_send" ), array( 'br' => array() ) ); ?></textarea>
                        </div>

                        <div class="margin_vertical_10" style="clear:both; margin-top:20px;">
                            <input class="button-primary" type="submit" value="<?php _e( "Send", "GF_SMS" ) ?>"
                                   name="gf_hannansms_submit_send" style="width:100px;">
                        </div>

						<?php if ( ! empty( $form_id ) && ! empty( $entries ) ) { ?>
                            <input type="hidden" name="gf_hannansms_form_id_send" value="<?php echo $form_id; ?>"/>
                            <input type="hidden" name="gf_hannansms_entries_send"
                                   value="<?php echo implode( ',', $entries ) ?>"/>
						<?php }

					} else {
						_e( "Checkup SMS General settings.", "GF_SMS" );
					}
					?>
                </form>

            </div>

        </div>
		<?php
	}
}