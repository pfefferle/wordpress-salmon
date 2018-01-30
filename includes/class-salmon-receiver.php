<?php
/**
 * Salmon Receiver Class
 *
 * @author Matthias Pfefferle
 */
class SalmonReceiver {
	/**
	 * Initialize the plugin, registering WordPress hooks
	 */
	public static function init() {
		// Configure the REST API route
		add_action( 'rest_api_init', array( 'SalmonReceiver', 'register_routes' ) );
		// Filter the response to allow a webmention form if no parameters are passed
		add_filter( 'rest_pre_serve_request', array( 'SalmonReceiver', 'serve_request' ), 9, 4 );

		// Allow for avatars on salmon comment types
		add_filter( 'get_avatar_comment_types', array( 'SalmonReceiver', 'get_avatar_comment_types' ) );
	}

	/**
	 * Show avatars on webmentions if set
	 *
	 * @param array $types list of avatar enabled comment types
	 *
	 * @return array show avatars also on trackbacks and pingbacks
	 */
	public static function get_avatar_comment_types( $types ) {
		$types[] = 'salmon';
		return array_unique( $types );
	}

	/**
	 * Register the Route.
	 */
	public static function register_routes() {
		register_rest_route( 'salmon/1.0', '/endpoint', array(
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( 'SalmonReceiver', 'post' ),
			),
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( 'SalmonReceiver', 'get' ),
			),
		));
	}
	/**
	 * Hooks into the REST API output to output a webmention form.
	 *
	 * This is only done for the webmention endpoint.
	 *
	 * @param bool                      $served  Whether the request has already been served.
	 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request           $request Request used to generate the response.
	 * @param WP_REST_Server            $server  Server instance.
	 *
	 * @return true
	 */
	public static function serve_request( $served, $result, $request, $server ) {
		if ( '/salmon/1.0/endpoint' !== $request->get_route() ) {
			return $served;
		}
		if ( 'GET' !== $request->get_method() ) {
			return $served;
		}
		// If someone tries to poll the webmention endpoint return a webmention form.
		if ( ! headers_sent() ) {
			$server->send_header( 'Content-Type', 'text/html; charset=' . get_option( 'blog_charset' ) );
		}
		$template = apply_filters( 'salmon_endpoint_form', plugin_dir_path( __FILE__ ) . '../templates/salmon-endpoint-form.php' );
		load_template( $template );
		return true;
	}
	/**
	 * GET Callback for the webmention endpoint.
	 *
	 * Returns true. Any GET request is intercepted to return a webmention form.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return true
	 */
	public static function get( $request ) {
		return true;
	}
	/**
	 * POST Callback for the webmention endpoint.
	 *
	 * Returns the response.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response
	 *
	 * @uses apply_filters calls "webmention_comment_data" on the comment data
	 * @uses apply_filters calls "webmention_update" on the comment data
	 * @uses apply_filters calls "webmention_success_message" on the success message
	 */
	public static function post( $request ) {
		$params = array_filter( $request->get_params() );
		if ( ! isset( $params['source'] ) ) {
			return new WP_Error( 'source_missing' , __( 'Source is missing', 'webmention' ), array( 'status' => 400 ) );
		}
		$source = urldecode( $params['source'] );
		if ( ! isset( $params['target'] ) ) {
			return new WP_Error( 'target_missing', __( 'Target is missing', 'webmention' ), array( 'status' => 400 ) );
		}
		$target = urldecode( $params['target'] );
		if ( ! stristr( $target, preg_replace( '/^https?:\/\//i', '', home_url() ) ) ) {
			return new WP_Error( 'target_mismatching_domain', __( 'Target is not on this domain', 'webmention' ), array( 'status' => 400 ) );
		}
		$comment_post_id = webmention_url_to_postid( $target );
		// check if post id exists
		if ( ! $comment_post_id ) {
			return new WP_Error( 'target_not_valid', __( 'Target is not a valid post', 'webmention' ), array( 'status' => 400 ) );
		}
		if ( url_to_postid( $source ) === $comment_post_id ) {
			return new WP_Error( 'source_equals_target', __( 'Target and source cannot direct to the same resource', 'webmention' ), array( 'status' => 400 ) );
		}
		// check if pings are allowed
		if ( ! pings_open( $comment_post_id ) ) {
			return new WP_Error( 'pings_closed', __( 'Pings are disabled for this post', 'webmention' ), array( 'status' => 400 ) );
		}
		$post = get_post( $comment_post_id );
		if ( ! $post ) {
			return new WP_Error( 'target_not_valid', __( 'Target is not a valid post', 'webmention' ), array( 'status' => 400 ) );
		}
		// In the event of async processing this needs to be stored here as it might not be available
		// later.
		$comment_meta = array();
		$comment_author_ip = preg_replace( '/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'] );
		$comment_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT']: '';
		$comment_date = current_time( 'mysql' );
		$comment_date_gmt = current_time( 'mysql', 1 );
		$comment_meta['webmention_created_at'] = $comment_date_gmt;
		// change this if your theme can't handle the Webmentions comment type
		$comment_type = WEBMENTION_COMMENT_TYPE;
		// change this if you want to auto approve your Webmentions
		$comment_approved = WEBMENTION_COMMENT_APPROVE;
		$commentdata = compact( 'comment_type', 'comment_approved', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_meta', 'source', 'target' );
		$commentdata['comment_post_ID'] = $comment_post_id;
		$commentdata['comment_author_IP'] = $comment_author_ip;
		// Set Comment Author URL to Source
		$commentdata['comment_author_url'] = esc_url_raw( $commentdata['source'] );
		// Save Source to Meta to Allow Author URL to be Changed and Parsed
		$commentdata['comment_meta']['webmention_source_url'] = $commentdata['comment_author_url'];
		$fragment = wp_parse_url( $commentdata['target'], PHP_URL_FRAGMENT );
		if ( ! empty( $fragment ) ) {
			$commentdata['comment_meta']['webmention_target_fragment'] = $fragment;
		}
		$commentdata['comment_meta']['webmention_target_url'] = $commentdata['target'];
		// add empty fields
		$commentdata['comment_parent'] = $commentdata['comment_author_email'] = '';
		// Define WEBMENTION_PROCESS_TYPE as true if you want to define an asynchronous handler
		if ( WEBMENTION_PROCESS_TYPE_ASYNC === get_webmention_process_type() ) {
			// Schedule an action a random period of time in the next 2 minutes to handle webmentions.
			wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'webmention_process_schedule', array( $commentdata ) );
			// Return the source and target and the 202 Message
			$return = array(
				'link' => '', // TODO add API link to check state of comment
				'source' => $commentdata['source'],
				'target' => $commentdata['target'],
				'code' => 'scheduled',
				'message' => apply_filters( 'webmention_schedule_message', __( 'Webmention is scheduled', 'webmention' ) ),
			);
			return new WP_REST_Response( $return, 202 );
		}
		/**
		 * Filter Comment Data for Webmentions.
		 *
		 * All verification functions and content generation functions are added to the comment data.
		 *
		 * @param array $commentdata
		 * @return array|null|WP_Error $commentdata The Filtered Comment Array or a WP_Error object.
		 */
		$commentdata = apply_filters( 'webmention_comment_data', $commentdata );
		if ( ! $commentdata || is_wp_error( $commentdata ) ) {
			/**
			 * Fires if Error is Returned from Filter.
			 *
			 * Added to support deletion.
			 *
			 * @param array $commentdata
			 */
			do_action( 'webmention_data_error', $commentdata );
			return $commentdata;
		}
		// disable flood control
		remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );
		// update or save webmention
		if ( empty( $commentdata['comment_ID'] ) ) {
			// save comment
			$commentdata['comment_ID'] = wp_new_comment( $commentdata, true );
			/**
			 * Fires when a webmention is created.
			 *
			 * Mirrors comment_post and pingback_post.
			 *
			 * @param int $comment_ID Comment ID.
			 * @param array $commentdata Comment Array.
			 */
			do_action( 'webmention_post', $commentdata['comment_ID'], $commentdata );
		} else {
			// update comment
			wp_update_comment( $commentdata );
		}
		if ( is_wp_error( $commentdata['comment_ID'] ) ) {
			return new WP_REST_Response( $commentdata['comment_ID'], 500 );
		}
		// re-add flood control
		add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
		// Return select data
		$return = array(
			'link' => get_comment_link( $commentdata['comment_ID'] ),
			'source' => $commentdata['source'],
			'target' => $commentdata['target'],
			'code' => 'success',
			'message' => apply_filters( 'webmention_success_message', __( 'Webmention was successful', 'webmention' ) ),
		);
		return new WP_REST_Response( $return, 200 );
	}
}
