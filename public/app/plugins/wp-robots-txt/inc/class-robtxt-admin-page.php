<?php
/**
 * WP Robots Txt
 *
 * Copyright 2013  George Pattichis (gpattihis@gmail.com)
 *
 * "WP Robots Txt" is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * "WP Robots Txt" is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * "along with WP Robots Txt". If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
 *
 * @category    WordPress
 * @package     WPRobotsTxt
 * @author      George Pattichis
 * @copyright   2013 George Pattichis
 * @license     http://opensource.org/licenses/GPL-2.0 GPL-2.0+
 */

/**
 * Wrapper for all our admin area functionality.
 *
 * @since 0.1
 */
class ROBTXT_Admin_Page {

	/**
	 * The contents of the text-area.
	 *
	 * @since    1.2
	 * @access   private
	 * @var      string    $ins
	 */
	private static $ins = null;

	/**
	 * The name of our option.
	 *
	 * @since    1.2
	 * @access   protected
	 * @var      string    $setting
	 */
	protected $setting = 'robtxt_content';

	/**
	 * Get an instance of the class.
	 *
	 * @since   1.2
	 * @access  public
	 * @return  object
	 */
	public static function instance() {
		if ( null === self::$ins ) {
			self::$ins = new self();
		}

		return self::$ins;
	}

	/**
	 * Initialize our plugin.
	 *
	 * @since   1.2
	 * @access  public
	 * @uses    add_action
	 * @return  void
	 */
	public static function init() {
		add_action( 'admin_init', array( self::instance(), 'settings' ) );

		// Backwards compatibility.
		$old = get_option( 'cd_rdte_content' );
		if ( false !== $old ) {
			update_option( self::instance()->setting, $old );
			delete_option( 'cd_rdte_content' );
		}

		add_filter( 'plugin_action_links', array( self::instance(), 'robtxt_action_links' ), 10, 2 );
	}

	/**
	 * Registers our setting and takes care of adding the settings field
	 * we need to edit our robots.txt file
	 *
	 * @since   1.2
	 * @access  public
	 * @uses    register_setting
	 * @uses    add_settings_field
	 * @return  void
	 */
	public function settings() {
		register_setting(
			'reading',
			$this->setting,
			array( $this, 'robtxt_clean_setting' )
		);

		add_settings_section(
			'robots-txt',
			__( 'Robots.txt Content', 'wp-robots-txt' ),
			'__return_false',
			'reading'
		);

		add_settings_field(
			'robtxt_robots_content',
			__( 'Robots.txt Content', 'wp-robots-txt' ),
			array( $this, 'field' ),
			'reading',
			'robots-txt',
			array( 'label_for' => $this->setting )
		);
	}

	/**
	 * Callback for the settings field.
	 *
	 * @since   1.2
	 * @access  public
	 * @uses    get_option
	 * @uses    esc_attr
	 * @return  void
	 */
	public function field() {
		$content = get_option( $this->setting );
		if ( ! $content ) {
			$content = $this->robtxt_get_default_robots();
		}

		printf(
			'<textarea name="%1$s" id="%1$s" rows="10" class="large-text">%2$s</textarea>',
			esc_attr( $this->setting ),
			esc_textarea( $content )
		);

		$robots_link = '<a href="' . site_url() . '/robots.txt" target="_blank">robots.txt</a>';
		echo '<p class="description">';
		/* translators: %s is the link to see your robots.txt file */
		echo wp_kses( sprintf( __( 'The content of your %s file. Delete the above and save to restore the default.', 'wp-robots-txt' ), ( $robots_link ) ), 'post' );
		echo '</p>';
	}

	/**
	 * Strips tags and escapes any html entities that goes into the
	 * robots.txt field
	 *
	 * @since 1.2
	 * @param string $contents The contents of the text-area.
	 * @uses esc_html
	 * @uses add_settings_error
	 */
	public function robtxt_clean_setting( $contents ) {
		if ( empty( $contents ) ) {
			add_settings_error(
				$this->setting,
				'robtxt-restored',
				__( 'Robots.txt restored to default.', 'wp-robots-txt' ),
				'success'
			);
		}

		return esc_html( wp_strip_all_tags( $contents ) );
	}

	/**
	 * Get the default robots.txt content.
	 *
	 * @since   1.2
	 * @access  protected
	 * @uses    get_option
	 * @return  string The default robots.txt content
	 */
	protected function robtxt_get_default_robots() {
		$public = get_option( 'blog_public' );

		$output = "User-agent: *\n";
		if ( '0' === $public ) {
			$output .= "Disallow: /\n";
		} else {
			$site_url = wp_parse_url( site_url(), PHP_URL_PATH );
			$path     = ( ! empty( $site_url['path'] ) ) ? $site_url['path'] : '';
			$output  .= "Disallow: $path/wp-admin/\n";
			$output  .= "Allow: $path/wp-admin/admin-ajax.php\n";
			$output  .= "\nSitemap: " . esc_url( ( new WP_Sitemaps() )->index->get_index_url() ) . "\n";
		}

		return $output;
	}

	/**
	 * Show custom links in Plugins Page
	 *
	 * @since  1.2
	 * @access public
	 * @param  array $links Default Links.
	 * @param  array $file Plugin's root filepath.
	 * @return array Links list to display in plugins page.
	 */
	public function robtxt_action_links( $links, $file ) {

		if ( WP_ROBOTS_TXT_BASENAME === $file ) {
			$wpr_links = '<a href="' . get_admin_url() . 'options-reading.php" title="Edit your robots.txt">' . __( 'Settings', 'wp-robots-txt' ) . '</a>';
			$wpr_visit = '<a href="https://gp-web.dev/" title="Contact" target="_blank" >' . __( 'Contact', 'wp-robots-txt' ) . '</a>';
			array_unshift( $links, $wpr_visit );
			array_unshift( $links, $wpr_links );
		}

		return $links;
	}
}
