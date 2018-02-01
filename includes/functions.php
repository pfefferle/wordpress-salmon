<?php
if ( ! function_exists( 'base64_url_encode' ) ) {
	function base64_url_encode( $input, $nopad = 1, $wrap = 1 ) {
		return strtr( base64_encode( $input ), '+/', '-_' );
	}
}

if ( ! function_exists( 'base64_url_decode' ) ) {
	function base64_url_decode( $input ) {
		return base64_decode( strtr( $input, '-_', '+/' ) );
	}
}
