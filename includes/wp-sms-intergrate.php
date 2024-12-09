<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP SMS <https://wordpress.org/plugins/wp-sms/>
 */
class GFHANNANSMS_Pro_WP_SMS {

	public static function construct() {

		add_filter( 'gform_add_field_buttons', array( __CLASS__, 'button' ), 9999 );

		if ( ! defined( 'WP_SMS_VERSION' ) ) {
			add_filter( 'gform_admin_pre_render', array( __CLASS__, 'can_add' ), 10, 4 );

			return;
		}

		if ( is_admin() ) {
			add_filter( 'gform_field_type_title', array( __CLASS__, 'title' ), 10, 2 );
			add_action( 'gform_editor_js_set_default_values', array( __CLASS__, 'default_label' ) );
			add_action( 'gform_editor_js', array( __CLASS__, 'js' ) );
			add_action( 'gform_field_standard_settings', array( __CLASS__, 'standard_settings' ), 10, 2 );
			add_filter( 'gform_field_content', array( __CLASS__, 'content' ), 10, 5 );
			add_filter( 'gform_entries_field_value', array( __CLASS__, 'entries_value' ), 10, 4 );
			add_filter( 'gform_tooltips', array( __CLASS__, 'tooltips' ) );
		}

		add_filter( 'gform_field_validation', array( __CLASS__, 'validation' ), 10, 4 );
		add_filter( 'gform_entry_post_save', array( __CLASS__, 'process' ), 10, 2 );
		add_filter( 'gform_merge_tag_filter', array( __CLASS__, 'merge_tag' ), 10, 4 );
		add_action( 'gform_field_input', array( __CLASS__, 'input' ), 10, 5 );
		add_action( 'gform_field_css_class', array( __CLASS__, 'classes' ), 10, 3 );
	}

	public static function can_add( $form ) {
		echo GFCommon::is_form_editor() ? "
		<script type='text/javascript'>
			gform.addFilter('gform_form_editor_can_field_be_added', function (canFieldBeAdded, type) {
				 if (type == 'sms_subscribtion') {
					alert('" . __( 'The WP SMS plugin is not installed.', 'GF_SMS' ) . "');
					return false;
				}
				return canFieldBeAdded;
			});
        </script>" : '';

		return $form;
	}

	public static function button( $field_groups ) {
		foreach ( $field_groups as &$group ) {
			if ( $group["name"] == "gravity_sms_fields" ) {
				$group["fields"][] = array(
					"class"     => "button",
					"value"     => __( 'WP SMS', 'GF_SMS' ),
					"data-type" => "sms_subscribtion",
					//"onclick" => "StartAddField('sms_subscribtion');"//deprecated
				);
				break;
			}
		}

		return $field_groups;
	}


	public static function title( $title, $field_type ) {
		if ( $field_type == 'sms_subscribtion' ) {
			return $title = __( 'Subscribers Group', 'GF_SMS' );
		} else {
			return $title;
		}
	}

	public static function default_label() { ?>
        case "sms_subscribtion" :
        field.label = '<?php _e( 'Subscribers Group', 'GF_SMS' ); ?>';
        break;
		<?php
	}

	public static function classes( $classes, $field, $form ) {
		if ( ! empty( $field["type"] ) && $field["type"] == "sms_subscribtion" ) {
			$classes .= " gform_sms_subscribtion";
		}

		return $classes;
	}

	public static function tooltips( $tooltips ) {
		$tooltips["wp_sms_subs"]                = __( "<h6>Subscription operations</h6>Please select the intended operation.", "GF_SMS" );
		$tooltips["field_wp_sms_name"]          = __( "<h6>Name filed</h6>Please choose the field equivalent to the Name field in the WP SMS plugin.", "GF_SMS" );
		$tooltips["field_wp_sms_mobile"]        = __( "<h6>Mobile filed</h6>Please choose the field equivalent to the Mobile field in the WP SMS plugin.", "GF_SMS" );
		$tooltips["wp_sms_group_select"]        = __( "You can choose whether users may optionally select a Subscribers Group or be forced to subscribe to the group you determine.", "GF_SMS" );
		$tooltips["wp_sms_group_type"]          = __( "Displaying the Subscribers Group is possible in either a drop-down or a radio-button manner.", "GF_SMS" );
		$tooltips["wp_sms_group_forced"]        = __( "<h6>Mandatory group</h6>If you would like the user to subscribe to a certain SMS Subscribers Group, please select the desired group.", "GF_SMS" );
		$tooltips["wp_sms_welcome_msg"]         = __( "<h6>Welcome message</h6>Check the checkbox if you wish to deactivate sending a Welcome message after subscription activation in the WP SMS settings.", "GF_SMS" );
		$tooltips["wp_sms_repeat_error"]        = __( "If the entered mobile number has already been registered in the WP SMS newsletter, prevent filling out the form and show the laid message.", "GF_SMS" );
		$tooltips["wp_sms_country_code_select"] = __( "<h6>Country code</h6>You can change the default country code. but If entered mobile phone number was international format, this country code will be effectless.", "GF_SMS" );

		return $tooltips;
	}


