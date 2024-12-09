<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro_Verification {

	public static function construct() {

		if ( is_admin() ) {
			add_filter( 'gform_add_field_buttons', array( __CLASS__, 'gravity_sms_fields' ), 9998 );
			add_filter( 'gform_field_type_title', array( __CLASS__, 'title' ), 10, 2 );
			add_action( 'gform_editor_js_set_default_values', array( __CLASS__, 'default_label' ) );
			add_action( 'gform_editor_js', array( __CLASS__, 'js' ) );
			add_action( 'gform_field_standard_settings', array( __CLASS__, 'standard_settings' ), 10, 2 );
			add_filter( 'gform_tooltips', array( __CLASS__, 'tooltips' ) );
		}

		add_filter( 'gform_field_validation', array( __CLASS__, 'validation' ), 10, 4 );
		add_filter( 'gform_entry_post_save', array( __CLASS__, 'process' ), 10, 2 );
		add_action( 'gform_field_input', array( __CLASS__, 'input' ), 10, 5 );
		add_action( 'gform_field_css_class', array( __CLASS__, 'classes' ), 10, 3 );
		add_filter( 'gform_field_content', array( __CLASS__, 'content' ), 10, 5 );
		add_filter( 'gform_merge_tag_filter', array( __CLASS__, 'all_fields' ), 10, 4 );
	}

	public static function gravity_sms_fields( $field_groups ) {

		$gravity_sms_fields = array(
			'name'   => 'gravity_sms_fields',
			'label'  => __( 'SMS Fields', 'GF_SMS' ),
			'fields' => array(
				array(
					"class"     => "button",
					"value"     => __( 'Verification', 'GF_SMS' ),
					"data-type" => "sms_verification",
					//"onclick" => "StartAddField('sms_verification');"//deprecated
				),
			)
		);

		array_push( $field_groups, $gravity_sms_fields );

		return $field_groups;
	}

	public static function title( $title, $field_type ) {
		if ( $field_type == 'sms_verification' ) {
			return $title = __( 'Mobile Verification', 'GF_SMS' );
		}

		return $title;
	}

	public static function default_label() { ?>
        case "sms_verification" :
        field.label = '<?php echo __( 'Mobile Verification', 'GF_SMS' ); ?>';
        break;
		<?php
	}

	public static function classes( $classes, $field, $form ) {
		if ( ! empty( $field["type"] ) && $field["type"] == "sms_verification" ) {
			$classes .= " gfield_contains_required gform_sms_verification";
		}

		return $classes;
	}

	public static function input( $input, $field, $value, $entry_id, $form_id ) {

		if ( $field["type"] == "sms_verification" ) {

			$form = GFAPI::get_form( $form_id );

			$is_entry_detail = GFCommon::is_entry_detail();
			$is_form_editor  = GFCommon::is_form_editor();

			$field_id = $field["id"];
			$form_id  = empty( $form_id ) ? rgget( "id" ) : $form_id;

			$disabled_text = $is_form_editor ? "disabled='disabled'" : '';

			$input_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$field_id" : 'input_' . $form_id . "_$field_id";

			$size         = rgar( $field, "size" );
			$class_suffix = $is_entry_detail ? '_admin' : '';
			$class        = $size . $class_suffix;

			$max_length = '';

			$placeholder_attribute = $field->get_field_placeholder_attribute();
			$required_attribute    = $field->isRequired ? 'aria-required="true"' : '';
			$invalid_attribute     = $field->failed_validation ? 'aria-invalid="true"' : 'aria-invalid="false"';
			$html5_attributes      = " {$placeholder_attribute} {$required_attribute} {$invalid_attribute} {$max_length} ";

			$tabindex = GFCommon::get_tabindex();

			if ( ! is_admin() && ( RGFormsModel::get_input_type( $field ) == 'adminonly_hidden' ) ) {
				return '';
			}

			$text_input = '<div class="ginput_container ginput_container_text ginput_container_verfication">';
			$text_input .= '<input name="input_' . $field_id . '" id="' . $input_id . '" type="text" value="' . esc_attr( $value ) . '" class="verify_code ' . esc_attr( $class ) . '" ' . $tabindex . ' ' . $html5_attributes . ' ' . $disabled_text . '/>';

			if ( $is_form_editor ) {
				$input = $text_input;
				$input .= '</div><br/>';
				$input .= '<div class="gf-html-container ginput_container_verfication" id="ginput_container_verfication_' . $field_id . '">';
				$input .= '<span>';
				$input .= __( 'By adding this field, the user must first verify his phone number through SMS in order proceed into the next stages of filling out the form. Please be aware that normally no filed will be added to the form. However, the field will appear whenever you wish to complete or register the form.', 'GF_SMS' );
				$input .= '</span>';
				$input .= '</div>';

			} else if ( $is_entry_detail ) {
				$input = $text_input . '</div>';
			} else {

				$mobile_field_id = rgar( $field, "field_sms_verify_mobile" );
				$mobile_field    = RGFormsModel::get_field( $form, $mobile_field_id );

				$diff_page = ! empty( $mobile_field['pageNumber'] ) && ! empty( $field['pageNumber'] ) && $mobile_field['pageNumber'] != $field['pageNumber'] ? true : false;

				if ( $diff_page && apply_filters( 'sms_verify_self_validation', true ) ) {
					$result = self::validation( array( 'action' => 'self' ), $value, $form, $field );
				}

				if ( ! $diff_page && apply_filters( 'gform_button_verify', true ) && empty( $field['conditionalLogic'] ) ) {
					$max_page_num = GFFormDisplay::get_max_page_number( $form );
					if ( ! empty( $field['pageNumber'] ) && $field['pageNumber'] == $max_page_num || ! empty( $field['pageNumber'] ) ) {
						add_filter( 'gform_submit_button', array( __CLASS__, 'submit_button' ), 10, 2 );
					} else if ( $max_page_num > 1 ) {
						add_filter( 'gform_next_button', array( __CLASS__, 'next_button' ), 10, 2 );
					}
				}


				if ( apply_filters( 'sms_verify_display_none', true ) ) {
					return '<style type="text/css">#field_' . $form_id . '_' . $field_id . '{display:none !important;}</style>';
				} else {

					$input = '';

					if ( apply_filters( 'sms_verify_field', false ) || ( $diff_page && apply_filters( 'sms_verify_field', false ) ) ) {
						$input .= $text_input;
						if ( apply_filters( 'sms_verify_resend', false ) ) {
							$input .= '<input id="gform_resend_button" class="gform_button button" name="resend_verify_sms" type="submit" value="' . __( 'Resend', 'GF_SMS' ) . '">';
						}
						$input .= '</div>';
					}

					if ( ! empty( $result["message_"] ) ) {
						$input .= '<div class="ginput_container ginput_container_text ginput_container_verfication ginput_container_verfication_"><p>';
						$input .= $result["message_"];
						$input .= '</p></div>';
					}
				}
			}
		}

		return $input;
	}


	public static function validation( $result, $value, $form, $field ) {

		if ( $field["type"] == "sms_verification" ) {

			global $wpdb;
			$verify_table = GFHANNANSMS_Pro_SQL::verify_table();
			$form_id      = $form['id'];

			$mobile_field_id = rgar( $field, "field_sms_verify_mobile" );
			$mobile_field    = RGFormsModel::get_field( $form, $mobile_field_id );
			$mobile_value    = self::get_mobile( $field, false );
			if ( $mobile_field->noDuplicates && RGFormsModel::is_duplicate( $form_id, $mobile_field, $mobile_value ) ) {
				return $result;
			}

			$show_input = true;
			$mobile     = self::get_mobile( $field );
			if ( empty( $mobile ) || strlen( $mobile ) < 3 ) {
				$result["is_valid"] = false;
				$show_input         = false;
				$result["message"]  = __( "Please enter your mobile number for verification purposes in the field assigned for mobile numbers.", "GF_SMS" );
			} else {

				$white_list = self::white_list( $field );

				if ( ! in_array( $mobile, $white_list ) ) {

					$get_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$verify_table} WHERE mobile = %s AND form_id = %s AND entry_id = %s ORDER BY id DESC LIMIT 1", $mobile, $form_id, 0 ) );

					if ( ! empty( $get_result ) && is_object( $get_result ) ) {
						$ID       = $get_result->id;
						$code     = $get_result->code;
						$status   = $get_result->status;
						$try_num  = $get_result->try_num;
						$sent_num = $get_result->sent_num;
					} else {
						$ID       = '';
						$code     = '';
						$status   = '';
						$try_num  = '';
						$sent_num = '';
					}
					$try_num  = ( ! empty( $try_num ) && $try_num != 0 ) ? $try_num : 0;
					$sent_num = ( ! empty( $sent_num ) && $sent_num != 0 ) ? $sent_num : 0;

					$new_try_num = ( isset( $result["action"] ) && $result["action"] == 'self' ) ? $try_num : $try_num + 1;

					if ( empty( $code ) || ! $code ) {
						$type = rgar( $field, 'sms_verify_code_type_radio' );
						if ( $type == 'manual' ) {
							$delimator   = ',';
							$manual      = explode( $delimator, rgar( $field, 'sms_verify_code_type_manual' ) );
							$random_keys = array_rand( $manual, 1 );
							$code        = isset( $manual[ $random_keys[0] ] ) ? $manual[ $random_keys[0] ] : ( isset( $manual[ $random_keys ] ) ? $manual[ $random_keys ] : rand( 10000, 99999 ) );
						} else {
							$code = self::rand_mask( rgar( $field, 'sms_verify_code_type_rand' ) );
						}
					}

					$allowed_try = rgar( $field, 'sms_verify_try_num' );
					$allowed_try = $allowed_try ? ( $allowed_try - 1 ) : 10;

					if ( $try_num <= $allowed_try && ! rgempty( 'input_' . $field["id"] ) && ! empty( $code ) && rgpost( 'input_' . str_replace( '.', '_', $field["id"] ) ) == $code ) {
						if ( ! empty( $ID ) && $ID != 0 ) {
							GFHANNANSMS_Pro_SQL::update_verify( $ID, $new_try_num, $sent_num, 0, 1 );
						} else {
							GFHANNANSMS_Pro_SQL::insert_verify( $form_id, 0, $mobile, $code, 1, $new_try_num, $sent_num );
						}
					} else if ( ( $status != 1 && $status != '1' ) || empty( $status ) || $status == 0 ) {

						$result["is_valid"] = false;

						if ( $try_num < $allowed_try ) {

							$message = rgar( $field, 'sms_verify_code_msg_body' );
							$message = strpos( $message, '%code%' ) === false ? $message . '%code%' : $message;
							$message = $message ? $message : $code;
							$message = str_replace( '%code%', $code, $message );
							//$message = GFCommon::replace_variables($message, $form, $entry, false, true, false);

							$result["message"] = __( 'Enter the code you have received by SMS in above field to verify your mobile number.', 'GF_SMS' );

							$allowed_send = rgar( $field, 'sms_verify_sent_num' );
							$allowed_send = $allowed_send ? $allowed_send : 0;

							if ( $sent_num < $allowed_send ) {
								add_filter( 'sms_verify_resend', '__return_true', 99 );
							}

							if ( ! empty( $ID ) && $ID != 0 ) {

								if ( ! rgempty( 'resend_verify_sms' ) ) {

									$result["message"] = __( "Sending the message encountered an error.", "GF_SMS" );

									if ( $sent_num <= $allowed_send ) {

										if ( GFHANNANSMS_Form_Send::Send( $mobile, $message, $from = '', $form_id, '', $code ) == 'OK' ) {
											$sent_num = $sent_num + 1;

											GFHANNANSMS_Pro_SQL::update_verify( $ID, $try_num, $sent_num, 0, 0 );

											$result["message"] = __( "The activation code was sent again via SMS.", "GF_SMS" );
										}
									}
								} else if ( ! rgempty( 'input_' . $field["id"] ) ) {

									GFHANNANSMS_Pro_SQL::update_verify( $ID, $new_try_num, $sent_num, 0, 0 );

									$result["message"] = __( "The entered code is incorrect.", "GF_SMS" );
								}

							} else {
								if ( GFHANNANSMS_Form_Send::Send( $mobile, $message, $from = '', $form_id, '', $code ) ) {
									$sent_num = $sent_num + 1;
									GFHANNANSMS_Pro_SQL::insert_verify( $form_id, 0, $mobile, $code, 0, $try_num, $sent_num );
								} else {
									$result["message"] = __( "Sending the message encountered an error.", "GF_SMS" );
								}
							}

						} else {

							if ( ! empty( $ID ) && $ID != 0 ) {
								GFHANNANSMS_Pro_SQL::update_verify( $ID, $new_try_num, $sent_num, 0, 0 );
							}
							$show_input        = false;
							$result["message"] = __( "You have maxed out of the number of times you are allowed to verify your mobile number for this form.", "GF_SMS" );
						}

					}
				}
			}

			if ( isset( $result["is_valid"] ) && $result["is_valid"] != true ) {

				add_filter( 'gform_validation_message', array( __CLASS__, 'change_message' ), 10, 2 );
				add_filter( 'sms_verify_display_none', '__return_false', 99 );


				if ( $show_input == true ) {
					add_filter( 'sms_verify_field', '__return_true', 99 );
				}

				if ( isset( $result["action"] ) && $result["action"] == 'self' ) {
					$result["message_"] = ! empty( $result["message"] ) ? $result["message"] : '';
				} else {
					add_filter( 'sms_verify_self_validation', '__return_false', 99 );
				}
			} else {
				add_filter( 'gform_button_verify', '__return_false', 99 );
			}

		}

		return $result;
	}


	public static function process( $entry, $form ) {

		$sms_verification = GFCommon::get_fields_by_type( $form, array( 'sms_verification' ) );

		foreach ( (array) $sms_verification as $field ) {

			global $wpdb;
			$verify_table = GFHANNANSMS_Pro_SQL::verify_table();

			$field = (array) $field;

			$mobile = self::get_mobile( $field );

			$get_result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$verify_table} WHERE mobile = %s AND form_id = %s AND entry_id = %s ORDER BY id DESC LIMIT 1", $mobile, $form['id'], 0 ) );

			if ( ! empty( $get_result ) && is_object( $get_result ) ) {

				$ID     = ! empty( $get_result->id ) ? $get_result->id : '';
				$status = ! empty( $get_result->status ) ? $get_result->status : 0;

				if ( ! empty( $ID ) && $ID != 0 && ! empty( $status ) && $status != 0 ) {

					$verify_code = $entry[ $field['id'] ] = $get_result->code;

					GFHANNANSMS_Pro_SQL::update_entry_verify_sent( $form['id'], $entry['id'], $verify_code );

					$try_num  = $get_result->try_num;
					$sent_num = $get_result->sent_num;
					GFHANNANSMS_Pro_SQL::update_verify( $ID, $try_num, $sent_num, $entry['id'], 1 );

					GFAPI::update_entry_field( $entry['id'], $field['id'], $verify_code );
				}
			}
		}

		return $entry;
	}

	public static function content( $content, $field, $value, $entry_id, $form_id ) {
		/*
		if ( $field["type"] == "sms_verification" ) {
			return $content;
		}
		*/
		return $content;
	}

	public static function js() {
		$settings = GFHANNANSMS_Pro::get_option();
		?>
        <script type='text/javascript'>
            fieldSettings["sms_verification"] = ".label_setting, .placeholder_setting,.label_placement_setting, .conditional_logic_field_setting, .admin_label_setting, .size_setting, .default_value_setting, .css_class_setting, .sms_verification_setting";

            function gf_sms_verify_populate_select() {
                var options = ["<option value=''></option>"];
                jQuery.each(window.form.fields, function (i, field) {
                    if (field.inputs) {
                        jQuery.each(field.inputs, function (i, input) {
                            options.push("<option value='", input.id, "'>", field.label, " (", input.label, ") (ID: ", input.id, ")</option>");
                        });
                    } else {
                        options.push("<option value='", field.id, "'>", field.label, " (ID: ", field.id, ")</option>");
                    }
                });
                jQuery("select[id^=field_sms_verify_]").html(options.join(""));
            }

            jQuery(document).bind("gform_field_deleted", gf_sms_verify_populate_select);
            jQuery(document).bind("gform_field_added", gf_sms_verify_populate_select);
            gf_sms_verify_populate_select();
            jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
                if (field.sms_verify_code_type_radio == 'manual') {
                    jQuery("#sms_verify_code_type_radio_manual").prop("checked", true);
                    jQuery("#sms_verify_code_type_rand_div").hide("slow");
                    jQuery("#sms_verify_code_type_manual_div").show("slow");
                }
                else {
                    jQuery("#sms_verify_code_type_radio_rand").prop("checked", true);
                    jQuery("#sms_verify_code_type_rand_div").show("slow");
                    jQuery("#sms_verify_code_type_manual_div").hide("slow");
                }
                // show hide div when radio button changed
                jQuery('input[name="sms_verify_code_type_radio"]').on("click", function () {
                    if (jQuery('input[name="sms_verify_code_type_radio"]:checked').val() == 'manual') {
                        jQuery("#sms_verify_code_type_rand_div").hide("slow");
                        jQuery("#sms_verify_code_type_manual_div").show("slow");
                    }
                    else {
                        jQuery("#sms_verify_code_type_rand_div").show("slow");
                        jQuery("#sms_verify_code_type_manual_div").hide("slow");
                    }
                });
                if (field.sms_verify_country_code_radio == 'dynamic') {
                    jQuery("#sms_verify_country_code_radio_dynamic").prop("checked", true);
                    jQuery("#sms_verify_country_code_static_div").hide("slow");
                    jQuery("#field_sms_verify_country_code_dynamic_div").show("slow");
                }
                else {
                    jQuery("#sms_verify_country_code_radio_static").prop("checked", true);
                    jQuery("#sms_verify_country_code_static_div").show("slow");
                    jQuery("#field_sms_verify_country_code_dynamic_div").hide("slow");
                }
                // show hide div when radio button changed
                jQuery('input[name="sms_verify_country_code_radio"]').on("click", function () {
                    if (jQuery('input[name="sms_verify_country_code_radio"]:checked').val() == 'dynamic') {
                        jQuery("#sms_verify_country_code_static_div").hide("slow");
                        jQuery("#field_sms_verify_country_code_dynamic_div").show("slow");
                    }
                    else {
                        jQuery("#sms_verify_country_code_static_div").show("slow");
                        jQuery("#field_sms_verify_country_code_dynamic_div").hide("slow");
                    }
                });
                jQuery("#sms_verify_code_type_rand").val(field["sms_verify_code_type_rand"]);
                jQuery("#sms_verify_code_type_manual").val(field["sms_verify_code_type_manual"]);
                jQuery('#sms_verify_country_code_static').val(field.sms_verify_country_code_static == undefined ? <?php echo ! empty( $settings ) && ! empty( $settings["code"] ) ? $settings["code"] : "''"; ?> : field.sms_verify_country_code_static);
                jQuery("#field_sms_verify_country_code_dynamic").val(field["field_sms_verify_country_code_dynamic"]);
                jQuery("#sms_verify_try_num").val(field["sms_verify_try_num"]);
                jQuery("#sms_verify_sent_num").val(field["sms_verify_sent_num"]);
                jQuery("#sms_verify_code_msg_body").val(field["sms_verify_code_msg_body"]);
                jQuery("#sms_verify_code_white_list").val(field["sms_verify_code_white_list"]);
                jQuery("#sms_verify_code_all_fields").attr("checked", field["sms_verify_code_all_fields"] == true);

                var fields = [ <?php foreach ( self::get_this_fields() as $key ) {
					echo "'{$key}',";
				} ?> ];
                fields.map(function (fname) {
                    jQuery("#field_sms_verify_" + fname).attr("value", field["field_sms_verify_" + fname]);
                });
            });
        </script>
		<?php
	}


	public static function tooltips( $tooltips ) {

		$tooltips['form_gravity_sms_fields']        = __( '<h6>Gravity SMS</h6>Gravity SMS Pro fields', 'GF_SMS' );
		$tooltips['sms_verify_code_type_select']    = __( 'You can determine how you would like your activation codes to be considered. Note that in the manual type each code might be sent to several people.', 'GF_SMS' );
		$tooltips["sms_verify_mobile"]              = __( "<h6>Mobile Field</h6>Select the mobile number field to verify.", "GF_SMS" );
		$tooltips["sms_verify_code_msg_body"]       = __( "<h6>SMS Text</h6>Enter the SMS text containing the activation code. Also for the activation code, use the given short-code.", "GF_SMS" );
		$tooltips["sms_verify_try_num"]             = __( "Define how many time a number is allowed to enter the wrong code in this form.", "GF_SMS" );
		$tooltips["sms_verify_sent_num"]            = __( "Define how many time a number is allowed to request to get the activation code in this form.", "GF_SMS" );
		$tooltips["sms_verify_all_fields"]          = __( "By activating this part, the contents of this field will be concealed from the ‘all_fields” tag.", "GF_SMS" );
		$tooltips["sms_verify_country_code_select"] = __( "<h6>Country code</h6>You can change the default country code. but If entered mobile phone number was international format, this country code will be effectless.", "GF_SMS" );
		$tooltips["sms_verify_code_white_list"]     = __( "<h6>White list</h6>Enter the numbers that do not need verification. ", "GF_SMS" );

		return $tooltips;
	}


	public static function standard_settings( $position, $form_id ) {

		if ( $position == 50 ) { ?>

            <li class="sms_verification_setting field_setting">

                <div class="field_sms_verify_mobile">
                    <br/>
                    <label for="field_sms_verify_mobile">
						<?php _e( 'Mobile number field', 'GF_SMS' ); ?>
						<?php gform_tooltip( 'sms_verify_mobile' ) ?>
                    </label>
                    <select id="field_sms_verify_mobile"
                            onchange="SetFieldProperty('field_sms_verify_mobile', this.value);"></select>
                </div>

                <div class="sms_country_code">
                    <br/>
                    <label>
						<?php _e( "Country Code", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_country_code_select" ); ?>
                    </label>
                    <div>
                        <input type="radio" name="sms_verify_country_code_radio"
                               id="sms_verify_country_code_radio_static" size="10" value="static"
                               onclick="SetFieldProperty('sms_verify_country_code_radio', this.value);"/>
                        <label for="sms_verify_country_code_radio_static" class="inline">
							<?php _e( 'Static', 'GF_SMS' ); ?>
                        </label>

                        <input type="radio" name="sms_verify_country_code_radio"
                               id="sms_verify_country_code_radio_dynamic" size="10" value="dynamic"
                               onclick="SetFieldProperty('sms_verify_country_code_radio', this.value);"/>
                        <label for="sms_verify_country_code_radio_dynamic" class="inline">
							<?php _e( 'Dynamic', 'GF_SMS' ); ?>
                        </label>
                    </div>

                    <div id="sms_verify_country_code_static_div">
                        <input id="sms_verify_country_code_static" name="sms_verify_country_code_static" type="text"
                               size="35" style="direction:ltr !important;text-align:left;"
                               onkeyup="SetFieldProperty('sms_verify_country_code_static', this.value);">
                    </div>

                    <div id="field_sms_verify_country_code_dynamic_div">
                        <select id="field_sms_verify_country_code_dynamic"
                                onchange="SetFieldProperty('field_sms_verify_country_code_dynamic', this.value);"></select>
                    </div>
                </div>

                <div class="sms_verify_type_div">
                    <br/>
                    <label>
						<?php _e( "How to enter the verification codes?", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_code_type_select" ); ?>
                    </label>
                    <div>
                        <input type="radio" name="sms_verify_code_type_radio" id="sms_verify_code_type_radio_rand"
                               size="10" value="rand"
                               onclick="SetFieldProperty('sms_verify_code_type_radio', this.value);"/>
                        <label for="sms_verify_code_type_radio_rand" class="inline">
							<?php _e( 'Random', 'GF_SMS' ); ?>
                        </label>

                        <input type="radio" name="sms_verify_code_type_radio" id="sms_verify_code_type_radio_manual"
                               size="10" value="manual"
                               onclick="SetFieldProperty('sms_verify_code_type_radio', this.value);"/>
                        <label for="sms_verify_code_type_radio_manual" class="inline">
							<?php _e( 'Manual', 'GF_SMS' ); ?>
                        </label>
                    </div>

                    <div id="sms_verify_code_type_rand_div">
                        <input id="sms_verify_code_type_rand" name="sms_verify_code_type_rand" type="text" size="35"
                               style="direction:ltr !important;text-align:left;"
                               onkeyup="SetFieldProperty('sms_verify_code_type_rand', this.value);">
                        <p class="mask_text_description_" style="margin: 5px 0px 0px;">
							<?php _e( 'Enter a custom mask', 'gravityforms' ) ?>.
                            <a onclick="tb_show('<?php echo __( 'Custom Mask Instructions', 'gravityforms' ) ?>', '#TB_inline?width=350&inlineId=custom_mask_instructions', '');"
                               href="javascript:void(0);"><?php _e( 'Help', 'gravityforms' ) ?></a>
                        </p>
                    </div>

                    <div id="sms_verify_code_type_manual_div">
                        <textarea id="sms_verify_code_type_manual"
                                  style="text-align:left !important; direction:ltr !important;"
                                  class="fieldwidth-1 fieldheight-1"
                                  onkeyup="SetFieldProperty('sms_verify_code_type_manual', this.value);"></textarea>
                        <span
                                class="description"><?php _e( 'Please separate the codes using commas.', 'GF_SMS' ) ?></span>
                    </div>
                </div>

                <div id="sms_verify_code_msg_body_div">
                    <br/>
                    <label for="sms_verify_code_msg_body">
						<?php _e( "The SMS text", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_code_msg_body" ); ?>
                    </label>
                    <textarea id="sms_verify_code_msg_body" class="fieldwidth-1"
                              onkeyup="SetFieldProperty('sms_verify_code_msg_body', this.value);"></textarea>
                    <span class="description"><?php _e( 'Verification Code', 'GF_SMS' ) ?> = <code>%code%</code></span>
                </div>

                <div class="sms_verify_try_num_div">
                    <br/>
                    <label for="sms_verify_try_num">
						<?php _e( "Maximum number of retries allowed", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_try_num" ); ?>
                    </label>
                    <input type="text" size="35" id="sms_verify_try_num"
                           onkeyup="SetFieldProperty('sms_verify_try_num', this.value);"/>
                </div>

                <div class="sms_verify_sent_num_div">
                    <br/>
                    <label for="sms_verify_sent_num">
						<?php _e( "Maximum number of resending the code", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_sent_num" ); ?>
                    </label>
                    <input type="text" size="35" id="sms_verify_sent_num"
                           onkeyup="SetFieldProperty('sms_verify_sent_num', this.value);"/>
                </div>

                <div class="sms_verify_code_all_fields_div">
                    <br/>
                    <input type="checkbox" id="sms_verify_code_all_fields"
                           onclick="SetFieldProperty('sms_verify_code_all_fields', this.checked);"/>
                    <label for="sms_verify_code_all_fields" class="inline">
						<?php _e( "Hide from the {all_fields} merge tag", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_all_fields" ); ?>
                    </label>
                </div>

                <div id="sms_verify_code_white_list_div">
                    <br/>
                    <label for="sms_verify_code_white_list">
						<?php _e( "Exceptional mobile numbers", "GF_SMS" ); ?>
						<?php gform_tooltip( "sms_verify_code_white_list" ); ?>
                    </label>
                    <textarea id="sms_verify_code_white_list" style="text-align:left;direction:ltr !important;"
                              class="fieldwidth-1"
                              onkeyup="SetFieldProperty('sms_verify_code_white_list', this.value);"></textarea>
                    <span class="description"><?php _e( 'Separate the numbers using commas.', 'GF_SMS' ) ?></span>
                </div>

            </li>
			<?php
		}
	}

	public static function get_this_fields() {
		return array( 'mobile', 'country_code_dynamic' );
	}

	public static function rand_str( $type = 2 ) {
		$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$numbers  = $type == 1 ? '0123456789' : '';
		$rand     = str_split( str_shuffle( $alphabet . $numbers ) );

		return $rand[ rand( 0, count( $rand ) - 1 ) ];
	}

	public static function rand_mask( $mask ) {

		if ( empty( $mask ) ) {
			return rand( 10000, 99999 );
		}
		$all_str = str_split( $mask );
		$code    = '';
		foreach ( (array) $all_str as $str ) {
			if ( $str == '*' ) {
				$code .= self::rand_str( 1 );
			} elseif ( $str == 'a' ) {
				$code .= self::rand_str( 2 );
			} elseif ( $str == '9' ) {
				$code .= rand( 0, 9 );
			} else {
				$code .= $str;
			}
		}

		return $code;
	}

	public static function country_code( $field ) {
		$field     = (array) $field;
		$code_type = rgar( $field, "sms_verify_country_code_radio" );
		if ( $code_type == 'dynamic' ) {
			$code = rgar( $field, "field_sms_verify_country_code_dynamic" );
			$code = str_replace( '.', '_', $code );
			$code = "input_{$code}";
			$code = ! rgempty( $code ) ? sanitize_text_field( rgpost( $code ) ) : '';
		} else {
			$code = rgar( $field, "sms_verify_country_code_static" );
		}

		return $code;
	}

	public static function get_mobile( $field, $change = true ) {
		$field  = (array) $field;
		$mobile = rgar( $field, "field_sms_verify_mobile" );
		$mobile = str_replace( '.', '_', $mobile );
		$mobile = "input_{$mobile}";
		$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';
		if ( $change && ! empty( $mobile ) ) {
			$mobile = GFHANNANSMS_Form_Send::change_mobile_separately( $mobile, self::country_code( $field ) );
		}

		return $mobile;
	}

	public static function white_list( $field ) {
		$field      = (array) $field;
		$numbers    = rgar( $field, "sms_verify_code_white_list" );
		$white_list = GFHANNANSMS_Form_Send::change_mobile( $numbers, self::country_code( $field ) );

		return ! empty( $white_list ) ? explode( ',', $white_list ) : array();
	}

	public static function submit_button( $button, $form ) {
		unset( $form['button']['text'] );
		$text = apply_filters( 'sms_verification_button', __( 'Verify Mobile Number', 'GF_SMS' ), $button, $form );
		if ( is_callable( array( 'GFFormDisplay', 'get_form_button' ) ) ) {
			return GFFormDisplay::get_form_button( $form['id'], "gform_submit_button_{$form['id']}", $form['button'], $text, 'gform_button', $text, 0 );
		} else {
			return self::get_form_button( $form['id'], "gform_submit_button_{$form['id']}", $form['button'], $text, 'gform_button', $text, 0 );
		}
	}

	public static function next_button( $button, $form ) {
		unset( $form['button']['text'] );
		$text  = apply_filters( 'sms_verification_button', __( 'Verify Mobile Number', 'GF_SMS' ), $button, $form );
		$field = GFCommon::get_fields_by_type( $form, array( 'page' ) );
		if ( is_callable( array( 'GFFormDisplay', 'get_form_button' ) ) ) {
			return GFFormDisplay::get_form_button( $form['id'], "gform_next_button_{$form['id']}_{$field->id}", $field->nextButton, $text, 'gform_next_button', $text, $field->pageNumber );
		} else {
			return self::get_form_button( $form['id'], "gform_next_button_{$form['id']}_{$field->id}", $field->nextButton, $text, 'gform_next_button', $text, $field->pageNumber );
		}
	}

	public static function change_message( $message, $form ) {
		return "<div class='validation_error'>" . esc_html__( 'In order to continue, you must verify your mobile number.', 'GF_SMS' ) . '</div>';
	}

	public static function all_fields( $value, $merge_tag, $modifier, $field ) {
		if ( $merge_tag == 'all_fields' && $field->type == 'sms_verification' ) {
			if ( rgar( $field, "sms_verify_code_all_fields" ) ) {
				return false;
			}
		}

		return $value;
	}

	public static function get_form_button( $form_id, $button_input_id, $button, $default_text, $class, $alt, $target_page_number, $onclick = '' ) {

		$tabindex = GFCommon::get_tabindex();

		$input_type = 'submit';

		if ( ! empty( $target_page_number ) ) {
			$onclick    = "onclick='jQuery(\"#gform_target_page_number_{$form_id}\").val(\"{$target_page_number}\"); {$onclick} jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]); '";
			$input_type = 'button';
		} else {
			// prevent multiple form submissions when button is pressed multiple times
			if ( GFFormsModel::is_html5_enabled() ) {
				$set_submitting = "if( !jQuery(\"#gform_{$form_id}\")[0].checkValidity || jQuery(\"#gform_{$form_id}\")[0].checkValidity()){window[\"gf_submitting_{$form_id}\"]=true;}";
			} else {
				$set_submitting = "window[\"gf_submitting_{$form_id}\"]=true;";
			}

			$onclick_submit = $button['type'] == 'link' ? "jQuery(\"#gform_{$form_id}\").trigger(\"submit\",[true]);" : '';

			$onclick = "onclick='if(window[\"gf_submitting_{$form_id}\"]){return false;}  {$set_submitting} {$onclick} {$onclick_submit}'";
		}

		if ( rgar( $button, 'type' ) == 'text' || rgar( $button, 'type' ) == 'link' || empty( $button['imageUrl'] ) ) {
			$button_text = ! empty( $button['text'] ) ? $button['text'] : $default_text;
			if ( rgar( $button, 'type' ) == 'link' ) {
				$button_input = "<a href='javascript:void(0);' id='{$button_input_id}_link' class='{$class}' {$tabindex} {$onclick}>{$button_text}</a>";
			} else {
				$class        .= ' button';
				$button_input = "<input type='{$input_type}' id='{$button_input_id}' class='{$class}' value='" . esc_attr( $button_text ) . "' {$tabindex} {$onclick} />";
			}
		} else {
			$imageUrl     = $button['imageUrl'];
			$class        .= ' gform_image_button';
			$button_input = "<input type='image' src='{$imageUrl}' id='{$button_input_id}' class='{$class}' alt='{$alt}' {$tabindex} {$onclick} />";
		}

		return $button_input;
	}

}