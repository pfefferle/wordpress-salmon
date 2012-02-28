<?php
/*
Plugin Name: Salmon for WordPress
Plugin URI: http://wordpress.org/extend/plugins/salmon/
Description: Salmon plugin for WordPress.
Version: 0.5
Author: Matthias Pfefferle
Author URI: http://notizblog.org/
*/

/*
 * thanks to Arne Roomann-Kurrik for his inspiration with salmonpress
 * 
 * salmonpress: http://code.google.com/p/salmonpress/
 * Arne Roomann-Kurrik: http://roomanna.com
 */
require_once 'salmon.php';
require_once 'magicsig.php';
require_once 'admin-pages.php';

// Init feeds.
add_action('atom_head', array('SalmonPlugin', 'printAtomFeedHeader'));
add_action('comment_atom_entry', array('SalmonPlugin', 'addCrosspostingExtension'));
add_action('rss2_head', array('SalmonPlugin', 'printRssFeedHeader'));
//add_action('atom_entry', array('SalmonPlugin', 'addSalmonLink'));

// Query handler
add_action('parse_query', array('SalmonPlugin', 'parseQuery'));
add_filter('query_vars', array('SalmonPlugin', 'queryVars'));
add_action('init', array('SalmonPlugin', 'flushRewriteRules'));
add_action('generate_rewrite_rules', array('SalmonPlugin', 'addRewriteRules'));

// xrd discovery
add_action("host_meta_xrd", array("SalmonPlugin", "addXrdDiscovery"));
add_action('webfinger_xrd', array('SalmonPlugin', 'addXrdDiscovery'), 10, 1);
add_action('webfinger_xrd', array('SalmonPlugin', 'addXrdMagicSig'), 10, 1);

// add avatar filter
add_filter('get_avatar', array('SalmonPlugin', 'addSalmonAvatar'), 10, 5);

/**
 * Static class to register callback handlers and otherwise configure
 * WordPress for accepting Salmon posts.
 *
 * @author Matthias Pfefferle
 */
class SalmonPlugin {
  /**
   * show discovery links
   *
   * @param Object $user default null
   */
  function addXrdDiscovery($user = null) {
    $url = SalmonPlugin::generateApiUrl($user);
    echo "<Link rel='salmon' href='$url'/>\n";
    echo "<Link rel='http://salmon-protocol.org/ns/salmon-replies' href='$url' />\n";
    echo "<Link rel='http://salmon-protocol.org/ns/salmon-mention' href='$url' />\n";
  }

  /**
   * adds magic signatures to the webfinger xrd
   *
   * @param Object $user default null
   */
  function addXrdMagicSig($user) {
    $sig = MagicSig::get_public_sig($user->ID);
    $encoded = MagicSig::to_string($sig);

    echo '<Link rel="magic-public-key" href="data:application/magic-public-key,'.$encoded.'"/>';

    echo '<Property xmlns:mk="http://salmon-protocol.org/ns/magic-key"
                    type="http://salmon-protocol.org/ns/magic-key"
                    mk:key_id="1">'.$encoded.'</Property>';
  }
  
  /**
   * adds salmon link to <entry />
   */
  function addSalmonLink() {
    if (function_exists("get_webfinger")) {
      $webfinger = get_webfinger(get_the_author_meta("ID"), true);
      echo '<link rel="salmon" href="'.$webfinger.'" />';
    }
  }
  
  /**
   * generates the enpoint url
   * 
   * @param Object $user|null
   * @return string
   */
  function generateApiUrl($user = null) {
    if ($user) {
      $url = get_author_posts_url($user->ID, $user->user_nicename);
    } else {
      $url = get_bloginfo('wpurl')."/";
    }
    
    if (strstr($url, '?')) {
      $combiner = "&amp;";
    } else {
      $combiner = "?";
    }
    
    $url .= $combiner."salmon=endpoint";

    return $url;
  }
  
  function printAtomFeedHeader() {
    SalmonPlugin::printFeedHeader('atom');
  }
  
  function printRssFeedHeader() {
    SalmonPlugin::printFeedHeader('rss');
  }

  /**
   * Prints the link pointing to the salmon endpoint to a syndicated feed.
   */
  function printFeedHeader($type) {
    if (is_author()) {
      if (get_query_var('author_name')) :
        $user = get_user_by('slug', get_query_var('author_name'));
      else :
        $user = get_userdata(get_query_var('author'));
      endif;
    } else {
      $user = null;
    }
    
    // add namespace to rss files
    if ($type == 'rss') {
      $namespace = "atom:";
    } else {
      $namespace = "";
    }

    echo "<".$namespace."link rel='salmon' href='".SalmonPlugin::generateApiUrl($user)."'/>";
    echo "<".$namespace."link rel='http://salmon-protocol.org/ns/salmon-replies' href='".SalmonPlugin::generateApiUrl($user)."'/>";
    echo "<".$namespace."link rel='http://salmon-protocol.org/ns/salmon-mention' href='".SalmonPlugin::generateApiUrl($user)."'/>";
  }

  /**
   * Checks a query for the 'SalmonPlugin' parameter and attempts to parse a
   * Salmon post if the parameter exists.
   */
  function parseQuery($wp_query) {
    if (isset($wp_query->query_vars['salmon']) ||
        isset($wp_query->query_vars['salmonpress'])) {
      SalmonPlugin::parseSalmonPost();
    }
  }
  
