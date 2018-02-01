<?php
require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
wp_enqueue_style( 'plugin-install' );
wp_enqueue_script( 'plugin-install' );
add_thickbox();
$GLOBALS['tab'] = 'custom';

?>
<div class="wrap">
	<h2><?php esc_html_e( 'Salmon', 'salmon' ); ?></h2>

	<p><strong><?php esc_html_e( 'As updates and content flow in real time around the Web, conversations around the content are becoming increasingly fragmented into individual silos.  Salmon aims to define a standard protocol for comments and annotations to swim upstream to original update sources -- and spawn more commentary in a virtuous cycle.  It\'s open, decentralized, abuse resistant, and user centric.', 'salmon' ); ?></strong></p>

	<h3><?php esc_html_e( 'Dependencies', 'salmon' ); ?></h3>
<?php
$plugins = array();

$required_plugins = apply_filters( 'salmon_required_plugins', array(
	'host-meta',
	'webfinger',
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

// check wordpress version
$wp_list_table = _get_list_table( 'WP_Plugin_Install_List_Table' );
$wp_list_table->items = $plugins;
$wp_list_table->display();
?>

	<h3><?php esc_html_e( 'Further readings', 'salmon' ); ?></h3>
	<ul>
		<li><a href="http://www.salmon-protocol.org/" target="_blank"><?php esc_html_e( 'Spec page', 'salmon' ); ?></a></li>
		<li><a href="https://github.com/pfefferle/wordpress-salmon/issues" target="_blank"><?php esc_html_e( 'Give us feedback', 'salmon' ); ?></a></li>
	</ul>
</div>
