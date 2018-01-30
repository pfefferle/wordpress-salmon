<?php
class Salmon_Discovery {
	public static function init() {
		// Init feeds.
		add_action( 'atom_head', array( 'Salmon_Discovery', 'print_atom_feed_header' ) );
		add_action( 'rss2_head', array( 'Salmon_Discovery', 'print_rss_feed_header' ) );
		//add_action('atom_entry', array('Salmon_Discovery', 'add_salmon_link'));

		// xrd discovery
		add_action( 'host_meta', array( 'Salmon_Discovery', 'add_host_meta_discovery' ) );
		add_action( 'webfinger_user_data', array( 'Salmon_Discovery', 'add_webfinger_discovery' ), 10, 3 );
	}

	/**
	 * Show discovery links
	 *
	 * @param WP_User $user default null
	 */
	public static function add_host_meta_discovery( $array ) {
		$url = Salmon_Plugin::generate_api_url();

		$array['links'][] = array(
			'rel'  => 'salmon',
			'href' => $url,
		);

		$array['links'][] = array(
			'rel'  => 'http://salmon-protocol.org/ns/salmon-replies',
			'href' => $url,
		);

		$array['links'][] = array(
			'rel'  => 'http://salmon-protocol.org/ns/salmon-mention',
			'href' => $url,
		);
	}

	/**
	 * @param array $array
	 * @param $resource
	 * @param WP_User $user
	 *
	 * @return array
	 */
	public static function add_webfinger_discovery( $array, $resource, $user ) {
		$magic_public_key = Magic_Sig::get_magic_public_key( $user->ID );

		$array['links'][] = array(
			'rel'  => 'magic-public-key',
			'href' => 'data:application/magic-public-key,' . $magic_public_key,
		);

		if ( ! isset( $array['properties'] ) ) {
			$array['properties'] = array();
		}

		// experimental
		$array['properties']['http://salmon-protocol.org/ns/magic-key'] = $magic_public_key;

		$url = Salmon_Plugin::generate_api_url( $user );

		$array['links'][] = array(
			'rel'  => 'salmon',
			'href' => $url,
		);

		$array['links'][] = array(
			'rel'  => 'http://salmon-protocol.org/ns/salmon-replies',
			'href' => $url,
		);

		$array['links'][] = array(
			'rel'  => 'http://salmon-protocol.org/ns/salmon-mention',
			'href' => $url,
		);

		return $array;
	}

	/**
	 * adds salmon link to <entry />
	 */
	public static function add_salmon_link() {
		if ( function_exists( "get_webfinger" ) ) {
			$webfinger = get_webfinger( get_the_author_meta( "ID" ), true );
			echo '<link rel="salmon" href="' . $webfinger . '" />';
		}
	}

	/**
	 * @param array $array
	 * @param $resource
	 * @param WP_User $user
	 *
	 * @return array
	 */
	public static function add_user_salmon( $array, $resource, $user ) {
		$salmon = Salmon_Plugin::generate_api_url( $user );
		$array['links'][] = array(
			'rel'  => 'salmon',
			'href' => $salmon,
		);

		return $array;
	}

	public static function print_atom_feed_header() {
		Salmon_Discovery::print_feed_header( 'atom' );
	}

	public static function print_rss_feed_header() {
		Salmon_Discovery::print_feed_header( 'rss' );
	}

	/**
	 * Prints the link pointing to the salmon endpoint to a syndicated feed.
	 */
	public static function print_feed_header( $type ) {
		if ( is_author() ) {
			if ( get_query_var( 'author_name' ) ) :
				$user = get_user_by( 'slug', get_query_var( 'author_name' ) );
			else :
				$user = get_userdata( get_query_var( 'author' ) );
			endif;
		} else {
			$user = null;
		}

		// add namespace to rss files
		if ( $type == 'rss' ) {
			$namespace = "atom:";
		} else {
			$namespace = "";
		}

		echo "<" . $namespace . "link rel='salmon' href='" . Salmon_Plugin::generate_api_url( $user ) . "'/>";
		echo "<" . $namespace . "link rel='http://salmon-protocol.org/ns/salmon-replies' href='" . Salmon_Plugin::generate_api_url( $user ) . "'/>";
		echo "<" . $namespace . "link rel='http://salmon-protocol.org/ns/salmon-mention' href='" . Salmon_Plugin::generate_api_url( $user ) . "'/>";
	}
}
