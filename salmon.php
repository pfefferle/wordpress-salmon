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

require_once 'includes/functions.php';
require_once 'includes/class-salmon-entry.php';

add_action( 'init', array( 'SalmonPlugin', 'init' ) );

/**
 * Static class to register callback handlers and otherwise configure
 * WordPress for accepting Salmon posts.
 *
 * @author Matthias Pfefferle
 */
class SalmonPlugin {
	public static function init() {
		// Init feeds.
		add_action( 'atom_head', array( 'SalmonPlugin', 'print_feed_header' ) );
		add_action( 'comment_atom_entry', array( 'SalmonPlugin', 'add_crossposting_extension' ) );
		add_action( 'rss2_head', array( 'SalmonPlugin', 'print_feed_header' ) );

		// Query handler
		add_action( 'parse_query', array( 'SalmonPlugin', 'parse_query' ) );
		add_filter( 'query_vars', array( 'SalmonPlugin', 'query_vars' ) );
		add_action( 'init', array( 'SalmonPlugin', 'flush_rewrite_rules' ) );
		add_action( 'generate_rewrite_rules', array( 'SalmonPlugin', 'add_rewrite_rules' ) );

		// xrd discovery
		//add_filter( 'host_meta', array( 'SalmonPlugin', 'add_jrd_discovery' ) );
		add_filter( 'webfinger_user_data', array( 'SalmonPlugin', 'add_jrd_discovery' ), 10, 3 );
		add_filter( 'webfinger_user_data', array( 'SalmonPlugin', 'add_magic_sig' ), 10, 3 );

		// add avatar filter
		add_filter( 'pre_get_avatar_data', array( 'SalmonPlugin', 'pre_get_avatar_data' ), 11, 2 );

		add_action( 'rest_api_init', array( 'SalmonPlugin', 'rest_api_init' ), 10, 5 );
	}

	/**
	 * show discovery links
	 *
	 * @param Object $user default null
	 */
	public static function add_jrd_discovery( $jrd, $resource, $user ) {
		$api_endpoint = get_rest_url( null, '/salmon/0.0/endpoint' );

		$jrd['links'][] = array(
			'rel' => 'salmon',
			'href' => $api_endpoint,
		);

		$jrd['links'][] = array(
			'rel' => 'http://salmon-protocol.org/ns/salmon-replies',
			'href' => $api_endpoint,
		);

		$jrd['links'][] = array(
			'rel' => 'http://salmon-protocol.org/ns/salmon-mention',
			'href' => $api_endpoint,
		);

		return $jrd;
	}

	/**
	 * adds magic signatures to the webfinger xrd
	 *
	 * @param Object $user default null
	 */
	public static function add_magic_sig( $jrd, $resource, $user ) {
		$magic_key = salmon_get_magic_key( $user->ID );

		$jrd['links'][] = array(
			'rel' => 'magic-public-key',
			'href' => sprintf( 'data:application/magic-public-key,%s', $magic_key ),
		);

		$jrd['properties'][] = array(
			'http://salmon-protocol.org/ns/magic-key' => $magic_key,
		);

		return $jrd;
	}

	public static function rest_api_init() {
		register_rest_route( 'salmon/0.0', '/endpoint', array(
			'methods' => 'POST',
			'callback' => array( 'SalmonPlugin', 'post' ),
		) );
	}

	public static function post( $request ) {

	}

	/**
	 * Prints the link pointing to the salmon endpoint to a syndicated feed.
	 */
	public static function print_feed_header() {
		$namespace = '';

		if ( is_feed( 'rss' ) ) {
			$namespace = 'atom:';
		}

		$api_endpoint = get_rest_url( null, '/salmon/0.0/endpoint' );

		printf( '<%slink rel="salmon" href="%s" />', $namespace, $api_endpoint );
		printf( '<%slink rel="http://salmon-protocol.org/ns/salmon-replies" href="%s" />', $namespace, $api_endpoint );
		printf( '<%slink rel="http://salmon-protocol.org/ns/salmon-mention" href="%s" />', $namespace, $api_endpoint );
	}

	/**
	 * Checks a query for the 'SalmonPlugin' parameter and attempts to parse a
	 * Salmon post if the parameter exists.
	 */
	public static function parse_query( $wp_query ) {
		if ( isset( $wp_query->query_vars['salmon'] ) ||
			isset( $wp_query->query_vars['salmonpress'] ) ) {
			SalmonPlugin::parse_salmon_post();
		}
	}

	/**
	 * Replaces the default avatar with the Salmon photo
	 *
	 * @param array             $args Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object
	 *
	 * @return array $args
	 */
	public static function pre_get_avatar_data( $args, $id_or_email ) {
		if ( ! isset( $args['class'] ) ) {
			$args['class'] = array( 'u-photo' );
		} else {
			$args['class'][] = 'u-photo';
		}
		if ( ! is_object( $id_or_email ) ||
			! isset( $id_or_email->comment_type ) ||
			! get_comment_meta( $id_or_email->comment_ID, '_salmon_avatars', true ) ) {
			return $args;
		}

		$avatars = get_comment_meta( $id_or_email->comment_ID, '_salmon_avatars', true );

		if ( ! $avatars ) {
			return $avatar;
		}

		if ( array_key_exists( $size, $avatars ) ) {
			$url = $avatars[ $size ];
		} else {
			$url = $avatars[ min( array_diff( array_keys( $avatars ), range( 0, $size ) ) ) ];
		}

		if ( $avatar ) {
			$args['url'] = $url;
			$args['class'][] = 'avatar-salmon';
		}

		return $args;
	}

