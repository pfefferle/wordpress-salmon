<?php
// register
if ( isset( $wp_version ) ) {
	// admin panel
	add_action( 'admin_menu', array( 'SalmonAdminPages', 'addMenuItem' ) );
	if ( is_admin() ) {
		add_action( 'admin_enqueue_scripts', array( 'SalmonAdminPages', 'admin_enqueue_scripts' ) );
	}
}

/**
 * yiid admin panels
 *
 * @author Matthias Pfefferle
 */
class SalmonAdminPages {
	/**
	 * adds the yiid-items to the admin-menu
	 */
	public static function addMenuItem() {
		add_options_page( 'Salmon', 'Salmon', 'manage_options', 'salmon', array( 'SalmonAdminPages', 'showSettings' ) );
	}

	/**
	 * Enqueue plugin install scripts and plugin installer.
	 */
	public static function admin_enqueue_scripts() {
		$screen = get_current_screen();
		if ( $screen->base !== 'settings_page_salmon' ) {
			return;
		}
		require_once( ABSPATH . 'wp-admin/admin.php' );
		require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		wp_enqueue_style( 'plugin-install' );
		wp_enqueue_script( 'plugin-install' );
		add_thickbox();
	}

	/**
	 * displays the yiid settings page
	 */
	public static function showSettings() {
		global $tab;
		?>
		<div class="wrap">
			<h2>Salmon for WordPress</h2>

			<p>"Salmon for WordPress" needs some plugins to work properly.</p>
			<?php
			$plugins          = array();
			$required_plugins = apply_filters( 'ostatus_required_plugins', array(
				'host-meta',
				'webfinger',
				'well-known',
			) );

			foreach ( $required_plugins as $plugin ) {
				$plugins[] = plugins_api( 'plugin_information', array(
					'slug' => $plugin,
					'fields' => array(
						'icons' => true,
						'active_installs' => true,
						'short_description' => true,
					),
				) );
			}

			$tab = 'custom';

			// check wordpress version
			if ( version_compare( get_bloginfo( 'version' ), 3.0, '<=' ) ) {
				display_plugins_table( $plugins );
			} else {
				$wp_list_table = _get_list_table( 'WP_Plugin_Install_List_Table' );
				$wp_list_table->items = $plugins;
				$wp_list_table->display();
			}
			?>
		</div>
		<?php
	}
}

?>
