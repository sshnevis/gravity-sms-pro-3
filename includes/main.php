<?php if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro {

	public static $version = '2.3.0';
	public static $gf_version = '1.9.10';
	public static $stored_credit;
	public static $get_option;

	public static function construct() {

		if ( function_exists( 'members_get_capabilities' ) ) {
			add_filter( 'members_get_capabilities', array( __CLASS__, 'members_get_capabilities' ) );
		}

		if ( ! class_exists( 'GFCommon' ) ) {
			return false;
		}

		if ( ! version_compare( GFCommon::$version, self::$gf_version, '>=' ) ) {
			return false;
		}

		if ( is_admin() && ! self::check_access( 'gravityforms_hannansms' ) ) {
			return false;
		}

		if ( ! class_exists( 'GFHANNANSMS_Pro_WebServices' ) ) {
			require_once( GF_SMS_DIR . 'includes/gateways.php' );
		}

		if ( ! class_exists( 'GFHANNANSMS_Pro_SQL' ) ) {
			require_once( GF_SMS_DIR . 'includes/sql.php' );
		}
		GFHANNANSMS_Pro_SQL::setup_update();

		if ( ! class_exists( 'GFHANNANSMS_Pro_Verification' ) ) {
			require_once( GF_SMS_DIR . 'includes/verification.php' );
		}
		GFHANNANSMS_Pro_Verification::construct();

		if ( ! class_exists( 'GFHANNANSMS_Pro_WP_SMS' ) ) {
			require_once( GF_SMS_DIR . 'includes/wp-sms-intergrate.php' );
		}
		GFHANNANSMS_Pro_WP_SMS::construct();

		if ( ! class_exists( 'GFHANNANSMS_Form_Send' ) ) {
			require_once( GF_SMS_DIR . 'includes/send.php' );
		}
		GFHANNANSMS_Form_Send::construct();

		if ( ! class_exists( 'GFHANNANSMS_Pro_Bulk' ) ) {
			require_once( GF_SMS_DIR . 'includes/bulk.php' );
		}
		GFHANNANSMS_Pro_Bulk::construct();

		if ( is_admin() ) {

		    /*
			if ( ! get_option( 'gf_sms_pro_notice_5' ) ) {
				add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
				add_action( 'wp_ajax_nopriv_dismiss_admin_notice_gf_sms', array(
					__CLASS__,
					'dismiss_admin_notice_gf_sms'
				) );
				add_action( 'wp_ajax_dismiss_admin_notice_gf_sms', array( __CLASS__, 'dismiss_admin_notice_gf_sms' ) );
			}
		    */


			add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar_menu' ), 2000 );
			add_filter( 'gform_addon_navigation', array( __CLASS__, 'submenu' ) );

			if ( ! class_exists( 'GFHANNANSMS_Pro_Settings' ) ) {
				require_once( GF_SMS_DIR . 'includes/settings.php' );
			}
			RGForms::add_settings_page( array(
					'name'      => 'gf_sms_pro',
					'tab_label' => __( 'SMS Settings', 'GF_SMS' ),
					'title'     => __( 'Gravityforms SMS Pro', 'GF_SMS' ),
					'handler'   => array( 'GFHANNANSMS_Pro_Settings', 'settings' ),
				)
			);

			if ( ! class_exists( 'GFHANNANSMS_Pro_Configurations' ) ) {
				require_once( GF_SMS_DIR . 'includes/configurations.php' );
			}
			GFHANNANSMS_Pro_Configurations::construct();

			if ( ! class_exists( 'GFHANNANSMS_Pro_Feeds' ) ) {
				require_once( GF_SMS_DIR . 'includes/feeds.php' );
			}
			GFHANNANSMS_Pro_Feeds::construct();

			if ( ! class_exists( 'GFHANNANSMS_Pro_Entries_Sidebar' ) ) {
				require_once( GF_SMS_DIR . 'includes/sidebar.php' );
			}
			GFHANNANSMS_Pro_Entries_Sidebar::construct();

			if ( ! class_exists( 'GFHANNANSMS_Pro_Sent' ) ) {
				require_once( GF_SMS_DIR . 'includes/sent.php' );
			}

		}
	}

	public static function active() {
		global $wp_roles;
		$editable_roles = get_editable_roles();
		foreach ( (array) $editable_roles as $role => $details ) {
			if ( $role == 'administrator' || in_array( 'gravityforms_edit_forms', $details['capabilities'] ) ) {
				$wp_roles->add_cap( $role, 'gravityforms_hannansms' );
				$wp_roles->add_cap( $role, 'gravityforms_hannansms_uninstall' );
			}
		}
	}

	protected static function check_access( $required_permission ) {
		if ( ! function_exists( 'wp_get_current_user' ) ) {
			include( ABSPATH . "wp-includes/pluggable.php" );
		}

		return GFCommon::current_user_can_any( $required_permission );
	}

	public static function members_get_capabilities( $caps ) {
		return array_merge( $caps, array( "gravityforms_hannansms", "gravityforms_hannansms_uninstall" ) );
	}

	public static function deactive() {
		delete_option( "gf_sms_installed" );
	}

	public static function submenu( $submenus ) {
		$permission = "gravityforms_hannansms";
		if ( ! empty( $permission ) ) {
			$submenus[] = array(
				"name"       => "gf_hannansms",
				"label"      => __( "SMS Notification", "GF_SMS" ),
				"callback"   => array( __CLASS__, "pages" ),
				"permission" => $permission
			);
		}

		return $submenus;
	}

	public static function get_option() {
		if ( ! empty( self::$get_option ) ) {
			return self::$get_option;
		}

		$options = get_option( "gf_sms_settings" );

		if ( ! empty( $options ) && is_array( $options ) ) {
			self::$get_option = array_map( 'sanitize_text_field', $options );
		} else {
			self::$get_option = $options;
		}

		return self::$get_option;
	}

	public static function pages() {
		$view = rgget( "view" );
		if ( $view == "edit" ) {
			GFHANNANSMS_Pro_Configurations::configuration();
		} else if ( $view == "send" ) {
			GFHANNANSMS_Pro_Bulk::send_many_numbers();
		} else if ( $view == "sent" ) {
			GFHANNANSMS_Pro_Sent::table();
		} else {
			GFHANNANSMS_Pro_Feeds::feeds( '' );
		}
	}

	public static function admin_bar_menu() {

		$settings = self::get_option();

		if ( $settings["menu"] != 'Show' ) {
			return false;
		}

		$balance = '';
		if ( $settings["cr"] == 'Show' ) {

			$credit = self::credit();
			if ( ! empty( $credit ) && $credit ) {
				$balance = ' (' . $credit . ') ';
			}
		}

		global $wp_admin_bar;
		$menu_id = 'GF_SMS';
		$wp_admin_bar->add_menu( array(
			'id'    => $menu_id,
			'title' => ( __( 'GF SMS', 'GF_SMS' ) . $balance ),
			'href'  => 'admin.php?page=gf_settings&subview=gf_sms_pro'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'title'  => __( 'General Settings', 'GF_SMS' ),
			'id'     => 'gf-sms-settings',
			'href'   => 'admin.php?page=gf_settings&subview=gf_sms_pro'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'title'  => __( 'Feeds', 'GF_SMS' ),
			'id'     => 'gf-sms-feeds',
			'href'   => 'admin.php?page=gf_hannansms'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'title'  => __( 'New Feed', 'GF_SMS' ),
			'id'     => 'gf-sms-new-feed',
			'href'   => 'admin.php?page=gf_hannansms&view=edit&id=0'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'title'  => __( 'Send Message', 'GF_SMS' ),
			'id'     => 'gf-sms-send',
			'href'   => 'admin.php?page=gf_hannansms&view=send'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'title'  => __( 'Sent Messages', 'GF_SMS' ),
			'id'     => 'gf-sms-sent',
			'href'   => 'admin.php?page=gf_hannansms&view=sent'
		) );

		$feeds = GFHANNANSMS_Pro_SQL::get_feeds();
		if ( is_array( $feeds ) && sizeof( $feeds ) > 0 ) {
			rsort( $feeds );
			foreach ( (array) $feeds as $feed ) {
				$wp_admin_bar->add_menu( array(
					'parent' => 'gf-sms-feeds',
					'title'  => __( 'Feed', 'GF_SMS' ) . ' ' . $feed['id'] . ' (' . $feed["form_title"] . ')',
					'id'     => 'gf-sms-feed-' . $feed['id'],
					'href'   => 'admin.php?page=gf_hannansms&view=edit&id=' . $feed['id']
				) );
			}
		}

	}

	public static function range() {
		$settings = self::get_option();

		return GFHANNANSMS_Pro_WebServices::action( $settings, "range", '', '', '' );
	}

	public static function credit( $update = false ) {

		if ( $update ) {
			self::$get_option = null;
		} else if ( ! empty( self::$stored_credit ) ) {
			return self::$stored_credit;
		}

		$settings            = self::get_option();
		self::$stored_credit = GFHANNANSMS_Pro_WebServices::action( $settings, "credit", '', '', '' );

		return self::$stored_credit;
	}

	public static function show_credit( $show, $label ) {

		$credit = '';
		if ( $show == "Show" ) {

			$credit = self::credit();

			if ( ! empty( $credit ) && $credit ) {

				preg_match( '/([\d]+)/', $credit, $match );
				$credit_int = isset( $match[0] ) ? $match[0] : $credit;

				$range = self::range();
				$max   = isset( $range["max"] ) ? $range["max"] : 500;
				$min   = isset( $range["min"] ) ? $range["min"] : 2;

				if ( intval( $credit_int ) >= $max ) {
					$color = '#008000';
				} else if ( intval( $credit_int ) < $max && intval( $credit_int ) >= $min ) {
					$color = '#FFC600';
				} else {
					$color = '#FF1454';
				}

				$pos = is_rtl() ? 'left' : 'right';

				if ( $label ) {
					$credit = '<label style="font-size:16px !important;">' . __( 'Your Credit : ', 'GF_SMS' ) . '<span style="color:' . $color . ' !important;">' . $credit . '</span></label>';
				} else {
					$credit = '<span style="position: absolute; ' . $pos . ': 10px; color:' . $color . ' !important;">' . $credit . '</span>';
				}

			}
		}

		echo $credit;
	}

	public static function admin_notice() {
		$class   = 'notice notice-success gf_sms_pro_notice is-dismissible';
		$message .= '<hr>';
		$message .= sprintf( __( 'Do you ❤️ "Persian Gravity Forms SMS Pro"? Please Rate 5 Stars on %sWordpress.org%s', 'GF_SMS' ), '<a target="_blank" href="https://wordpress.org/plugins/persian-gravity-sms-pro">', '</a>' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

		self::is_dismissible();
	}

	private static function is_dismissible() {
		?>
        <script type="text/javascript">
            jQuery(document).on("click", ".gf_sms_pro_notice .notice-dismiss", function () {
                jQuery.ajax({
                    url: "<?php echo admin_url( 'admin-ajax.php' ) ?>",
                    type: "post",
                    data: {
                        action: "dismiss_admin_notice_gf_sms",
                        security: "<?php echo wp_create_nonce( "dismiss_admin_notice_gf_sms" ); ?>",
                    },
                    success: function (response) {
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	public static function dismiss_admin_notice_gf_sms() {
		check_ajax_referer( 'dismiss_admin_notice_gf_sms', 'security' );
		delete_option( 'gf_sms_pro_notice_4' );
		update_option( 'gf_sms_pro_notice_5', 'true' );
		die();
	}

	public static function entry_type( $class = '', $method = '' ) {

		if ( empty( $class ) || empty( $method ) || method_exists( $class, $method ) ) {

			return true;

			/*$version = GFCommon::$version;
			if ( method_exists( 'GFFormsModel', 'get_database_version' ) ) {
				$version = GFFormsModel::get_database_version();
			}

			if ( version_compare( $version, '2.3-dev-1', '>=' ) ) {
				return true;
			}*/
		}

		return false;
	}
}