	/**
	 * adds the crossposting extension to the feeds
	 *
	 * @param int $comment_id
	 */
	public static function add_crossposting_extension( $comment_id ) {
		// get comment
		$comment = get_comment( $comment_id );
		// check if comment-type is 'salmon'
		if ( get_comment_meta( $comment->comment_ID, '_comment_type', true ) != true ) {
			return false;
		}

		$id = get_comment_meta( $comment->comment_ID, '_crossposting_id', false );

		// add extension if id is set
		if ( ! $id ) {
			return false;
		}

		echo '<crosspost:source xmlns:crosspost="http://purl.org/syndication/cross-posting">' . PHP_EOL;
		echo '  <id>' . $id . '</id>' . PHP_EOL;

		$link = get_comment_meta( $comment->comment_ID, '_crossposting_link', true );
		if ( $link ) {
			echo '  <link rel="alternate" type="text/html" href="' . $link . '" />' . PHP_EOL;
		}

		echo '</crosspost:source>' . PHP_EOL;
	}


	/**
	 * Adds the 'SalmonPlugin' query variable to wordpress.
	 */
	public static function query_vars( $vars ) {
		$vars[] = 'salmon';
		$vars[] = 'salmonpress';

		return $vars;
	}

	/**
	 * Clears the cached rewrite rules so that we may add our own.
	 */
	public static function flush_rewrite_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	  /**
	   * Adds a rewrite rule so that http://mysite.com/index.php?SalmonPlugin=true
	   * can be rewritten as http://mysite.com/SalmonPlugin
	   */
	public static function add_rewrite_rules( $wp_rewrite ) {
		global $wp_rewrite;
		$new_rules = array(
			'salmon/?(.+)' => 'index.php?salmon=' . $wp_rewrite->preg_index( 1 ),
			'salmon' => 'index.php?salmon=endpoint',
		);

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}

	/**
	 * Attempts to parse data sent to the Salmon endpoint and post it as a
	 * comment for the current blog.
	 */
	public static function parse_salmon_post() {
		$user = null;

		// get user by url
		if ( get_query_var( 'author_name' ) ) :
			$user = get_user_by( 'slug', get_query_var( 'author_name' ) );
		else :
			$user = get_userdata( get_query_var( 'author' ) );
		endif;

		// Allow cross domain JavaScript requests, from salmon-playground.
		if ( strtoupper( 'OPTIONS' == $_SERVER['REQUEST_METHOD'] )  &&
			strtoupper( 'POST' == $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ) ) {
			// See https://developer.mozilla.org/En/HTTP_access_control
			header( 'HTTP/1.1 200 OK' );
			header( 'Access-Control-Allow-Origin: * ' );
			die();
		}

		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) != 'POST' ) {
			header( 'HTTP/1.1 400 Bad Request' );
			echo SalmonPlugin::endpoint( 'Error: The posted Salmon entry was malformed.' );
		}

		$request_body = file_get_contents( 'php://input' );

		$env = MagicSig::parse( $request_body );

		// Validate the request if the option is set.
		if ( get_option( 'salmon_validate' ) ) {
			if ( false === $entry->validate() ) {
				header( 'HTTP/1.1 403 Forbidden' );
				SalmonPlugin::endpoint( 'Error: The posted Salmon entry was malformed.' );
			}
		}

		$data = base64url_decode( $env['data'] );

		//error_log(print_r($data, true)."\n", 3, dirname(__FILE__) . "/log.txt");

		do_action( 'salmon_atom_data', $data, $user );
		$entry = SalmonEntry::from_atom( $data );

		$commentdata = $entry->to_commentdata();
		do_action( 'salmon_comment_data', $commentdata, $user );

		if ( $user ) {
			wp_mail( $user->user_email, 'you\'ve fished a salmon', strip_tags( $commentdata['comment_content'] . PHP_EOL . PHP_EOL . 'from: ' . $commentdata['author_name'] . ': ' . $commentdata['author_uri'] ) );
		}

		if ( '' == $commentdata['comment_post_ID'] ) {
			header( 'HTTP/1.1 200 OK' );
			//  print "The posted Salmon entry was malformed.";
			//} else if (!isset($commentdata['user_id'])) {
			//  if (get_option('comment_registration')) {
			//    header('HTTP/1.1 403 Forbidden');
			//    SalmonPlugin::endpoint("Error: The blog settings only allow registered users to post comments.");
			//  }
		} else {
			// save comment
			$comment_id = wp_insert_comment( $commentdata );
			// add comment meta
			update_comment_meta( $comment_id, '_salmon_avatars', $entry->avatars );
			update_comment_meta( $comment_id, '_comment_type', 'salmon' );
			update_comment_meta( $comment_id, '_crossposting_id', $entry->id );
			update_comment_meta( $comment_id, '_crossposting_link', $entry->link );
			header( 'HTTP/1.1 200 OK' );
			SalmonPlugin::endpoint( 'The Salmon entry was posted.' );
		}
		die();
	}

	/**
	 *
	 */
	public static function endpoint( $text ) {
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) ) { language_attributes(); } ?>>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<title>Salmon Endpoint</title>
	<?php
		wp_admin_css( 'install', true );
		do_action( 'admin_head' );
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
