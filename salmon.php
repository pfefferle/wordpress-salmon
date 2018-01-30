<?php
/**
 * Plugin Name: Salmon for WordPress
 * Plugin URI: https://github.com/pfefferle/wordpress-salmon
 * Description: Salmon plugin for WordPress.
 * Version: 0.9
 * Author: Matthias Pfefferle
 * Author URI: https://notiz.blog/
 */

/*
 * thanks to Arne Roomann-Kurrik for his inspiration with salmonpress
 *
 * salmonpress: http://code.google.com/p/salmonpress/
 * Arne Roomann-Kurrik: http://roomanna.com
 */

add_action( 'plugins_loaded', array( 'Salmon_Plugin', 'init' ) );

/**
 * Static class to register callback handlers and otherwise configure
 * WordPress for accepting Salmon posts.
 *
 * @author Matthias Pfefferle
 */
class Salmon_Plugin {

	public static function init() {
		require_once 'includes/functions.php';

		require_once 'includes/class-salmon-entry.php';

		// add salmon discovery
		require_once 'includes/class-salmon-discovery.php';
		Salmon_Discovery::init();

		require_once 'includes/class-magic-sig.php';
		require_once 'admin-pages.php';

		// Query handler
		add_action( 'parse_query', array( 'Salmon_Plugin', 'parse_query' ) );
		add_filter( 'query_vars', array( 'Salmon_Plugin', 'query_vars' ) );

		add_action( 'init', array( 'Salmon_Plugin', 'flush_rewrite_rules' ) );
		add_action( 'generate_rewrite_rules', array( 'Salmon_Plugin', 'add_rewrite_rules' ) );

		// add avatar filter
		add_filter( 'get_avatar', array( 'Salmon_Plugin', 'add_salmon_avatar' ), 10, 5 );

		add_action( 'comment_atom_entry', array( 'Salmon_Plugin', 'add_crossposting_extension' ) );
	}

	/**
	 * generates the enpoint url
	 *
	 * @param Object $user |null
	 *
	 * @return string
	 */
	public static function generate_api_url( $user = null ) {
		if ( $user ) {
			$url = add_query_arg( 'salmon', 'endpoint', get_author_posts_url( $user->ID, $user->user_nicename ) );
		} else {
			$url = site_url( '/?salmon=endpoint' );
		}

		return $url;
	}

	/**
	 * Checks a query for the 'Salmon_Plugin' parameter and attempts to parse a
	 * Salmon post if the parameter exists.
	 */
	public static function parse_query( $wp_query ) {
		if ( isset( $wp_query->query_vars['salmon'] ) ||
				 isset( $wp_query->query_vars['salmonpress'] )
		) {
			Salmon_Plugin::parse_salmon_post();
		}
	}

