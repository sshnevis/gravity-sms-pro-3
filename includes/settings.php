<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'gform_tooltips', array( 'GFHANNANSMS_Pro_Settings', 'tooltips' ) );

class GFHANNANSMS_Pro_Settings {

	protected static function check_access( $required_permission ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		return GFCommon::current_user_can_any( $required_permission );
	}

	public static function tooltips( $tooltips ) {

		$tooltips["admin_default"] = __( "You can set several numbers. Separate with commas(,). for example : +16175551212,+16175551213. this numbers is default and you can change them later.", "GF_SMS" );
		$tooltips["show_credit"]   = __( "Activating this section is not recommended; since you must connect to the webservice provider every time you wish to check your credit’s status and this might cause the wordpress admin to reduce in speed.", "GF_SMS" );
		$tooltips["country_code"]  = __( "Your Mobile Country code. like : +1", "GF_SMS" );
		$tooltips["gf_sms_sender"] = __( "Separate with commas (,)", "GF_SMS" );
		$tooltips["show_adminbar"] = __( "Show Gravity forms SMS menu in adminbar?", "GF_SMS" );
		$tooltips["sidebar_ajax"]  = __( "Activate this option to replace merge tags value in SMS Sidebar via ajax (Entry details)", "GF_SMS" );

		return $tooltips;
	}


