<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class GF_SMS_Sent_List_Table extends WP_List_Table {

	function __construct() {
		global $status, $page;
		parent::__construct( array(
			'singular' => __( 'SMS', 'GF_SMS' ),
			'plural'   => __( 'SMS', 'GF_SMS' ),
			'ajax'     => false
		) );
	}

	function no_items() {
		_e( 'No items found, dude.', 'GF_SMS' );
	}

	function column_default( $item, $column_name ) {
		$align = is_rtl() ? 'right' : 'left';
		switch ( $column_name ) {
			case 'form_id':
			case 'entry_id':
				return ! empty( $item[ $column_name ] ) && is_numeric( str_replace( ',', '', $item[ $column_name ] ) ) ? $item[ $column_name ] : '-';
			case 'message':
				return $item[ $column_name ];
			case 'date':
				return date_i18n( 'Y-m-d H:i:s', strtotime( $item[ $column_name ] ) );
			case 'sender':
			case 'reciever':
				return '<div style="direction:ltr !important;text-align:' . $align . ';">' . $item[ $column_name ] . '</div>';
			default:
				return print_r( $item, true );
		}
	}

	function get_sortable_columns() {
		$sortable_columns = array(
			'date'     => array( 'date', false ),
			'form_id'  => array( 'form_id', false ),
			'entry_id' => array( 'entry_id', false ),
			'sender'   => array( 'sender', false ),
			'reciever' => array( 'reciever', false ),
			'message'  => array( 'message', false )
		);

		return $sortable_columns;
	}

	function get_columns() {
		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'date'     => __( 'Date', 'GF_SMS' ),
			'form_id'  => __( 'Form ID', 'GF_SMS' ),
			'entry_id' => __( 'Entry ID', 'GF_SMS' ),
			'sender'   => __( 'From', 'GF_SMS' ),
			'reciever' => __( 'Recievers', 'GF_SMS' ),
			'message'  => __( 'Message', 'GF_SMS' )
		);

		return $columns;
	}

	function column_date( $item ) {

		$delete_nonce = wp_create_nonce( 'gf_delete_sms' );

		$actions = array(
			'delete' => sprintf( '<a href="?page=%s&view=sent&action=%s&item=%s&_wpnonce=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce, __( 'Delete', 'GF_SMS' ) ),
		);

		return sprintf( '%1$s %2$s', date_i18n( 'Y-m-d H:i:s', strtotime( $item['date'] ) ), $this->row_actions( $actions ) );
	}

	function get_bulk_actions() {
		$actions = array(
			'bulk_delete' => __( 'Delete', 'GF_SMS' )
		);

		return $actions;
	}

	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="item[]" value="%s" />', $item['id']
		);
	}

	function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		/** Process bulk action */
		$this->process_bulk_action();

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$total_items  = $this->record_count();

		$this->set_pagination_args( array(
			'total_items' => $total_items,                  //WE have to calculate the total number of items
			'per_page'    => $per_page                     //WE have to determine how many items to show on a page
		) );
		$this->items = $this->get_items( $per_page, $current_page );
	}


	/**
	 * Returns the count of records in the database.
	 *
	 * @return null|string
	 */
	public function record_count() {
		global $wpdb;

		$sent_table_name = GFHANNANSMS_Pro_SQL::sent_table();

		$sql = "SELECT COUNT(*) FROM {$sent_table_name}";

		if ( isset( $_REQUEST['id'] ) ) {
			$sql .= ' WHERE `form_id` LIKE "%%' . $wpdb->esc_like( $_REQUEST['id'] ) . '%%"';
		}

		return $wpdb->get_var( $sql );
	}

	public function process_bulk_action() {

		$action = $this->current_action();

		if ( 'delete' === $action ) {

			if ( ! empty( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'gf_delete_sms' ) ) {
				die( __( 'Go get a life script kiddies', 'GF_SMS' ) );
			}


			$this->delete_item( absint( $_REQUEST['item'] ) );

			echo '<div class="updated notice is-dismissible below-h2"><p>' . __( 'Item removed.', 'GF_SMS' ) . '</p></div>';
		} else if ( $action == 'bulk_delete' ) {

			if ( ! empty( $_REQUEST ) && ! wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'bulk-' . $this->_args['plural'] ) ) {
				die( __( 'Go get a life script kiddies', 'GF_SMS' ) );
			}


			$delete_ids = isset( $_REQUEST['item'] ) ? esc_sql( $_REQUEST['item'] ) : array();
			foreach ( (array) $delete_ids as $id ) {
				$this->delete_item( absint( $id ) );
			}

			echo '<div class="updated notice is-dismissible below-h2"><p>' . __( 'Items removed.', 'GF_SMS' ) . '</p></div>';

		}
	}


	/**
	 * Delete a item record.
	 *
	 * @param int $id item ID
	 */
	public function delete_item( $id ) {
		global $wpdb;

		$sent_table_name = GFHANNANSMS_Pro_SQL::sent_table();

		$wpdb->delete( $sent_table_name, array( 'id' => $id ) );
	}

	/**
	 * Retrieve items data from the database
	 *
	 * @param int $per_page
	 * @param int $page_number
	 *
	 * @return mixed
	 */
	public function get_items( $per_page = 20, $page_number = 1 ) {

		global $wpdb;

		$sent_table_name = GFHANNANSMS_Pro_SQL::sent_table();

		$sql = "SELECT * FROM {$sent_table_name}";

		if ( isset( $_REQUEST['s'] ) ) {
			$sql .= ' WHERE `message` LIKE "%%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%%" OR `reciever` LIKE "%%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%%"  OR `sender` LIKE "%%' . $wpdb->esc_like( $_REQUEST['s'] ) . '%%"';
		} else if ( isset( $_REQUEST['id'] ) ) {
			$sql .= ' WHERE `form_id` LIKE "%%' . $wpdb->esc_like( $_REQUEST['id'] ) . '%%"';
		}

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$sql .= ' ORDER BY ' . esc_sql( $_REQUEST['orderby'] );
			$sql .= ! empty( $_REQUEST['order'] ) ? ' ' . esc_sql( $_REQUEST['order'] ) : ' ASC';
		} else {
			$sql .= ' ORDER BY id DESC';
		}

		$sql .= " LIMIT $per_page";
		$sql .= ' OFFSET ' . ( $page_number - 1 ) * $per_page;


		$result = $wpdb->get_results( $sql, 'ARRAY_A' );

		return $result;
	}

} //class


class GFHANNANSMS_Pro_Sent {

	public static function table() {

		$gf_sms_sent = new GF_SMS_Sent_List_Table();
		echo '<div class="wrap"><h2>' . __( 'Sent Messages', 'GF_SMS' );

		if ( isset( $_GET['id'] ) ) {

			$form = RGFormsModel::get_form_meta( rgget( 'id' ) );
			if ( ! empty( $form ) ) {
				echo ' (' . $form['title'] . ')</h2>';
			} else {
				echo '</h2>';
			}
		} else {
			echo '</h2>';
		}

		$gf_sms_sent->prepare_items();

		echo '<style type="text/css">';
		echo '.wp-list-table .column-id { width: 5%; }';
		echo '</style>';


		?>
        <form method="post">
            <input type="hidden" name="page" value="GF_SMS_list_table">
			<?php
			$gf_sms_sent->search_box( __( 'Search', 'GF_SMS' ), 'search_id' );
			$gf_sms_sent->display();
			?>
        </form></div>
		<?php

	}
}