<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// WP_List_Table is not loaded automatically so we need to load it in our application
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Create a new table class that will extend the WP_List_Table.
 */
class Patchstack_Network_Sites_Table extends WP_List_Table {

	/**
	 * Prepare the items for the table to process
	 *
	 * @return Void
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$data     = $this->table_data();

		usort( $data, [ &$this, 'sort_data' ] );
		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$total_items  = count( $data );

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $per_page,
			]
		);

		$data                  = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );
		$this->_column_headers = [ $columns, $hidden, $sortable ];
		$this->items           = $data;
	}

	/**
	 * Override the parent columns method. Defines the columns to use in your listing table
	 *
	 * @return Array
	 */
	public function get_columns() {
		return [
			'id'              => 'ID',
			'title'           => 'Title',
			'url'             => 'URL',
			'activated'       => 'License Status',
			'edit' 			  => 'Settings Page',
			'firewall_status' => 'Firewall Status',
			'migration' 	  => 'Rerun Migration'
		];
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	public function get_hidden_columns() {
		return [];
	}

	/**
	 * Define the sortable columns
	 *
	 * @return Array
	 */
	public function get_sortable_columns() {
		return [ 'title' => [ 'title', false ] ];
	}

	/**
	 * Get the table data
	 *
	 * @return Array
	 */
	private function table_data() {
		global $wpdb;
		$data = [];
		$free = get_option( 'patchstack_license_free', 0 ) == 1;
		$nonce = wp_create_nonce( 'patchstack-migration' );

		$blogs_ids = function_exists( 'get_sites' ) ? get_sites() : [];
		foreach ( $blogs_ids as $b ) {
			$site_info = get_blog_details( $b->blog_id );

			// Search functionality
			$match = false;
			if ( isset( $_GET['s'] ) ) {
				$search = (string) $_GET['s'];
				if ( strpos( (string) $b->blog_id, $search ) !== false ) {
					$match = true;
				}

				if ( strpos( (string) $b->blogname, $search ) !== false ) {
					$match = true;
				}

				if ( strpos( (string) $site_info->siteurl, $search ) !== false ) {
					$match = true;
				}
			}

			if ( ! isset( $_GET['s'] ) || $match ) {
				$is_firewall_enabled = get_blog_option( $b->blog_id, 'patchstack_basic_firewall' );
				$is_activated        = get_blog_option( $b->blog_id, 'patchstack_clientid', '' ) != '';
				$prefix          	 = $wpdb->get_blog_prefix( $b->blog_id );

				// Determine if the site has any missing migrations.
				$has_missing = false;
				if ($is_activated) {
					foreach( [ 'patchstack_firewall_log', 'patchstack_logic', 'patchstack_event_log' ] as $table ) {
						$result = $wpdb->get_results("SHOW TABLES LIKE '" . $prefix . $table . "'");
						if ( !$result ) {
							$has_missing = true;
						}
					}
				}

				$data[] = [
					'id'              => (int) $b->blog_id,
					'title'           => esc_html( $b->blogname ),
					'url'             => '<a href="' . esc_url( $site_info->siteurl ). '">' . esc_url( $site_info->siteurl ) . '</a>',
					'activated'       => $is_activated ? 'Activated' : 'Deactivated',
					'edit'			  => '<a href="' . esc_url( $site_info->siteurl ). '/wp-admin/options-general.php?page=patchstack">Settings Page</a>',
					'firewall_status' => $is_firewall_enabled && ! $free ? 'Enabled' : 'Disabled',
					'migration'            => '<a href="' . esc_url( add_query_arg(
						[
							'PatchstackNonce' => $nonce,
							'site'          => $b->blog_id
						]
					) ) . '">' . ($has_missing ? 'Run' : 'Rerun') . ' Database Migration</a>',
				];
			}
		}

		return $data;
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  Array  $item Data
	 * @param  String $column_name - Current column name
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'title':
			case 'url':
			case 'activated':
			case 'firewall_status':
			case 'migration':
			case 'edit':
				return $item[ $column_name ];
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Allows you to sort the data by the variables set in the $_GET
	 *
	 * @param Array $a
	 * @param Array $b
	 * @return Mixed
	 */
	private function sort_data( $a, $b ) {
		// Set defaults
		$orderby = 'id';
		$order   = 'asc';
		$columns = $this->get_columns();

		// If orderby is set, use this as the sort column
		if ( isset( $_GET['orderby'] ) && ! empty( $_GET['orderby'] ) && isset( $columns[$_GET['orderby']] ) ) {
			$orderby = wp_filter_nohtml_kses( $_GET['orderby'] );
		}

		// If order is set use this as the order
		if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) && $_GET['order'] != 'asc' ) {
			$order = 'desc';
		}

		$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
		if ( $order === 'asc' ) {
			return $result;
		}

		return -$result;
	}
}
