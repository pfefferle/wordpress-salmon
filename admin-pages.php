<?php
// register
if (isset($wp_version)) {
  // admin panel
  add_action('admin_menu', array('SalmonAdminPages', 'addMenuItem'));
}

if (is_admin() && $_GET['page'] == 'salmon') {
  require_once(ABSPATH . 'wp-admin/admin.php');
  require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
  wp_enqueue_style( 'plugin-install' );
  wp_enqueue_script( 'plugin-install' );
  add_thickbox();
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
  function addMenuItem() {
    add_options_page('Salmon', 'Salmon', 10, 'salmon', array('SalmonAdminPages', 'showSettings'));
  }

  /**
   * displays the yiid settings page
   */
  function showSettings() {
?>
  <div class="wrap">
    <h2>Salmon for WordPress</h2>
    
    <p>"Salmon for WordPress" needs some plugins to work properly.</p>
<?php  
  $plugins = array();
  $plugins[] = plugins_api('plugin_information', array('slug' => 'host-meta'));
  $plugins[] = plugins_api('plugin_information', array('slug' => 'webfinger'));
  $plugins[] = plugins_api('plugin_information', array('slug' => 'well-known'));
  
  // check wordpress version
  if (get_bloginfo('version') <= 3.0) {
    display_plugins_table($plugins);
  } else {
    $wp_list_table = _get_list_table('WP_Plugin_Install_List_Table');
    $wp_list_table->items = $plugins;
    $wp_list_table->display();
  }
?>
  </div>    
<?php
  }
}
?>