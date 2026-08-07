<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is used to log any plugin related action.
 */
class P_Event_Plugins extends P_Event_Log {

	/**
	 * Hook into the actions so we can log it.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'activated_plugin', [ &$this, 'activated_plugin' ] );
		add_action( 'deactivated_plugin', [ &$this, 'deactivated_plugin' ] );
		add_action( 'upgrader_process_complete', [ &$this, 'plugin_install_or_update' ], 10, 2 );
	}

	/**
	 * Log the action regarding the plugin.
	 *
	 * @param string $action
	 * @param string $plugin_name
	 * @return void
	 */
	protected function _add_log_plugin( $action, $plugin_name ) {
		// Get the plugin name if it is a path.
		if ( strpos( $plugin_name, '/' ) !== false ) {
			$plugin_dir  = explode( '/', $plugin_name );
			$plugin_data = array_values( get_plugins( '/' . $plugin_dir[0] ) );
			$plugin_data = array_shift( $plugin_data );
			$plugin_name = $plugin_data['Name'];
		}

		$this->insert(
			[
				'action'      => $action,
				'object'      => 'plugin',
				'object_id'   => 0,
				'object_name' => esc_html( $plugin_name ),
			]
		);
	}

	/**
	 * When a plugin is de-activated.
	 *
	 * @param string $plugin_name
	 * @return void
	 */
	public function deactivated_plugin( $plugin_name ) {
		$this->_add_log_plugin( 'deactivated', $plugin_name );
	}

	/**
	 * When a plugin is activated.
	 *
	 * @param string $plugin_name
	 * @return void
	 */
	public function activated_plugin( $plugin_name ) {
		$this->_add_log_plugin( 'activated', $plugin_name );
	}

	/**
	 * When a plugin is installed or updated.
	 *
	 * @param Plugin_Upgrader $upgrader
	 * @param array           $extra
	 * @return void
	 */
	public function plugin_install_or_update( $upgrader, $extra ) {
		if ( ! isset( $extra['type'] ) || $extra['type'] !== 'plugin' ) {
			return;
		}

		// If plugin is installed.
		if ( $extra['action'] === 'install' ) {
			$path = $upgrader->plugin_info();
			if ( ! $path ) {
				return;
			}

			$data = get_plugin_data( $upgrader->skin->result['local_destination'] . '/' . $path, true, false );
			$this->_add_log_plugin( 'installed', $data['Name'] );
		}

		// If plugin is updated.
		if ( $extra['action'] === 'update' ) {
			// Bulk update?
			if ( isset( $extra['bulk'] ) && $extra['bulk'] == true ) {
				$slugs = $extra['plugins'];
			} else {
				if ( ! isset( $upgrader->skin->plugin ) ) {
					return;
				}

				$slugs = [ $upgrader->skin->plugin ];
			}

			// Loop through all updated plugins.
			foreach ( $slugs as $slug ) {
				$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $slug, true, false );
				$this->_add_log_plugin( 'updated', $data['Name'] );
			}
		}
	}
}
