<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro_Feeds {

	public static function construct() {
		if ( defined( 'RG_CURRENT_PAGE' ) && in_array( RG_CURRENT_PAGE, array( 'admin-ajax.php' ) ) ) {
			add_action( 'wp_ajax_gf_feed_ajax_active', array( __CLASS__, 'ajax' ) );
			add_action( 'wp_ajax_nopriv_gf_feed_ajax_active', array( __CLASS__, 'ajax' ) );
		}

		add_filter( 'gform_form_settings_menu', array( __CLASS__, 'toolbar' ), 10, 2 );
		add_action( 'gform_form_settings_page_sms', array( __CLASS__, 'sms_form_settings_page' ) );
	}

	public static function ajax() {
		check_ajax_referer( 'gf_feed_ajax_active', 'gf_feed_ajax_active' );
		$id   = rgpost( "feed_id" );
		$feed = GFHANNANSMS_Pro_SQL::get_feed( $id );
		GFHANNANSMS_Pro_SQL::update_feed( $id, $feed["form_id"], rgpost( "is_active" ), $feed["meta"] );
	}

	public static function feeds( $arg ) {

		$show = $arg != 'settings' ? true : false;

		$is_support = 'yes';
		if ( class_exists( "GFCommon" ) ) {
			if ( ! version_compare( GFCommon::$version, GFHANNANSMS_Pro::$gf_version, ">=" ) ) {
				$is_support = 'no';
			}
		} else {
			$is_support = 'no';
		}

		if ( $is_support == 'no' ) {
			die( sprintf( __( "Gravity forms SMS Pro requires Gravity Forms %s. Upgrade automatically on the %sPlugin page%s.", "GF_SMS" ), GFHANNANSMS_Pro::$gf_version, "<a href='plugins.php'>", "</a>" ) );
		}

		if ( rgpost( "action" ) == "delete" ) {
			check_admin_referer( "list_action", "gf_hannansms_list" );
			$id = absint( rgpost( "action_argument" ) );
			GFHANNANSMS_Pro_SQL::remove_feed( $id ); ?>
            <div class="updated fade" style="padding:6px"><?php _e( "Feed deleted.", "GF_SMS" ) ?></div>
			<?php
		} else if ( ! rgempty( "bulk_action" ) ) {

			check_admin_referer( "list_action", "gf_hannansms_list" );
			$selected_feeds = rgpost( "feed" );
			if ( is_array( $selected_feeds ) ) {
				foreach ( (array) $selected_feeds as $feed_id ) {
					GFHANNANSMS_Pro_SQL::remove_feed( $feed_id );
				}
			}
			?>
            <div class="updated fade" style="padding:6px"><?php _e( "Feeds deleted.", "GF_SMS" ) ?></div>
			<?php
		}

		$settings = GFHANNANSMS_Pro::get_option();
		$is_OK    = ( ! empty( $settings["ws"] ) && $settings["ws"] != 'no' );
		?>

        <div class="wrap">

			<?php if ( $show ) { ?>
                <h2>
					<?php _e( "SMS Pro Feeds - All Forms", "GF_SMS" ); ?>
                </h2>
                <br/>
				<?php
				if ( $is_OK ) {
					GFHANNANSMS_Pro::show_credit( $settings["cr"], true );
					?>
                    <a class="add-new-h2"
                       href="admin.php?page=gf_hannansms&view=edit&id=0"><?php _e( "Add New", "GF_SMS" ) ?></a>
                    <a class="add-new-h2"
                       href="admin.php?page=gf_hannansms&view=send"><?php _e( "Send SMS to custom numbers", "GF_SMS" ) ?></a>
					<?php
				}
			}
			?>
            <form id="feed_form" method="post">
				<?php wp_nonce_field( 'list_action', 'gf_hannansms_list' ) ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>
                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px 0;">
                        <label class="hidden" for="bulk_action"><?php _e( "Bulk action", "GF_SMS" ) ?></label>
                        <select name="bulk_action" id="bulk_action">
                            <option value=''> <?php _e( "Bulk action", "GF_SMS" ) ?> </option>
                            <option value='delete'><?php _e( "Delete", "GF_SMS" ) ?></option>
                        </select>
                        <input type="submit" class="button" value="<?php _e( "Apply", "GF_SMS" ) ?>"
                               onclick="if( jQuery('#bulk_action').val() == 'delete' && !confirm('<?php echo __( "Delete selected feeds? \'Cancel\' to stop, \'OK\' to delete.", "GF_SMS" ) ?>')) { return false; } return true;"/>
                        <a class="button-primary"
                           href="admin.php?page=gf_settings&subview=gf_sms_pro"><?php _e( "SMS General settings", "GF_SMS" ) ?></a>
                        <a class="button-primary"
                           href="admin.php?page=gf_hannansms&view=sent<?php echo rgget( 'id' ) ? '&id=' . rgget( 'id' ) : '' ?>"><?php _e( "Sent Messages", "GF_SMS" ) ?></a>

                    </div>
                </div>
                <table class="wp-list-table widefat fixed striped toplevel_page_gf_edit_forms" cellspacing="0">
                    <thead>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column" style="padding:13px 3px">
                            <input type="checkbox"/></th>
                        <th scope="col" id="active" class="manage-column" style="width:30px;text-align:center"></th>
                        <th scope="col"
                            class="manage-column" <?php echo $show ? 'style="width:60px;"' : ''; ?>><?php _e( "Feed ID", "GF_SMS" ) ?></th>
						<?php if ( $show ) { ?>
                            <th scope="col" class="manage-column"
                                style="width:60px;"><?php _e( "Form ID", "GF_SMS" ) ?></th>
                            <th scope="col" class="manage-column"><?php _e( "Form title", "GF_SMS" ) ?></th>
                            <th scope="col" class="manage-column"><?php _e( "Entries", "GF_SMS" ) ?></th>
						<?php } ?>
                        <th scope="col" class="manage-column"><?php _e( "Sender Number", "GF_SMS" ) ?></th>
                        <th scope="col" class="manage-column"><?php _e( "Recievers", "GF_SMS" ) ?></th>
                    </tr>
                    </thead>
                    <tfoot>
                    <tr>
                        <th scope="col" id="cb" class="manage-column column-cb check-column" style="padding:13px 3px">
                            <input type="checkbox"/></th>
                        <th scope="col" id="active" class="manage-column"></th>
                        <th scope="col" class="manage-column"><?php _e( "Feed ID", "GF_SMS" ) ?></th>
						<?php if ( $show ) { ?>
                            <th scope="col" class="manage-column"><?php _e( "Form ID", "GF_SMS" ) ?></th>
                            <th scope="col" class="manage-column"><?php _e( "Form title", "GF_SMS" ) ?></th>
                            <th scope="col" class="manage-column"><?php _e( "Entries", "GF_SMS" ) ?></th>
						<?php } ?>
                        <th scope="col" class="manage-column"><?php _e( "Sender Number", "GF_SMS" ) ?></th>
                        <th scope="col" class="manage-column"><?php _e( "Recievers", "GF_SMS" ) ?></th>
                    </tr>
                    </tfoot>
                    <tbody class="the-list">
					<?php

					if ( rgget( 'id' ) ) {
						$feeds = GFHANNANSMS_Pro_SQL::get_feed_via_formid( rgget( 'id' ), false );
					} else {
						$feeds = GFHANNANSMS_Pro_SQL::get_feeds();
					}
					if ( ! $is_OK ) { ?>
                        <tr>
                            <td colspan="<?php echo ! $show ? '5' : '8' ?>" style="padding:20px;">
								<?php echo sprintf( __( "To get started, please configure your %sGravity SMS Pro Settings%s and set Gateway .", "GF_SMS" ), '<a href="admin.php?page=gf_settings&subview=gf_sms_pro">', "</a>" ); ?>
                            </td>
                        </tr>
						<?php
					} else {
						if ( is_array( $feeds ) && sizeof( $feeds ) > 0 ) {
							rsort( $feeds );
							foreach ( (array) $feeds as $feed ) {
								?>
                                <tr class='author-self status-inherit  gf-locking  alternate' valign="top">
                                    <th scope="row" class="check-column"><input type="checkbox" name="feed[]"
                                                                                value="<?php echo $feed["id"] ?>"/></th>
                                    <td style="text-align:center"><img style="cursor:pointer;max-width:30px;"
                                                                       src="<?php echo esc_url( GFCommon::get_base_url() ) ?>/images/active<?php echo intval( $feed["is_active"] ) ?>.png"
                                                                       alt="<?php echo $feed["is_active"] ? __( "Active", "GF_SMS" ) : __( "Inactive", "GF_SMS" ); ?>"
                                                                       title="<?php echo $feed["is_active"] ? __( "Active", "GF_SMS" ) : __( "Inactive", "GF_SMS" ); ?>"
                                                                       onclick="ToggleActive(this, <?php echo $feed['id'] ?>); "/>
                                    </td>

									<?php if ( ! $show ) { ?>
                                        <td class="column-title">
                                            <strong>
                                                <a href="admin.php?page=gf_hannansms&view=edit&id=<?php echo $feed["id"] ?>"
                                                   title="<?php _e( "Edit Config", "GF_SMS" ) ?>"><?php echo $feed["id"] ?></a>
                                            </strong>
                                            <div class="row-actions">
													<span class="edit">
														<a title="<?php _e( "Configuration", "GF_SMS" ) ?>"
                                                           href="admin.php?page=gf_hannansms&view=edit&id=<?php echo $feed["id"] ?>"><?php _e( "Configuration", "GF_SMS" ) ?></a>
														|
													</span>
                                                <span class="trash">
														<a title="<?php _e( "Delete", "GF_SMS" ) ?>"
                                                           href="javascript: if(confirm('<?php _e( "Delete this feed? ", "GF_SMS" ) ?> <?php _e( "\'Cancel\' to stop, \'OK\' to delete.", "GF_SMS" ) ?>')){ DeleteSetting(<?php echo $feed["id"] ?>);}"><?php _e( "delete", "GF_SMS" ) ?></a>
													</span>
                                            </div>
                                        </td>
									<?php } else { ?>
                                        <td style="text-align:center"><?php echo $feed["id"] ?> </td>
                                        <td style="text-align:center"><?php echo $feed["form_id"] ?> </td>
                                        <td class="column-title">
                                            <strong>
                                                <a href="admin.php?page=gf_hannansms&view=edit&id=<?php echo $feed["id"] ?>"
                                                   title="<?php _e( "Edit Config", "GF_SMS" ) ?>"><?php echo $feed["form_title"] ?></a>
                                            </strong>
                                            <div class="row-actions">
													<span class="edit">
														<a title="<?php _e( "Configuration", "GF_SMS" ) ?>"
                                                           href="admin.php?page=gf_hannansms&view=edit&id=<?php echo $feed["id"] ?>"><?php _e( "Configuration", "GF_SMS" ) ?></a>
														|
													</span>
                                                <span class="edit">
														<a title="<?php _e( "Edit Form", "GF_SMS" ) ?>"
                                                           href="admin.php?page=gf_edit_forms&id=<?php echo $feed["form_id"] ?>"><?php _e( "Edit form", "GF_SMS" ) ?></a>
														|
													</span>
                                                <span class="view">
														<a title="<?php _e( "Entries", "GF_SMS" ) ?>"
                                                           href="admin.php?page=gf_entries&view=entries&id=<?php echo $feed["form_id"] ?>"><?php _e( "Entries", "GF_SMS" ) ?></a>
														|
													</span>
                                                <span class="trash">
														<a title="<?php _e( "Delete", "GF_SMS" ) ?>"
                                                           href="javascript: if(confirm('<?php _e( "Delete this feed? ", "GF_SMS" ) ?> <?php _e( "\'Cancel\' to stop, \'OK\' to delete.", "GF_SMS" ) ?>')){ DeleteSetting(<?php echo $feed["id"] ?>);}"><?php _e( "delete", "GF_SMS" ) ?></a>
														|
													</span>
                                                <span class="view">
														<a title="<?php _e( "Feed", "GF_SMS" ) ?>"
                                                           href="admin.php?page=gf_edit_forms&view=settings&subview=sms&id=<?php echo $feed["form_id"] ?>"><?php _e( "Form Feeds", "GF_SMS" ) ?></a>
														|
													</span>
                                                <span class="outbox">
														<a title="<?php _e( "Outbox", "GF_SMS" ) ?>"
                                                           href="admin.php?page=gf_hannansms&view=sent&id=<?php echo $feed["form_id"] ?>"><?php _e( "Form Outbox", "GF_SMS" ) ?></a>
													</span>
                                            </div>
                                        </td>

                                        <td class="column-date">
                                            <strong>
                                                <a href="<?php echo admin_url( "admin.php?page=gf_entries&view=entries&id={$feed["form_id"]}" ); ?>">
													<?php
													if ( GFHANNANSMS_Pro::entry_type( 'GFAPI', 'count_entries' ) ) {
														echo GFAPI::count_entries( $feed["form_id"], array() );
													} else {
														echo RGFormsModel::get_lead_count( $feed["form_id"], '', null, null, null, null, null );
													}
													?>
                                                </a>
                                            </strong>
                                        </td>
									<?php } ?>
                                    <td class="column-date"><?php echo esc_html( $feed["meta"]["from"] ) ?></td>
									<?php
									$sep1 = '';
									$sep2 = '';

									if ( ! empty( $feed["meta"]["to"] ) && ! empty( $feed["meta"]["to_c"] ) ) {
										$sep1 = ",";
									}

									$field_label     = '';
									$form            = RGFormsModel::get_form_meta( $feed["form_id"] );
									$client_field_id = $feed["meta"]["customer_field_clientnum"];
									foreach ( $form['fields'] as $field ) {
										if ( $field->id == $client_field_id ) {
											$field_label = sprintf( __( 'Reciever field label: %s', 'GF_SMS' ), RGFormsModel::get_label( $field ) );
										}
									}

									if ( ( ! empty( $feed["meta"]["to"] ) || ! empty( $feed["meta"]["to_c"] ) ) && ( $field_label && $field_label != '' ) ) {
										$sep2 = '<br/>';
									}
									?>
                                    <td class="column-date"><?php echo esc_html( $feed["meta"]["to"] ) . $sep1 . esc_html( $feed["meta"]["to_c"] ) . $sep2 . $field_label ?></td>
                                </tr>
								<?php
							}
						} else {
							?>
                            <tr>
                                <td colspan="<?php echo ! $show ? '5' : '8' ?>" style="padding:20px;">
									<?php echo sprintf( __( "You don't have any SMS feeds configured. Let's go %screate one%s!", "GF_SMS" ), '<a href="admin.php?page=gf_hannansms&view=edit&id=0&fid=' . absint( rgget( "id" ) ) . '">', "</a>" ); ?>
                                </td>
                            </tr>
							<?php
						}
					}
					?>
                    </tbody>
                </table>
            </form>
        </div>
        <script type="text/javascript">
            function DeleteSetting(id) {
                jQuery("#action_argument").val(id);
                jQuery("#action").val("delete");
                jQuery("#feed_form")[0].submit();
            }

            function ToggleActive(img, feed_id) {
                var is_active = img.src.indexOf("active1.png") >= 0;
                if (is_active) {
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title', '<?php _e( "Inactive", "GF_SMS" ) ?>').attr('alt', '<?php _e( "Inactive", "GF_SMS" ) ?>');
                }
                else {
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title', '<?php _e( "Active", "GF_SMS" ) ?>').attr('alt', '<?php _e( "Active", "GF_SMS" ) ?>');
                }
                jQuery.post(ajaxurl, {
                    action: "gf_feed_ajax_active",
                    gf_feed_ajax_active: "<?php echo wp_create_nonce( "gf_feed_ajax_active" ) ?>",
                    feed_id: feed_id,
                    is_active: is_active ? 0 : 1,
                    cookie: encodeURIComponent(document.cookie)
                });
                return true;
            }
        </script>
		<?php
	}


	public static function toolbar( $menu_items, $form_id ) {
		$menu_items[] = array(
			'name'  => 'sms',
			'label' => __( 'SMS', 'GF_SMS' )
		);

		return $menu_items;
	}

	public static function sms_form_settings_page() {
		GFFormSettings::page_header(); ?>


        <h3><span><i class="fa fa-mobile"></i> <?php esc_html_e( 'SMS', 'GF_SMS' ) ?>
                <a id="add-new-confirmation" class="add-new-h2"
                   href="<?php echo esc_url( admin_url( 'admin.php?page=gf_hannansms&view=edit&id=0&fid=' . absint( rgget( "id" ) ) ) ) ?>"><?php esc_html_e( 'Add New', 'GF_SMS' ) ?></a></span>
        </h3>

		<?php self::feeds( 'settings' ); ?>

		<?php GFFormSettings::page_footer();
	}
}