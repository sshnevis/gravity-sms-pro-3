<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GFHANNANSMS_Pro_SQL {

	public static function setup_update() {
		if ( get_option( "gf_sms_version" ) != GFHANNANSMS_Pro::$version || ! get_option( 'gf_sms_installed' ) ) {
			self::gf_sms_create_tables();
		}
	}

	public static function main_table() {
		global $wpdb;

		return $wpdb->prefix . "gf_sms_feed";
	}

	public static function sent_table() {
		global $wpdb;

		return $wpdb->prefix . "gf_sms_sent";
	}

	public static function verify_table() {
		global $wpdb;

		return $wpdb->prefix . "gf_sms_verification";
	}

	public static function gf_sms_create_tables() {

		update_option( 'gf_sms_installed', '1' );
		update_option( 'gf_sms_version', GFHANNANSMS_Pro::$version );

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
		}

		global $wpdb;

		$charset_collate = '';
		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
		}
		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}

		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		$main_table_name = self::main_table();

		foreach ( array( 'HANNANStd_GravitySMS', 'gravity_sms_pro_hannanstd' ) as $old_table ) {
			$old_table = $wpdb->prefix . $old_table;
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$old_table}'" ) ) {
				$wpdb->query( "RENAME TABLE $old_table TO $main_table_name" );
			}
		}

		dbDelta( "CREATE TABLE IF NOT EXISTS $main_table_name (
			id mediumint(8) unsigned not null auto_increment,
			form_id mediumint(8) unsigned not null,
			is_active tinyint(1) not null default 1,
			meta longtext,
			PRIMARY KEY  (id),
			KEY form_id (form_id)
		) $charset_collate;" );

		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		$sent_table_name = self::sent_table();
		$old_table       = $wpdb->prefix . 'gravity_sms_pro_sent';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$old_table}'" ) ) {

			if ( $wpdb->get_var( "SHOW COLUMNS FROM {$old_table} LIKE 'lead_id'" ) ) {
				$wpdb->query( "ALTER TABLE {$old_table} CHANGE COLUMN lead_id entry_id VARCHAR(20) NOT NULL" );
			}

			$wpdb->query( "RENAME TABLE $old_table TO $sent_table_name" );
		}

		dbDelta( "CREATE TABLE IF NOT EXISTS $sent_table_name (
			id mediumint(8) unsigned not null auto_increment,
			form_id mediumint(8) unsigned not null,
            entry_id VARCHAR(20) NOT NULL,
			date DATETIME,
			sender VARCHAR(20) NOT NULL,
			reciever VARCHAR(255) NOT NULL,
			message TEXT NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;" );

		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		$verify_table_name = self::verify_table();
		$old_table         = $wpdb->prefix . 'gravity_sms_pro_verification';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$old_table}'" ) ) {

			if ( $wpdb->get_var( "SHOW COLUMNS FROM {$old_table} LIKE 'lead_id'" ) ) {
				$wpdb->query( "ALTER TABLE {$old_table} CHANGE COLUMN lead_id entry_id mediumint(10) unsigned not null" );
			}

			$wpdb->query( "RENAME TABLE $old_table TO $verify_table_name" );
		}

		dbDelta( "CREATE TABLE IF NOT EXISTS $verify_table_name (
			id mediumint(8) unsigned not null auto_increment,
			form_id mediumint(8) unsigned not null,
            entry_id mediumint(10) unsigned not null,	
            try_num mediumint(10) unsigned not null,
            sent_num mediumint(10) unsigned not null,		
			mobile VARCHAR(20) NOT NULL,
			code VARCHAR(250),
			status tinyint(1),
			PRIMARY KEY  (id),
			KEY form_id (form_id)
		) $charset_collate;" );

		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/
		/*---------------------------------------------------------------------*/

	}

	public static function drop_table() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::main_table() );
		$wpdb->query( "DROP TABLE IF EXISTS " . self::sent_table() );
		$wpdb->query( "DROP TABLE IF EXISTS " . self::verify_table() );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'gf_%sms_%' ) );
	}

	public static function get_feeds() {

		global $wpdb;

		$table_name      = self::main_table();
		$form_table_name = RGFormsModel::get_form_table_name();

		$sql = "SELECT s.id, s.is_active, s.form_id, s.meta, f.title as form_title
                FROM $table_name s
                INNER JOIN $form_table_name f ON s.form_id = f.id";

		$results = $wpdb->get_results( $sql, ARRAY_A );
		$count   = sizeof( $results );

		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]["meta"] = maybe_unserialize( $results[ $i ]["meta"] );
		}

		return $results;
	}

	public static function get_feed( $id ) {

		global $wpdb;

		$table_name = self::main_table();
		$sql        = $wpdb->prepare( "SELECT id, form_id, is_active, meta FROM $table_name WHERE id=%d", $id );
		$results    = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $results ) ) {
			return array();
		}

		$result         = $results[0];
		$result["meta"] = maybe_unserialize( $result["meta"] );

		return $result;
	}

	public static function get_feed_via_formid( $form_id, $only_active = false ) {
		global $wpdb;
		$table_name = self::main_table();

		$active_clause = $only_active ? " AND is_active=1" : "";
		$sql           = $wpdb->prepare( "SELECT id, form_id, is_active, meta FROM $table_name WHERE form_id=%d $active_clause", $form_id );
		$results       = $wpdb->get_results( $sql, ARRAY_A );
		if ( empty( $results ) ) {
			return array();
		}

		$count = sizeof( $results );

		for ( $i = 0; $i < $count; $i ++ ) {
			$results[ $i ]["meta"] = maybe_unserialize( $results[ $i ]["meta"] );
		}

		return $results;
	}

	public static function update_feed( $id, $form_id, $is_active, $setting ) {

		global $wpdb;

		$table_name = self::main_table();
		$setting    = maybe_serialize( $setting );

		if ( $id == 0 ) {
			$wpdb->insert( $table_name, array(
				"form_id"   => $form_id,
				"is_active" => $is_active,
				"meta"      => $setting
			), array( "%d", "%d", "%s" ) );
			$id = $wpdb->get_var( "SELECT LAST_INSERT_ID()" );
		} else {
			$wpdb->update( $table_name, array(
				"form_id"   => $form_id,
				"is_active" => $is_active,
				"meta"      => $setting
			), array( "id" => $id ), array( "%d", "%d", "%s" ), array( "%d" ) );
		}

		return $id;

	}

	public static function remove_feed( $id ) {

		global $wpdb;
		$table_name = self::main_table();
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id=%s", $id ) );
	}

	public static function save_sms_sent( $form_id, $entry_id, $sender, $reciever, $message, $verify_code = '' ) {

		global $wpdb;
		$sent_table_name = self::sent_table();

		if ( empty( $entry_id ) || ! $entry_id ) {
			$entry_id = ! empty( $verify_code ) ? '_' . $verify_code . '_' : '';
		} else {
			$entry_id = is_array( $entry_id ) ? implode( ',', $entry_id ) : $entry_id;
		}

		$form_id  = ! empty( $form_id ) ? $form_id : 0;
		$reciever = is_array( $reciever ) ? implode( ',', $reciever ) : $reciever;

		$wpdb->insert( $sent_table_name,
			array(
				'date'     => date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
				'form_id'  => $form_id,
				'entry_id' => $entry_id,
				'sender'   => $sender,
				'reciever' => $reciever,
				'message'  => $message
			),
			array(
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s'
			)
		);
	}

	public static function update_entry_verify_sent( $form_id, $entry_id, $verify_code ) {

		global $wpdb;

		$sent_table_name = self::sent_table();

		$form_id = ! empty( $form_id ) ? $form_id : 0;

		if ( empty( $entry_id ) || ! $entry_id ) {
			$entry_id = '';
		} else {
			$entry_id = is_array( $entry_id ) ? implode( ',', $entry_id ) : $entry_id;
		}

		$verify_code = '_' . $verify_code . '_';

		$wpdb->update( $sent_table_name,
			array(
				'entry_id' => $entry_id,
			),
			array( 'form_id' => $form_id, 'entry_id' => $verify_code ),
			array( '%s' ),
			array( '%d', '%s' )
		);
	}

	public static function insert_verify( $form_id, $entry_id, $mobile, $code, $status, $try_num, $sent_num ) {
		global $wpdb;
		$sent_verify_table = self::verify_table();
		$entry_id          = ! empty( $entry_id ) ? $entry_id : '';
		$form_id           = ! empty( $form_id ) ? $form_id : 0;
		$wpdb->insert( $sent_verify_table,
			array(
				'form_id'  => $form_id,
				'entry_id' => $entry_id,
				'mobile'   => $mobile,
				'code'     => $code,
				'try_num'  => $try_num,
				'sent_num' => $sent_num,
				'status'   => $status
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
				'%d'
			)
		);
	}

	public static function update_verify( $id, $try_num, $sent_num, $entry_id, $status ) {
		global $wpdb;
		$sent_verify_table = self::verify_table();
		$entry_id          = ! empty( $entry_id ) ? $entry_id : '';
		$wpdb->update( $sent_verify_table,
			array(
				'entry_id' => $entry_id,
				'try_num'  => $try_num,
				'sent_num' => $sent_num,
				'status'   => $status
			),
			array( 'id' => $id ),
			array(
				'%d',
				'%d',
				'%d',
				'%d'
			),
			array( '%d' )
		);

	}

}