	public static function settings() {

		wp_enqueue_script('GF_SMS_Chosen', GF_SMS_URL . '/assets/chosen_v1.8.5/chosen.jquery.min.js', array(), true);
		wp_enqueue_style('GF_SMS_Chosen', GF_SMS_URL . '/assets/chosen_v1.8.5/chosen.min.css');

		$settings = GFHANNANSMS_Pro::get_option();

		$G_code = rgget( 'gateway' ) ? rgget( 'gateway' ) : ( ! empty( $settings["ws"] ) ? $settings["ws"] : '' );
		$G_code = strtolower( $G_code );

		$gateway_options = get_option( "gf_hannansms_" . $G_code );

		if ( ! rgempty( "uninstall" ) ) {

			check_admin_referer( "uninstall", "gf_hannansms_uninstall" );

			if ( ! self::check_access( "gravityforms_hannansms_uninstall" ) ) {
				die( __( "You don't have adequate permission to uninstall Gravity SMS Pro.", "GF_SMS" ) );
			} else {

				GFHANNANSMS_Pro_SQL::drop_table();

				delete_option( "gf_sms_settings" );
				delete_option( "gf_sms_version" );
				delete_option( "gf_sms_installed" );
				delete_option( "gf_sms_last_sender" );

				foreach ( (array) GFHANNANSMS_Pro_WebServices::get() as $code => $name ) {
					delete_option( "gf_hannansms_" . strtolower( $code ) );
				}

				$plugin = GF_SMS_DIR . "/gravity_sms_pro.php";

				update_option( 'recently_activated', array( $plugin => time() ) + (array) get_option( 'recently_activated' ) );

				deactivate_plugins( $plugin );
				?>

                <div class="updated fade" style="padding:20px;">
					<?php
					echo sprintf( __( "Gravity Forms SMS Pro have been successfully uninstalled. It can be re-activated from the %splugins page%s.", "GF_SMS" ), "<a href='plugins.php'>", "</a>" )
					?>
                </div>

				<?php
			}

			return false;
		} else if ( ! rgempty( "gf_hannansms_submit" ) ) {

			check_admin_referer( "update", "gf_hannansms_update" );

			$settings = array(
				"user_name"    => rgpost( "gf_hannansms_user_name" ),
				"password"     => rgpost( "gf_hannansms_password" ),
				"from"         => rgpost( "gf_hannansms_from" ),
				"code"         => rgpost( "gf_hannansms_code" ),
				"to"           => rgpost( "gf_hannansms_to" ),
				"ws"           => rgpost( "gf_hannansms_ws" ),
				"cr"           => rgpost( "gf_hannansms_showcr" ),
				"menu"         => rgpost( "gf_hannansms_menu" ),
				"sidebar_ajax" => rgpost( "gf_hannansms_sidebar_ajax" )
			);

			update_option( "gf_sms_settings", array_map( 'sanitize_text_field', $settings ) );

			if ( rgpost( "gf_hannansms_ws" ) && rgpost( "gf_hannansms_ws" ) != 'no' ) {

				$Saved_Gateway = 'GFHANNANSMS_Pro_' . strtoupper( sanitize_text_field( rgpost( "gf_hannansms_ws" ) ) );

				if ( class_exists( $Saved_Gateway ) && method_exists( $Saved_Gateway, 'options' ) ) {

					$gateway_options = array();

					foreach ( (array) $Saved_Gateway::options() as $option => $name ) {
						$gateway_options[ $option ] = sanitize_text_field( rgpost( "gf_hannansms_" . strtolower( sanitize_text_field( rgpost( "gf_hannansms_ws" ) ) ) . '_' . $option ) );
					}

					update_option( "gf_hannansms_" . strtolower( sanitize_text_field( rgpost( "gf_hannansms_ws" ) ) ), $gateway_options );
				}
			}

			if ( ! headers_sent() ) {
				wp_redirect( admin_url( 'admin.php?page=gf_settings&subview=gf_sms_pro&updated=true' ) );
				exit;
			}
		}

		if ( rgget( 'updated' ) == 'true' ) {
			echo '<div class="updated fade" style="padding:6px">' . __( "Settings updated.", "GF_SMS" ) . '</div>';
		}
		?>

        <form method="post" action="">

			<?php wp_nonce_field( "update", "gf_hannansms_update" ) ?>

            <h3><span><i
                            class="fa fa fa-mobile"></i><?php echo '   ' . __( "Gravity SMS Pro settings", "GF_SMS" ) . '   '; ?></span>
            </h3>

			<?php
			if ( ! empty( $G_code ) && $G_code != 'no' ) {

				if ( $G_code == strtolower( $settings["ws"] ) && $credit = GFHANNANSMS_Pro::credit( true ) ) {

					preg_match( '/([\d]+)/', $credit, $match );
					$credit_int = isset( $match[0] ) ? $match[0] : $credit;

					$range = GFHANNANSMS_Pro::range();

					$max = isset( $range["max"] ) ? $range["max"] : 500;
					$min = isset( $range["min"] ) ? $range["min"] : 2;

					if ( intval( $credit_int ) >= $max ) {
						$color = '#008000';
					} else if ( intval( $credit_int ) < $max && intval( $credit_int ) >= $min ) {
						$color = '#FFC600';
					} else {
						$color = '#FF1454';
					}
					?>

                    <h5><?php _e( "Your SMS Credit : ", "GF_SMS" ) ?><span
                                style="color:<?php echo $color; ?> !important;"><?php echo $credit; ?></span></h5>

					<?php
				}

			}
			?>

            <hr/>

            <table class="form-table">

                <tr>
                    <th scope="row"><label for="gf_hannansms_ws"><?php _e( "SMS Gateway", "GF_SMS" ); ?></label></th>

                    <td width="340">
                        <select id="gf_hannansms_ws" name="gf_hannansms_ws" style="width:100%;"
                                class="select-gateway<?php echo is_rtl() ? " chosen-rtl" : ""; ?>"
                                onchange="GF_SwitchGateway(jQuery(this).val());">

							<?php foreach ( (array) GFHANNANSMS_Pro_WebServices::get() as $code => $name ) { ?>

                                <option style="padding:3px"
                                        value="<?php echo $code ?>" <?php echo esc_attr( $G_code ) == $code ? "selected='selected'" : "" ?>><?php echo $name ?></option>

							<?php } ?>

                        </select>
                    </td>

                    <td rowspan="2" valign="middle">
                    </td>
                </tr>


				<?php

				if ( ! empty( $G_code ) && $G_code != 'no' ) {

					$Gateway = 'GFHANNANSMS_Pro_' . strtoupper( $G_code );

					if ( class_exists( $Gateway ) && method_exists( $Gateway, 'options' ) ) {

						$flag = true;

						foreach ( (array) $Gateway::options() as $option => $name ) { ?>
                            <tr>
                                <th scope="row"><label
                                            for="gf_hannansms_<?php echo $G_code . '_' . $option; ?>"><?php echo $name; ?></label>
                                </th>
                                <td width="340">
                                    <input type="text" id="gf_hannansms_<?php echo $G_code . '_' . $option; ?>"
                                           name="gf_hannansms_<?php echo $G_code . '_' . $option; ?>"
                                           value="<?php echo esc_attr( $gateway_options[ $option ] ) ?>" size="50"
                                           style="padding: 5px; direction:ltr !important;text-align:left;"/>
                                </td>
                                <td rowspan="2" valign="middle">
                                </td>
                            </tr>
						<?php }
					}
				}
				?>

                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_from">
							<?php _e( "Sender (From)", "GF_SMS" ); ?>
							<?php gform_tooltip( 'gf_sms_sender' ) ?>
                        </label>

                    </th>
                    <td width="340">

                        <input type="text" id="gf_hannansms_from" name="gf_hannansms_from"
                               value="<?php echo esc_attr( $settings["from"] ) ?>" size="50"
                               style="padding: 5px; direction:ltr !important;text-align:left;"/><br/>
                    </td>
                </tr>


                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_code">
							<?php _e( "Your Default Country Code", "GF_SMS" ); ?>
							<?php gform_tooltip( 'country_code' ) ?>
                        </label>
                    </th>
                    <td width="340">

                        <input type="text" id="gf_hannansms_code" name="gf_hannansms_code"
                               value="<?php echo esc_attr( $settings["code"] ) ?>" size="50"
                               style="padding: 5px; direction:ltr !important;text-align:left;"/><br/>

                    </td>
                </tr>


               <!-- <tr>
                    <th scope="row">
                        <label for="gf_hannansms_to">
							<?php /*_e( "Admin Default Numbers", "GF_SMS" ); */?>
							<?php /*gform_tooltip( 'admin_default' ) */?>
                        </label>
                    </th>
                    <td width="340">

                        <input type="text" id="gf_hannansms_to" name="gf_hannansms_to"
                               value="<?php /*echo esc_attr( $settings["to"] ) */?>" size="50"
                               style="padding: 5px; direction:ltr !important;text-align:left;"/><br/>

                    </td>
                </tr>-->

				<?php if ( ! empty( $flag ) && ! empty( $Gateway ) && $Gateway::credit() ) { ?>

                    <tr>
                        <th scope="row">
                            <label for="gf_hannansms_showcr">
								<?php _e( "Show Credit/Balance", "GF_SMS" ); ?>
								<?php gform_tooltip( 'show_credit' ) ?>
                            </label>
                        </th>
                        <td width="340">


                            <input type="radio" name="gf_hannansms_showcr" id="gf_hannansms_showcr_show"
                                   value="Show" <?php echo esc_attr( $settings["cr"] ) == "Show" ? "checked='checked'" : "" ?>/>
                            <label class="inline"
                                   for="gf_hannansms_showcr_show"><?php _e( "Yes", "GF_SMS" ); ?></label>&nbsp;&nbsp;&nbsp;

                            <input type="radio" name="gf_hannansms_showcr" id="gf_hannansms_showcr_no"
                                   value="No" <?php echo esc_attr( $settings["cr"] ) != "Show" ? "checked='checked'" : "" ?>/>
                            <label class="inline"
                                   for="gf_hannansms_showcr_no"><?php _e( "No ( Recommended )", "GF_SMS" ); ?></label>

                            <br/>

                        </td>
                    </tr>

					<?php
				} ?>

                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_menu">
							<?php _e( "Admin Bar Menu", "GF_SMS" ); ?>
							<?php gform_tooltip( 'show_adminbar' ) ?>
                        </label>
                    </th>
                    <td width="340">


                        <input type="radio" name="gf_hannansms_menu" id="gf_hannansms_menu_show"
                               value="Show" <?php echo esc_attr( $settings["menu"] ) == "Show" ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_hannansms_menu_show"><?php _e( "Yes", "GF_SMS" ); ?></label>&nbsp;&nbsp;&nbsp;


                        <input type="radio" name="gf_hannansms_menu" id="gf_hannansms_menu_no"
                               value="No" <?php echo esc_attr( $settings["menu"] ) != "Show" ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_hannansms_menu_no"><?php _e( "No", "GF_SMS" ); ?></label>

                        <br/>

                    </td>
                </tr>


                <tr>
                    <th scope="row">
                        <label for="gf_hannansms_sidebar_ajax">
							<?php _e( "Replace merge tags value in SMS Sidebar", "GF_SMS" ); ?>
							<?php gform_tooltip( 'sidebar_ajax' ) ?>
                        </label>
                    </th>
                    <td width="340">


                        <input type="radio" name="gf_hannansms_sidebar_ajax" id="gf_hannansms_sidebar_ajax_Yes"
                               value="Yes" <?php echo empty( $settings["sidebar_ajax"] ) || esc_attr( $settings["sidebar_ajax"] ) != "No" ? "checked='checked'" : "" ?>/>
                        <label class="inline"
                               for="gf_hannansms_sidebar_ajax_Yes"><?php _e( "Yes", "GF_SMS" ); ?></label>&nbsp;&nbsp;&nbsp;

                        <input type="radio" name="gf_hannansms_sidebar_ajax" id="gf_hannansms_sidebar_ajax_no"
                               value="No" <?php echo ! empty( $settings["sidebar_ajax"] ) && esc_attr( $settings["sidebar_ajax"] ) == "No" ? "checked='checked'" : "" ?>/>
                        <label class="inline" for="gf_hannansms_sidebar_ajax_no"><?php _e( "No", "GF_SMS" ); ?></label>

                        <br/>

                    </td>
                </tr>


                <tr>
                    <th scope="row">
                        <input type="submit" name="gf_hannansms_submit" class="button-primary"
                               value="<?php _e( "Save Settings", "GF_SMS" ) ?>"/>
                    </th>
                </tr>

            </table>


        </form>
        <form action="" method="post">
			<?php wp_nonce_field( "uninstall", "gf_hannansms_uninstall" ) ?>
			<?php if ( self::check_access( "gravityforms_hannansms_uninstall" ) ) { ?>

                <div class="hr-divider"></div>
                <div class="delete-alert alert_red">
                    <h3><?php _e( "Uninstall Gravity Forms SMS Pro", "GF_SMS" ) ?></h3>
                    <div
                            class="gf_delete_notice"><?php _e( "<strong>Warning!</strong> This operation deletes ALL Gravity SMS Pro Informations.", "GF_SMS" ) ?></div>
                    <input type="submit" name="uninstall"
                           value="<?php _e( "Uninstall Gravity Forms SMS Pro", "GF_SMS" ) ?>" class="button"
                           onclick="return confirm('<?php _e( "Warning! ALL Gravity SMS Pro informations will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "GF_SMS" ) ?>'); "/>
                </div>
			<?php } ?>
        </form>


        <script type="text/javascript">
            function GF_SwitchGateway(code) {
                new_query = "gateway=" + code;
                document.location = document.location + "&" + new_query;
            }

            jQuery(document).ready(function () {
                jQuery(".select-gateway").chosen();
            });
        </script>
		<?php
	}
}

if ( defined( 'GF_SMS_GATEWAY' ) ) {

	$files = scandir( GF_SMS_GATEWAY );

	if ( $files ) {

		foreach ( (array) $files as $file ) {

			$path_parts = pathinfo( GF_SMS_GATEWAY . $file );

			if ( strpos( $file, '.php' ) ) {

				include 'gateways/' . $path_parts['filename'] . '.php';

				$Gateway = 'GFHANNANSMS_Pro_' . strtoupper( $path_parts['filename'] );

				if ( class_exists( $Gateway ) ) {
					if ( method_exists( $Gateway, 'options' ) && method_exists( $Gateway, 'process' ) && method_exists( $Gateway, 'name' ) ) {
						add_filter( 'gf_sms_gateways', array( $Gateway, 'name' ) );
					}
				}
			}
		}
	}
}
