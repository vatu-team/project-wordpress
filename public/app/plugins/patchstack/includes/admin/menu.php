<?php

// Do not allow the file to be called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class used for the admin menu and to enqueue styles and/or scripts.
 */
class P_Admin_Menu extends P_Core {

	/**
	 * Add the actions required for the admin menu.
	 *
	 * @param Patchstack $core
	 * @return void
	 */
	public function __construct( $core ) {
		parent::__construct( $core );

		if ( defined( 'PS_DISABLE_MENU' ) && PS_DISABLE_MENU ) {
			return;
		}

		add_action( 'admin_head', [ $this, 'add_meta_nonce' ] );
		add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
		add_action( 'network_admin_menu', [ $this, 'network_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'plugin_action_links_' . $this->plugin->basename, [ $this, 'admin_settings' ] );
	}

	/**
	 * Add Patchstack nonce as meta tag so we can access it in our JavaScript files.
	 *
	 * @return void
	 */
	public function add_meta_nonce() {
		$screen = get_current_screen();
		if ( current_user_can( 'manage_options' ) && isset( $screen->base ) && stripos( $screen->base, 'patchstack' ) !== false ) {
			echo '<meta name="patchstack_nonce" value="' . wp_create_nonce( 'patchstack-nonce' ) . '">';
		}
	}

	/**
	 * Register the wp-admin menu items.
	 *
	 * @return void
	 */
	public function add_menu_pages() {
		add_submenu_page( $this->plugin->name, 'Main', esc_attr__( 'Settings', 'patchstack' ), 'manage_options', $this->plugin->name, [ $this, 'render_settings_page' ] );
		add_options_page( 'Security', 'Security', 'manage_options', $this->plugin->name );
	}

	/**
	 * Register the menu pages for multisite/networks.
	 *
	 * @return void
	 */
	public function network_menu() {
		add_menu_page( 'Patchstack', 'Patchstack', 'manage_options', 'patchstack-multisite', [ $this->plugin->multisite, 'sites_section_callback' ] );
		add_submenu_page( 'patchstack-multisite', 'Activate', 'Activate', 'manage_options', 'patchstack-multisite-settings', [ $this, 'render_settings_page' ] );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		require dirname( __FILE__ ) . '/../views/pages/settings.php';
	}

	/**
	 * Register the stylesheets for the backend.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		$screen = get_current_screen();

		// Load the Patchstack style on all Patchstack pages except site overview.
		if ( isset( $screen->base, $_GET['page'] ) && stripos( $screen->base, 'patchstack' ) !== false && $_GET['page'] != 'patchstack-multisite' ) {
			wp_enqueue_style( 'patchstack', $this->plugin->url . 'assets/css/patchstack.min.css', [], $this->plugin->version );
			wp_enqueue_style( 'patchstack-hint', 'https://cdnjs.cloudflare.com/ajax/libs/hint.css/3.0.0/hint.min.css', [], $this->plugin->version );
		}
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$screen = get_current_screen();
		if ( isset( $screen->base ) && ( stripos( $screen->base, 'patchstack' ) !== false ) && current_user_can( 'manage_options' ) ) {
			wp_enqueue_script( 'patchstack', $this->plugin->url . 'assets/js/patchstack.min.js', [ 'jquery' ], $this->plugin->version );
			wp_enqueue_script( 'google-jsapi', 'https://www.google.com/jsapi' );
			wp_localize_script(
				'patchstack',
				'PatchstackVars',
				[
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'patchstack-nonce' ),
					'error_message' => esc_attr__( 'Sorry, there was a problem processing your request.', 'patchstack' ),
				]
			);
		}
	}

	/**
	 * Add the "Settings" hyperlink to the Patchstack section on the plugins page of WordPress.
	 *
	 * @param array $links
	 * @return array
	 */
	public function admin_settings( $links ) {
		return array_merge( [ '<a href="admin.php?page=patchstack&tab=license">' . esc_attr__( 'Settings', 'patchstack' ) . '</a>' ], $links );
	}
}