	public static function js() {
		$settings = GFHANNANSMS_Pro::get_option();
		?>
        <script type='text/javascript'>
            if (typeof fieldSettings != 'undefined') {
                fieldSettings["sms_subscribtion"] = ".enable_enhanced_ui_setting , .size_setting, .label_placement_setting, .prepopulate_field_setting,.error_message_setting, .conditional_logic_field_setting, .label_setting, .admin_label_setting,.rules_setting, .visibility_setting, .description_setting, .css_class_setting, .wp_sms_subscribtion_setting";
            }

            function wp_sms_hannanstd(type) {
                if (type == 'unsubscribe') {
                    jQuery("#wp_sms_subs_unsubs").prop("checked", true);
                    jQuery(".field_wp_sms_name").hide("slow");
                    jQuery(".wp_sms_group_select_div").hide("slow");
                    jQuery(".wp_sms_group_type_div").hide("slow");
                    jQuery(".wp_sms_group_forced_div").hide("slow");
                    jQuery(".wp_sms_welcome_msg_div").hide("slow");
                    jQuery(".wp_sms_repeat_error_div").hide("slow");
                    jQuery(".wp_sms_repeat_mgs_div").hide("slow");
                    jQuery(".sms_country_code").hide("slow");
                    jQuery("#ginput_container_unsubscribe_" + field.id).show("slow");
                    jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                    jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                    jQuery("#ginput_container_force_" + field.id).hide("slow");
                }
                else {
                    jQuery("#wp_sms_subs_subs").prop("checked", true);
                    jQuery("#ginput_container_unsubscribe_" + field.id).hide("slow");
                    jQuery(".field_wp_sms_name").show("slow");
                    jQuery(".wp_sms_group_select_div").show("slow");
                    jQuery(".wp_sms_welcome_msg_div").show("slow");
                    jQuery(".sms_country_code").show("slow");
                    jQuery(".wp_sms_repeat_error_div").show("slow");

                    //#2
                    if (field.wp_sms_group_type == 'select') {
                        jQuery("#wp_sms_group_type_select").prop("checked", true);
                        jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                        jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                    }
                    else {
                        jQuery("#wp_sms_group_type_radio").prop("checked", true);
                        jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                        jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                    }
                    jQuery('input[name="wp_sms_group_type"]').on("click", function () {
                        if (jQuery('input[name="wp_sms_group_type"]:checked').val() == 'select') {
                            jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                            jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                        }
                        else {
                            jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                            jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                        }
                    });

                    //#3
                    jQuery('#wp_sms_group_select').val(field.wp_sms_group_select == undefined ? "user" : field.wp_sms_group_select);
                    if (field.wp_sms_group_select != 'force') {
                        jQuery(".wp_sms_group_type_div").show("slow");
                        jQuery(".wp_sms_group_forced_div").hide("slow");
                        jQuery("#ginput_container_force_" + field.id).hide("slow");
                        if (field.wp_sms_group_type == 'select')
                            jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                        else
                            jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                    }
                    else {
                        jQuery(".wp_sms_group_type_div").hide("slow");
                        jQuery(".wp_sms_group_forced_div").show("slow");
                        jQuery("#ginput_container_force_" + field.id).show("slow");
                        jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                        jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                    }
                    jQuery('#wp_sms_group_select').change(function () {
                        if (jQuery(this).val() == 'user') {
                            jQuery(".wp_sms_group_type_div").show("slow");
                            jQuery(".wp_sms_group_forced_div").hide("slow");
                            jQuery("#ginput_container_force_" + field.id).hide("slow");
                            if (jQuery('input[name="wp_sms_group_type"]:checked').val() == 'select')
                                jQuery("#ginput_container_sms_select_" + field.id).show("slow");
                            else
                                jQuery("#ginput_container_sms_radio_" + field.id).show("slow");
                        }
                        else {
                            jQuery(".wp_sms_group_type_div").hide("slow");
                            jQuery(".wp_sms_group_forced_div").show("slow");
                            jQuery("#ginput_container_force_" + field.id).show("slow");
                            jQuery("#ginput_container_sms_radio_" + field.id).hide("slow");
                            jQuery("#ginput_container_sms_select_" + field.id).hide("slow");
                        }
                    }).change();

                    //#4
                    jQuery('#wp_sms_group_forced').val(field.wp_sms_group_forced == undefined ? "" : field.wp_sms_group_forced);

                    //#5
					<?php if( get_option( 'wp_subscribes_send_sms' )) { ?>
                    jQuery("#wp_sms_welcome_msg").attr("checked", field["wp_sms_welcome_msg"] == true);
					<?php } ?>

                    //#6
                    jQuery("#wp_sms_repeat_error").attr("checked", field["wp_sms_repeat_error"] == true);
                    if (field.wp_sms_repeat_error == true) {
                        jQuery(".wp_sms_repeat_mgs_div").show("slow");
                    }
                    else {
                        jQuery(".wp_sms_repeat_mgs_div").hide("slow");
                    }
                    jQuery("#wp_sms_repeat_error").change(function () {
                        if (jQuery('#wp_sms_repeat_error:checked').val())
                            jQuery(".wp_sms_repeat_mgs_div").show("slow");
                        else
                            jQuery(".wp_sms_repeat_mgs_div").hide("slow");
                    });

                    //#7
                    jQuery("#wp_sms_repeat_mgs").val(field["wp_sms_repeat_mgs"]);

                }

                //#8
                if (field.wp_sms_country_code_radio == 'dynamic') {
                    jQuery("#wp_sms_country_code_radio_dynamic").prop("checked", true);
                    jQuery("#wp_sms_country_code_static_div").hide("slow");
                    jQuery("#field_wp_sms_country_code_dynamic_div").show("slow");
                }
                else {
                    jQuery("#wp_sms_country_code_radio_static").prop("checked", true);
                    jQuery("#wp_sms_country_code_static_div").show("slow");
                    jQuery("#field_wp_sms_country_code_dynamic_div").hide("slow");
                }
                jQuery('input[name="wp_sms_country_code_radio"]').on("click", function () {
                    if (jQuery('input[name="wp_sms_country_code_radio"]:checked').val() == 'dynamic') {
                        jQuery("#wp_sms_country_code_static_div").hide("slow");
                        jQuery("#field_wp_sms_country_code_dynamic_div").show("slow");
                    }
                    else {
                        jQuery("#wp_sms_country_code_static_div").show("slow");
                        jQuery("#field_wp_sms_country_code_dynamic_div").hide("slow");
                    }
                });
                jQuery('#wp_sms_country_code_static').val(field.wp_sms_country_code_static == undefined ? <?php echo ! empty( $settings ) && ! empty( $settings["code"] ) ? $settings["code"] : "''"; ?> : field.wp_sms_country_code_static);
                jQuery("#field_wp_sms_country_code_dynamic").val(field["field_wp_sms_country_code_dynamic"]);

            }

            jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
                wp_sms_hannanstd(field.wp_sms_subs);
                jQuery('input[name="wp_sms_subs"]').on("click", function () {
                    wp_sms_hannanstd(jQuery('input[name="wp_sms_subs"]:checked').val());
                });
            });

            //get field online
            function gf_wp_sms_populate_select() {
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
                jQuery("select[id^=field_wp_sms_]").html(options.join(""));
            }

            jQuery(document).bind("gform_field_deleted", gf_wp_sms_populate_select);
            jQuery(document).bind("gform_field_added", gf_wp_sms_populate_select);
            gf_wp_sms_populate_select();
            jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
                var fields = [ <?php foreach ( self::get_this_fields() as $key => $value ) {
					echo "'{$key}',";
				} ?> ];
                fields.map(function (fname) {
                    jQuery("#field_wp_sms_" + fname).attr("value", field["field_wp_sms_" + fname]);
                });
            });
        </script>
		<?php
	}


	public static function standard_settings( $position, $form_id ) {
		if ( $position == 25 ) { ?>

            <li class="wp_sms_subscribtion_setting field_setting">

                <label>
					<?php _e( "Subscription operations", "GF_SMS" ); ?>
					<?php gform_tooltip( "wp_sms_subs" ); ?>
                </label>
                <div>
                    <input type="radio" name="wp_sms_subs" id="wp_sms_subs_subs" size="10" value="subscribe"
                           onclick="SetFieldProperty('wp_sms_subs', this.value);"/>
                    <label for="wp_sms_subs_subs" class="inline">
						<?php _e( 'Subscription', 'GF_SMS' ); ?>
                    </label>

                    <input type="radio" name="wp_sms_subs" id="wp_sms_subs_unsubs" size="10" value="unsubscribe"
                           onclick="SetFieldProperty('wp_sms_subs', this.value);"/>
                    <label for="wp_sms_subs_unsubs" class="inline">
						<?php _e( 'Unsubscribe', 'GF_SMS' ); ?>
                    </label>
                </div>

				<?php foreach ( self::get_this_fields() as $key => $value ) {
					if ( $key != 'country_code_dynamic' ) { ?>
                        <div class="field_wp_sms_<?php echo $key ?>">
                            <br/>
                            <label for="field_wp_sms_<?php echo $key ?>">
								<?php echo $value ?>
								<?php gform_tooltip( 'field_wp_sms_' . $key ) ?>
                            </label>
                            <select id="field_wp_sms_<?php echo $key ?>"
                                    onchange="SetFieldProperty('field_wp_sms_<?php echo $key ?>', this.value);"></select>
                        </div>
					<?php } ?>
				<?php } ?>

                <div class="sms_country_code">
                    <br/>
                    <label>
						<?php _e( "Country code", "GF_SMS" ); ?>
						<?php gform_tooltip( "wp_sms_country_code_select" ); ?>
                    </label>
                    <div>
                        <input type="radio" name="wp_sms_country_code_radio" id="wp_sms_country_code_radio_static"
                               size="10" value="static"
                               onclick="SetFieldProperty('wp_sms_country_code_radio', this.value);"/>
                        <label for="wp_sms_country_code_radio_static" class="inline">
							<?php _e( 'Static', 'GF_SMS' ); ?>
                        </label>

                        <input type="radio" name="wp_sms_country_code_radio" id="wp_sms_country_code_radio_dynamic"
                               size="10" value="dynamic"
                               onclick="SetFieldProperty('wp_sms_country_code_radio', this.value);"/>
                        <label for="wp_sms_country_code_radio_dynamic" class="inline">
							<?php _e( 'Dynamic', 'GF_SMS' ); ?>
                        </label>
                    </div>

                    <div id="wp_sms_country_code_static_div">
                        <input id="wp_sms_country_code_static" name="wp_sms_country_code_static" type="text" size="17"
                               style="text-align:left;direction:ltr !important"
                               onkeyup="SetFieldProperty('wp_sms_country_code_static', this.value);">
                    </div>

                    <div id="field_wp_sms_country_code_dynamic_div">
                        <select id="field_wp_sms_country_code_dynamic"
                                onchange="SetFieldProperty('field_wp_sms_country_code_dynamic', this.value);"></select>
                    </div>
                </div>


                <br class="field_wp_sms_name"/>

                <div class="wp_sms_group_select_div">
                    <label for="wp_sms_group_select" class="inline">
						<?php _e( "How to subscribe to a Subscribers Group", "GF_SMS" ); ?>
						<?php gform_tooltip( "wp_sms_group_select" ); ?>
                    </label><br/>

                    <select id="wp_sms_group_select" onchange="SetFieldProperty('wp_sms_group_select', this.value);">
                        <option value="user"><?php _e( 'Must be chosen by user', 'GF_SMS' ); ?></option>
                        <option
                                value="force"><?php _e( 'Force to subscribe to the determination group', 'GF_SMS' ); ?></option>
                    </select>
                </div>

                <br class="wp_sms_group_select_div"/>


                <div class="wp_sms_group_type_div">
                    <label for="wp_sms_group_type">
						<?php _e( "Display-type for Subscribers Group", "GF_SMS" ); ?>
						<?php gform_tooltip( 'wp_sms_group_type' ) ?>
                    </label>

                    <div>
                        <input type="radio" name="wp_sms_group_type" id="wp_sms_group_type_radio" size="10"
                               value="radio" onclick="SetFieldProperty('wp_sms_group_type', this.value);"/>
                        <label for="wp_sms_group_type_radio" class="inline">
							<?php _e( 'Radio Buttons', 'GF_SMS' ); ?>
                        </label>

                        <input type="radio" name="wp_sms_group_type" id="wp_sms_group_type_select" size="10"
                               value="select" onclick="SetFieldProperty('wp_sms_group_type', this.value);"/>
                        <label for="wp_sms_group_type_select" class="inline">
							<?php _e( 'Drop Down', 'GF_SMS' ); ?>
                        </label>
                    </div>
                </div>

                <br class="wp_sms_group_type_div"/>


                <div class="wp_sms_group_forced_div">
                    <label for="wp_sms_group_forced">
						<?php _e( "Please select the intended group", "GF_SMS" ); ?>
						<?php gform_tooltip( 'wp_sms_group_forced' ) ?>
                    </label>

                    <select id="wp_sms_group_forced" onchange="SetFieldProperty('wp_sms_group_forced', this.value);">
						<?php
						global $wpdb, $table_prefix;
						$get_group_result = $wpdb->get_results( "SELECT * FROM {$table_prefix}sms_subscribes_group" );
						foreach ( (array) $get_group_result as $items ) { ?>
                            <option value="<?php echo $items->ID ?>"><?php echo $items->name ?></option>
						<?php } ?>
                    </select>
                </div>

                <br class="wp_sms_group_forced_div"/>

                <div class="wp_sms_repeat_error_div">
                    <input type="checkbox" id="wp_sms_repeat_error"
                           onclick="SetFieldProperty('wp_sms_repeat_error', this.checked);"/>
                    <label for="wp_sms_repeat_error" class="inline">
						<?php _e( "The same-number message", "GF_SMS" ); ?>
						<?php gform_tooltip( "wp_sms_repeat_error" ); ?>
                    </label>
                </div>

                <div class="wp_sms_repeat_mgs_div">
                    <input type="text" id="wp_sms_repeat_mgs" class="fieldwidth-1"
                           onkeyup="SetFieldProperty('wp_sms_repeat_mgs', this.value);"/>
                </div>


				<?php if ( get_option( 'wp_subscribes_send_sms' ) ) { ?>

                    <br class="wp_sms_welcome_msg_div"/>

                    <div class="wp_sms_welcome_msg_div">
                        <input type="checkbox" id="wp_sms_welcome_msg"
                               onclick="SetFieldProperty('wp_sms_welcome_msg', this.checked);"/>
                        <label for="wp_sms_welcome_msg" class="inline">
							<?php _e( "Send welcome message", "GF_SMS" ); ?>
							<?php gform_tooltip( "wp_sms_welcome_msg" ); ?>
                        </label>
                    </div>
				<?php } ?>

            </li>
			<?php
		}
	}

	public static function get_this_fields() {
		return array(
			"name"                 => __( 'Name field', 'GF_SMS' ),
			"mobile"               => __( 'Mobile number field', 'GF_SMS' ),
			"country_code_dynamic" => '',
		);
	}

	public static function input( $input, $field, $value, $entry_id, $form_id ) {

		if ( $field["type"] == "sms_subscribtion" ) {

			$is_entry_detail = GFCommon::is_entry_detail();
			$is_form_editor  = GFCommon::is_form_editor();

			global $wpdb, $table_prefix;
			$get_group_result = $wpdb->get_results( "SELECT * FROM {$table_prefix}sms_subscribes_group" );

			$field_id = $field["id"];
			$form_id  = empty( $form_id ) ? rgget( "id" ) : $form_id;

			$disabled_text = $is_form_editor ? "disabled='disabled'" : '';

			$size         = rgar( $field, "size" );
			$class_suffix = $is_entry_detail ? '_admin' : '';
			$class        = $size . $class_suffix;

			$html5_attributes = '';

			$input_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$field_id" : 'input_' . $form_id . "_$field_id";

			$tabindex = GFCommon::get_tabindex();

			if ( ! is_admin() && ( RGFormsModel::get_input_type( $field ) == 'adminonly_hidden' ) ) {
				return '';
			}

			if ( ! is_admin() && ( rgar( $field, "wp_sms_subs" ) == 'unsubscribe' || rgar( $field, "wp_sms_group_select" ) == 'force' ) ) {
				$hidden = '';
				if ( ! empty( $field['conditionalLogic'] ) ) {
					$hidden .= '<input name="input_' . $field_id . '" id="' . $input_id . '" type="hidden" value="true" />';
				}

				return apply_filters( 'wp_sms_display_none', true ) ? $hidden . '<style type="text/css">#field_' . $form_id . '_' . $field_id . '{display:none !important;}</style>' : '';
			}

			if ( $is_entry_detail && rgar( $field, "wp_sms_subs" ) == 'unsubscribe' ) {
				return '<p>' . __( 'It’s not possible to edit WP SMS field on unsubscribe mode.', 'GF_SMS' ) . '</p>';
			}


			$type = rgar( $field, "wp_sms_group_type" ) ? rgar( $field, "wp_sms_group_type" ) : 'radio';

			$desired_form_id = ( $is_form_editor || ! is_admin() ) ? $form_id . '_' : '';

			$wp_sms_select = $wp_sms_radio = '';

			if ( $is_form_editor || $type == 'select' ) {

				if ( ! is_admin() && $field->enableEnhancedUI ) {

					$form          = RGFormsModel::get_form_meta( $form_id );
					$chosen_fields = array();
					foreach ( $form['fields'] as $field_val ) {
						$input_type = GFFormsModel::get_input_type( $field_val );
						if ( $field_val->enableEnhancedUI && in_array( $input_type, array(
								'select',
								'multiselect',
								'sms_subscribtion'
							) ) ) {
							$chosen_fields[] = "#input_{$form['id']}_{$field_val->id}";
						}
					}
					$chosen_script = "gformInitChosenFields('" . implode( ',', $chosen_fields ) . "','" . esc_attr( gf_apply_filters( 'gform_dropdown_no_results_text', $form['id'], __( 'No results matched', 'gravityforms' ), $form['id'] ) ) . "');";
					GFFormDisplay::add_init_script( $form_id, 'chosen', GFFormDisplay::ON_PAGE_RENDER, $chosen_script );
					GFFormDisplay::add_init_script( $form_id, 'chosen', GFFormDisplay::ON_CONDITIONAL_LOGIC, $chosen_script );


					if ( ! wp_script_is( 'gform_gravityforms', 'enqueued' ) ) {
						wp_enqueue_script( 'gform_gravityforms' );
					}

					if ( ! wp_script_is( 'chosen' ) ) {
						wp_enqueue_script( 'gform_chosen' );
					}

					if ( wp_script_is( 'chosen', 'registered' ) ) {
						wp_enqueue_script( 'chosen' );
					} else {
						wp_enqueue_script( 'gform_chosen' );
					}

					$scripts = array();

					if ( ! wp_script_is( 'gform_chosen' ) && ! wp_script_is( 'chosen' ) ) {
						if ( wp_script_is( 'chosen', 'registered' ) ) {
							$scripts[] = 'chosen';
						} else {
							$scripts[] = 'gform_chosen';
						}
					}

					if ( ! empty( $scripts ) ) {
						foreach ( (array) $scripts as $script ) {
							wp_enqueue_script( $script );
						}
						wp_print_scripts( $scripts );
					}

				}

				$wp_sms_select .= '<div class="ginput_container ginput_container_select ginput_container_sms_select" id="ginput_container_sms_select_' . $field_id . '">';
				$wp_sms_select .= '<select name="input_' . $field_id . '" id="input_' . $desired_form_id . $field_id . '" class="sms_subscribtion ' . esc_attr( $class ) . '" ' . $tabindex . ' ' . $html5_attributes . ' ' . $disabled_text . '/>';
				if ( empty( $get_group_result ) && $is_form_editor ) {
					$wp_sms_select .= '<div class="gf-html-container ginput_container_unsubscribe">';
					$wp_sms_select .= '<span>';
					$wp_sms_select .= __( "First you need to create some Subscription Groups in the WP SMS plugin.", "GF_SMS" );
					$wp_sms_select .= '</span>';
					$wp_sms_select .= '</div>';
				} else {
					foreach ( (array) $get_group_result as $items ) {
						$selected      = $items->ID == $value ? 'selected="selected"' : '';
						$wp_sms_select .= '<option value="' . $items->ID . '" ' . $selected . '>' . $items->name . '</option>';
					}
				}
				$wp_sms_select .= '</select>';
				$wp_sms_select .= '</div>';
			}

			if ( $is_form_editor || $type != 'select' ) {

				$choice_id    = 0;
				$wp_sms_radio .= '<div class="ginput_container ginput_container_radio ginput_container_sms_radio" id="ginput_container_sms_radio_' . $field_id . '">';
				$wp_sms_radio .= '<ul class="gfield_radio" id="input_' . $desired_form_id . $field_id . '">';

				if ( empty( $get_group_result ) && $is_form_editor ) {
					$wp_sms_radio .= '<div class="gf-html-container ginput_container_unsubscribe">';
					$wp_sms_radio .= '<span>';
					$wp_sms_radio .= __( "First you need to create some Subscription Groups in the WP SMS plugin.", "GF_SMS" );
					$wp_sms_radio .= '</span>';
					$wp_sms_radio .= '</div>';
				} else {
					foreach ( (array) $get_group_result as $items ) {
						$checked      = $items->ID == $value ? 'checked="checked"' : '';
						$wp_sms_radio .= '
						<li class="gchoice_' . $desired_form_id . $field_id . '_' . $choice_id . '">
							<input name="input_' . $field_id . '" type="radio" value="' . $items->ID . '" id="choice_' . $desired_form_id . $field_id . '_' . $choice_id . '" ' . $checked . ' ' . $tabindex . ' ' . $disabled_text . ' />
							<label id="label_' . $desired_form_id . $field_id . '_' . $choice_id . '" for="choice_' . $desired_form_id . $field_id . '_' . $choice_id . '">' . $items->name . '</label>
						</li>';
						$choice_id ++;
					}
				}
				$wp_sms_radio .= '</ul>';
				$wp_sms_radio .= '</div>';
			}


			if ( $is_form_editor ) {

				$wp_sms_input_unsubscribe = '<div class="gf-html-container ginput_container_unsubscribe" id="ginput_container_unsubscribe_' . $field_id . '">';
				$wp_sms_input_unsubscribe .= '<span>';
				$wp_sms_input_unsubscribe .= __( 'When the “unsubscribe” is activated, nothing will be shown for this field in the form output but the unsubscription will be done anyhow.', 'GF_SMS' );
				$wp_sms_input_unsubscribe .= '</span>';
				$wp_sms_input_unsubscribe .= '</div>';

				$wp_sms_input_force = '<div class="gf-html-container ginput_container_unsubscribe" id="ginput_container_force_' . $field_id . '">';
				$wp_sms_input_force .= '<span>';
				$wp_sms_input_force .= __( 'When the “mandatory group” is activated, nothing will be shown for this field in the form output but the subscription will be done anyhow.', 'GF_SMS' );
				$wp_sms_input_force .= '</span>';
				$wp_sms_input_force .= '</div>';

				$style = '<style type="text/css">';
				$style .= '#ginput_container_sms_select_' . $field_id . ' option{display:none;}';

				$hide_radio       = '#ginput_container_sms_radio_' . $field_id . '{display:none;}';
				$hide_select      = '#ginput_container_sms_select_' . $field_id . '{display:none;}';
				$hide_unsubscribe = '#ginput_container_unsubscribe_' . $field_id . '{display:none;}';
				$hide_force       = '#ginput_container_force_' . $field_id . '{display:none;}';

				if ( rgar( $field, 'wp_sms_subs' ) == 'unsubscribe' ) {
					$style .= $hide_radio;
					$style .= $hide_select;
					$style .= $hide_force;
				} else {
					$style .= $hide_unsubscribe;
					if ( rgar( $field, 'wp_sms_group_select' ) != 'force' ) {
						$style .= $hide_force;
						if ( $type == 'select' ) {
							$style .= $hide_radio;
						} else {
							$style .= $hide_select;
						}
					} else {
						$style .= $hide_radio;
						$style .= $hide_select;
					}
				}
				$style .= '</style>';

				return $wp_sms_input_unsubscribe . $wp_sms_select . $wp_sms_radio . $wp_sms_input_force . $style;
			}

			return $wp_sms_input = ( $type == 'select' ? $wp_sms_select : $wp_sms_radio );
		}

		return $input;
	}


	public static function wp_sms_group_name_by_id( $value ) {

		if ( ! is_numeric( $value ) ) {
			return $value;
		}

		global $wpdb, $table_prefix;
		$result = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$table_prefix}sms_subscribes_group WHERE ID = %d", $value ) );

		if ( ! empty( $result ) ) {
			$value = $result;
		}

		return $value;
	}


	public static function content( $content, $field, $value, $entry_id, $form_id ) {

		$mode = ! rgpost( 'screen_mode' ) ? 'view' : sanitize_text_field( rgpost( 'screen_mode' ) );

		if ( GFCommon::is_entry_detail() && $mode == 'view' && $field["type"] == "sms_subscribtion" ) {
			$content = '<tr>
					<td colspan="2" class="entry-view-field-name">' . esc_html( GFCommon::get_label( $field ) ) . '</td>
				</tr>
				<tr>
					<td colspan="2" class="entry-view-field-value">' . self::wp_sms_group_name_by_id( $value ) . '</td>
                </tr>';
		}

		return $content;
	}


	public static function entries_value( $value, $form_id, $field_id, $entry ) {

		$form  = RGFormsModel::get_form_meta( $form_id );
		$field = RGFormsModel::get_field( $form, $field_id );

		$field = (array) $field;//

		if ( $field["type"] == "sms_subscribtion" ) {
			$value = self::wp_sms_group_name_by_id( $value );
		}

		return $value;
	}


	public static function merge_tag( $value, $merge_tag, $modifier, $field ) {

		if ( $field->type == 'sms_subscribtion' ) {
			$value = self::wp_sms_group_name_by_id( $value );
		}

		return $value;
	}

	public static function country_code( $field ) {

		$code_type = rgar( $field, 'wp_sms_country_code_radio' );
		if ( $code_type == 'dynamic' ) {
			$code = rgar( $field, 'field_wp_sms_country_code_dynamic' );
			$code = str_replace( '.', '_', $code );
			$code = "input_{$code}";
			$code = ! rgempty( $code ) ? sanitize_text_field( rgpost( $code ) ) : '';
		} else {
			$code = rgar( $field, 'wp_sms_country_code_static' );
		}

		return $code;
	}

	public static function validation( $result, $value, $form, $field ) {

		if ( $field["type"] == "sms_subscribtion" ) {
			$mobile_error = false;

			if ( rgar( $field, 'wp_sms_subs' ) != 'unsubscribe' && rgar( $field, 'wp_sms_repeat_error' ) ) {

				$mobile = rgar( $field, 'field_wp_sms_mobile' );
				$mobile = str_replace( '.', '_', $mobile );
				$mobile = "input_{$mobile}";
				$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';
				$mobile = GFHANNANSMS_Form_Send::change_mobile_separately( $mobile, self::country_code( $field ) );

				global $wpdb, $table_prefix;
				$mobile_exist = $wpdb->query( $wpdb->prepare( "SELECT * FROM {$table_prefix}sms_subscribes WHERE mobile = %s", $mobile ) );

				if ( $mobile_exist ) {
					$mobile_error       = true;
					$result["is_valid"] = false;
					$result["message"]  = rgar( $field, 'wp_sms_repeat_mgs' ) ? rgar( $field, 'wp_sms_repeat_mgs' ) : __( "The entered mobile number has previously been used in this form.", "GF_SMS" );
					add_filter( 'wp_sms_display_none', '__return_false' );
				}

			}

			if ( RGFormsModel::get_input_type( $field ) == 'adminonly_hidden' || rgar( $field, 'wp_sms_subs' ) == 'unsubscribe' || ( rgar( $field, 'wp_sms_group_select' ) == 'force' && ! $mobile_error ) ) {
				$result['is_valid'] = true;
			}

		}

		return $result;
	}

	public static function process( $entry, $form ) {

		$wp_sms_fileds = GFCommon::get_fields_by_type( $form, array( 'sms_subscribtion' ) );

		foreach ( (array) $wp_sms_fileds as $field ) {

			$field = (array) $field;

			if ( ! empty( $field['conditionalLogic'] ) && empty( $entry[ $field['id'] ] ) ) {
				break;
			}

			$type = rgar( $field, 'wp_sms_subs' ) ? rgar( $field, 'wp_sms_subs' ) : 'subscribe';

			$name = rgar( $field, 'field_wp_sms_name' );
			$name = str_replace( '.', '_', $name );
			$name = "input_{$name}";
			$name = ! rgempty( $name ) ? sanitize_text_field( rgpost( $name ) ) : '';

			$mobile = rgar( $field, 'field_wp_sms_mobile' );
			$mobile = str_replace( '.', '_', $mobile );
			$mobile = "input_{$mobile}";
			$mobile = ! rgempty( $mobile ) ? sanitize_text_field( rgpost( $mobile ) ) : '';
			$mobile = GFHANNANSMS_Form_Send::change_mobile_separately( $mobile, self::country_code( $field ) );

			if ( rgar( $field, 'wp_sms_group_select' ) == 'force' ) {
				$groups = rgar( $field, 'wp_sms_group_forced' );
			} else {
				$groups = str_replace( '.', '_', $field['id'] );
				$groups = "input_{$groups}";
				$groups = ! rgempty( $groups ) ? rgpost( $groups ) : '';
			}

			if ( is_array( $groups ) ) {
				$groups = array_map( 'sanitize_text_field', $groups );
			} else {
				$groups = sanitize_text_field( $groups );
			}

			if ( $type == 'subscribe' ) {
				$value = $entry[ $field['id'] ] = is_array( $groups ) ? implode( ',', $groups ) : $groups;
			} else {
				$value = $entry[ $field['id'] ] = '';
			}
			GFAPI::update_entry_field( $entry['id'], $field['id'], $value );


			$process = self::subscribtion( $name, $mobile, $groups, $type, rgar( $field, 'wp_sms_repeat_error' ) );

			if ( ! empty( $process['message'] ) ) {
				RGFormsModel::add_note( $entry["id"], 0, __( 'GF SMS - WP SMS', 'GF_SMS' ), $process['message'] );
			}

			if ( ! empty( $process['status'] ) && $process['status'] == 'success-1' ) {

				if ( get_option( 'wp_subscribes_send_sms' ) && rgar( $field, 'wp_sms_welcome_msg' ) ) {

					$string = get_option( 'wp_subscribes_text_send' );
					//$template_vars = array( 'subscribe_name' => $name, 'subscribe_mobile' => $mobile );
					$final_message = preg_replace( '/%(.*?)%/ime', "\$template_vars['$1']", $string );

					GFHANNANSMS_Form_Send::Send( $mobile, $final_message, $from = '', $form['id'], '', '' );

				}
			}

		}

		return $entry;
	}

	public static function subscribtion( $name, $mobile, $groups, $type, $no_repeat = false ) {

		if ( empty( $mobile ) ) {
			return array( 'status' => 'empty', 'message' => __( 'The mobile number value is empty.', 'GF_SMS' ) );
		}

		global $wpdb, $table_prefix;

		$mobile_exist = false;
		if ( $no_repeat || $type != 'subscribe' ) {
			$_mobile      = substr( $mobile, - 10 );
			$mobile_exist = $wpdb->get_results( $wpdb->prepare( "SELECT mobile FROM {$table_prefix}sms_subscribes WHERE mobile LIKE %s", '%' . $_mobile . '%' ), ARRAY_N );
		}

		if ( empty( $mobile_exist ) || $type != 'subscribe' ) {

			if ( $type == 'subscribe' ) {

				$groups = is_array( $groups ) ? $groups : array( $groups );

				foreach ( (array) $groups as $group ) {

					$insert = $wpdb->insert( "{$table_prefix}sms_subscribes",
						array(
							'date'     => date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
							'name'     => $name,
							'mobile'   => $mobile,
							'status'   => '1',
							'group_ID' => $group
						)
					);

					if ( $insert ) {
						return array(
							'status'  => 'success-1',
							'message' => __( 'Subscription to the SMS newsletter has been successfully done.', 'GF_SMS' )
						);
					} else {
						return array(
							'status'  => 'failed-1',
							'message' => __( 'Subscription to the SMS newsletter failed.', 'GF_SMS' )
						);
					}
				}

			} else if ( $type == 'unsubscribe' ) {

				if ( ! empty( $mobile_exist ) ) {

					$delete = 0;
					foreach ( $mobile_exist as $mobile ) {
						$mobile = (array) $mobile;
						$delete = $delete + $wpdb->delete( "{$table_prefix}sms_subscribes", array( 'mobile' => reset( $mobile ) ) );
					}

					if ( ! empty( $delete ) ) {
						return array(
							'status'  => 'success-2',
							'message' => __( 'Unsubscription to the SMS newsletter has been successfully done.', 'GF_SMS' )
						);
					} else {
						return array(
							'status'  => 'failed-2',
							'message' => __( 'Unsubscription to the SMS newsletter failed.', 'GF_SMS' )
						);
					}

				} else {
					return array(
						'status'  => 'not-sub',
						'message' => __( 'The entered number was not subscribed to the SMS newsletter and the unsubscription did not take place.', 'GF_SMS' )
					);
				}
			}

		} else {
			return array(
				'status'  => 'repeat',
				'message' => __( 'The entered mobile number has previously been used in the newsletter.', 'GF_SMS' )
			);
		}

	}
}