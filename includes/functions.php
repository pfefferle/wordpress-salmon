<?php
if ( ! function_exists( 'base64_url_encode' ) ) {
	/**
	 * Encode data
	 *
	 * @param string $input input text
	 *
	 * @return string the encoded text
	 */
	function base64_url_encode( $input ) {
		return strtr( base64_encode( $input ), '+/', '-_' );
	}
}

if ( ! function_exists( 'base64_url_decode' ) ) {
	/**
	 * Dencode data
	 *
	 * @param string $input input text
	 *
	 * @return string the decoded text
	 */
	function base64_url_decode( $input ) {
		return base64_decode( strtr( $input, '-_', '+/' ) );
	}
}