  /**
   * displays facebook avatars for the custom comment type "facebook"
   *
   * @param string $avatar the original img tag
   * @return string the new image tag
   */
  function addSalmonAvatar($avatar, $id_or_email, $size, $default, $alt = '') {
    if (!is_object($id_or_email) || !isset($id_or_email->comment_type) || get_comment_meta($id_or_email->comment_ID, '_comment_type', true) != 'salmon') {
      return $avatar;
    }
  
    $avatars = get_comment_meta($id_or_email->comment_ID, '_salmon_avatars', true);
    
    if (!$avatars) {
      return $avatar;
    }
	
  	if (array_key_exists($size, $avatars)) {
  	  $url = $avatars[$size];
  	} else {
        $url = $avatars[min(array_diff(array_keys($avatars),range(0,$size)))];	
  	}
      
    if ( false === $alt )
      $safe_alt = '';
    else
      $safe_alt = esc_attr( $alt );
  
    $avatar = "<img alt='{$safe_alt}' src='{$url}' class='avatar avatar-{$size} photo avatar-facebook' height='{$size}' width='{$size}' />";
    return $avatar;
  }
  
  /**
   * adds the crossposting extension to the feeds
   *
   * @param int $commentId
   */
  function addCrosspostingExtension($commentId) {
  	// get comment
  	$comment = get_comment($commentId);
  	// check if comment-type is 'salmon'
  	if (get_comment_meta($comment->comment_ID, '_comment_type', true) == true) {
  	  $id = get_comment_meta($comment->comment_ID, '_crossposting_id', true);
  	  $link = get_comment_meta($comment->comment_ID, '_crossposting_link', true);
  	  
  	  // add extension if id is set
      if ($id) {
      	echo '<crosspost:source xmlns:crosspost="http://purl.org/syndication/cross-posting">'."\n";
        echo '  <id>'.$id.'</id>'."\n";
        if ($link) {
          echo '  <link rel="alternate" type="text/html" href="'.$link.'" />'."\n";
        }
      	echo '</crosspost:source>'."\n";
      }
    }
  }
  

  /**
   * Adds the 'SalmonPlugin' query variable to wordpress.
   */
  function queryVars($queryvars) {
    $queryvars[] = 'salmon';
    $queryvars[] = 'salmonpress';
    return $queryvars;
  }

  /**
   * Clears the cached rewrite rules so that we may add our own.
   */
  function flushRewriteRules() {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }

  /**
   * Adds a rewrite rule so that http://mysite.com/index.php?SalmonPlugin=true
   * can be rewritten as http://mysite.com/SalmonPlugin
   */
  function addRewriteRules($wp_rewrite) {
    global $wp_rewrite;
    $new_rules = array('salmon/?(.+)' => 'index.php?salmon=' . $wp_rewrite->preg_index(1),
                       'salmon' => 'index.php?salmon=endpoint');
    $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
  }

  /**
   * Attempts to parse data sent to the Salmon endpoint and post it as a
   * comment for the current blog.
   */
  public function parseSalmonPost() {
    $user = null;
    // get user by url
    if(get_query_var('author_name')) :
      $user = get_user_by('slug', get_query_var('author_name'));
    else :
      $user = get_userdata(get_query_var('author'));
    endif;

    // Allow cross domain JavaScript requests, from salmon-playground.
    if (strtoupper($_SERVER['REQUEST_METHOD']) == "OPTIONS" &&
        strtoupper($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) == "POST") {
      // See https://developer.mozilla.org/En/HTTP_access_control
      header('HTTP/1.1 200 OK');
      header('Access-Control-Allow-Origin: * ');
      die();
    }

    if (strtoupper($_SERVER['REQUEST_METHOD']) != "POST") {
      header('HTTP/1.1 400 Bad Request');
      echo SalmonPlugin::endpoint("Error: The posted Salmon entry was malformed.");
    }

    $requestBody = @file_get_contents('php://input');

    $env = MagicSig::parse($requestBody);

    // Validate the request if the option is set.
    if (get_option('salmon_validate')) {
      if ($entry->validate() === false) {
        header('HTTP/1.1 403 Forbidden');
        SalmonPlugin::endpoint("Error: The posted Salmon entry was malformed.");
      }
    }

    $data = MagicSig::base64_url_decode($env['data']);

//error_log(print_r($data, true)."\n", 3, dirname(__FILE__) . "/log.txt");

    do_action('salmon_atom_data', $data, $user);
    $entry = SalmonEntry::from_atom($data);

    $commentdata = $entry->to_commentdata();
    do_action('salmon_comment_data', $commentdata, $user);

    if ($user) {
      wp_mail($user->user_email, 'you\'ve fished a salmon', strip_tags($commentdata['comment_content'].'\n\nfrom: '.$commentdata['author_name'].': '.$commentdata['author_uri']));
    }

    if ($commentdata['comment_post_ID'] == '') {
      header('HTTP/1.1 200 OK');
    //  print "The posted Salmon entry was malformed.";
    //} else if (!isset($commentdata['user_id'])) {
    //  if (get_option('comment_registration')) {
    //    header('HTTP/1.1 403 Forbidden');
    //    SalmonPlugin::endpoint("Error: The blog settings only allow registered users to post comments.");
    //  }
    } else {
      // save comment
      $commentId = wp_insert_comment($commentdata);
      // add comment meta
      update_comment_meta($commentId, '_salmon_avatars', $entry->avatars);
      update_comment_meta($commentId, '_comment_type', 'salmon');
      update_comment_meta($commentId, '_crossposting_id', $entry->id);
      update_comment_meta($commentId, '_crossposting_link', $entry->link);
      header('HTTP/1.1 200 OK');
      SalmonPlugin::endpoint("The Salmon entry was posted.");
    }
    die();
  }

  /**
   *
   */
  function endpoint($text) {
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) ) language_attributes(); ?>>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Salmon Endpoint</title>
<?php
  wp_admin_css('install', true);
  do_action('admin_head');
?>
</head>
<body>
  <h1>Salmon Endpoint</h1>

  <p><?php echo $text ?></p>
</body>
</html>
<?php
    exit;
  }
}