	/**
	 * displays facebook avatars for the custom comment type "facebook"
	 *
	 * @param string $avatar the original img tag
	 *
	 * @return string the new image tag
	 */
	public static function add_salmon_avatar( $avatar, $id_or_email, $size, $default, $alt = '' ) {
		if ( ! is_object( $id_or_email ) || ! isset( $id_or_email->comment_type ) || get_comment_meta( $id_or_email->comment_ID, '_comment_type', true ) != 'salmon' ) {
			return $avatar;
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

		if ( false === $alt ) {
			$safe_alt = '';
		} else {
			$safe_alt = esc_attr( $alt );
		}

		$avatar = "<img alt='{$safe_alt}' src='{$url}' class='avatar avatar-{$size} photo avatar-facebook' height='{$size}' width='{$size}' />";

		return $avatar;
	}

	/**
	 * adds the crossposting extension to the feeds
	 *
	 * @param int $commentId
	 */
	public static function add_crossposting_extension( $commentId ) {
		// get comment
		$comment = get_comment( $commentId );
		// check if comment-type is 'salmon'
		if ( get_comment_meta( $comment->comment_ID, '_comment_type', true ) == true ) {
			$id   = get_comment_meta( $comment->comment_ID, '_crossposting_id', true );
			$link = get_comment_meta( $comment->comment_ID, '_crossposting_link', true );

			// add extension if id is set
			if ( $id ) {
				echo '<crosspost:source xmlns:crosspost="http://purl.org/syndication/cross-posting">' . "\n";
				echo '  <id>' . $id . '</id>' . "\n";
				if ( $link ) {
					echo '  <link rel="alternate" type="text/html" href="' . $link . '" />' . "\n";
				}
				echo '</crosspost:source>' . "\n";
			}
		}
	}


	/**
	 * Adds the 'Salmon_Plugin' query variable to wordpress.
	 */
	public static function query_vars( $queryvars ) {
		$queryvars[] = 'salmon';
		$queryvars[] = 'salmonpress';

		return $queryvars;
	}

	/**
	 * Clears the cached rewrite rules so that we may add our own.
	 */
	public static function flush_rewrite_rules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	/**
	 * Adds a rewrite rule so that http://mysite.com/index.php?Salmon_Plugin=true
	 * can be rewritten as http://mysite.com/Salmon_Plugin
	 */
	public static function add_rewrite_rules( $wp_rewrite ) {
		global $wp_rewrite;
		$new_rules         = array(
			'salmon/?(.+)' => 'index.php?salmon=' . $wp_rewrite->preg_index( 1 ),
			'salmon'       => 'index.php?salmon=endpoint'
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
		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) == "OPTIONS" &&
				strtoupper( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ) == "POST"
		) {
			// See https://developer.mozilla.org/En/HTTP_access_control
			header( 'HTTP/1.1 200 OK' );
			header( 'Access-Control-Allow-Origin: * ' );
			die();
		}

		if ( strtoupper( $_SERVER['REQUEST_METHOD'] ) !== "POST" ) {
			header( 'HTTP/1.1 400 Bad Request' );
			echo Salmon_Plugin::endpoint( "Error: The posted Salmon entry was malformed." );
		}

		$requestBody = @file_get_contents( 'php://input' );

		$env  = Magic_Sig::parse( $requestBody );
		$data = base64_url_decode( $env['data'] );

		do_action( 'salmon_atom_data', $data, $user );

		$entry = Salmon_Entry::from_atom( $data );
		// Validate the request if the option is set.
		if ( get_option( 'salmon_validate' ) ) {
			if ( $entry->validate() === false ) {
				header( 'HTTP/1.1 403 Forbidden' );
				Salmon_Plugin::endpoint( "Error: The posted Salmon entry was malformed." );
			}
		}

		$commentdata = $entry->to_commentdata();
		do_action( 'salmon_comment_data', $commentdata, $user );

		if ( $user ) {
			wp_mail( $user->user_email, 'you\'ve fished a salmon', strip_tags( $commentdata['comment_content'] ) );
		}

		if ( $commentdata['comment_post_ID'] == '' ) {
			header( 'HTTP/1.1 400 Bad Request' );
			print "The posted Salmon entry was malformed.";
			//} else if (!isset($commentdata['user_id'])) {
			//  if (get_option('comment_registration')) {
			//    header('HTTP/1.1 403 Forbidden');
			//    Salmon_Plugin::endpoint("Error: The blog settings only allow registered users to post comments.");
			//  }
		} else {
			// save comment
			$commentId = wp_insert_comment( $commentdata );
			// add comment meta
			update_comment_meta( $commentId, '_salmon_avatars', $entry->avatars );
			update_comment_meta( $commentId, '_comment_type', 'salmon' );
			update_comment_meta( $commentId, '_crossposting_id', $entry->id );
			update_comment_meta( $commentId, '_crossposting_link', $entry->link );
			header( 'HTTP/1.1 201 Created' );
			Salmon_Plugin::endpoint( "The Salmon entry was posted." );
		}
		die();
	}

	/**
	 *
	 */
	public static function endpoint( $text ) {
		?>
				<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
								"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
				<html xmlns="http://www.w3.org/1999/xhtml" <?php if ( function_exists( 'language_attributes' ) ) {
			language_attributes();
		} ?>>
				<head>
						<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
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
