<?php
if ( ! function_exists( 'base64url_encode' ) ) {
	function base64_url_encode( $input, $nopad = 1, $wrap = 1 ) {
		$data = base64_encode( $input );
		if ( $nopad ) {
			$data = str_replace( '=' , '' , $data );
		}
		$data = strtr( $data, '+/=', '-_,' );
		if ( $wrap ) {
			$datalb = '';
			while ( strlen( $data ) > 64 ) {
				$datalb .= substr( $data, 0, 64 ) . PHP_EOL;
				$data = substr( $data, 64 );
			}
			$datalb .= $data;
			return $datalb;
		} else {
			return $data;
		}
	}
}

if ( ! function_exists( 'base64url_decode' ) ) {
	function base64_url_decode( $input ) {
		return base64_decode( strtr( $input, '-_,', '+/=' ) );
	}
}
