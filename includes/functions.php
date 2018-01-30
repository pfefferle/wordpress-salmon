<?php
if ( ! function_exists( 'base64url_encode' ) ) {
	function base64url_encode( $input, $nopad = 1, $wrap = 1 ) {
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
	function base64url_decode( $input ) {
		return base64_decode( strtr( $input, '-_,', '+/=' ) );
	}
}


/**
 * libmagicsignature
 *
 * This is a very limited version of Magic Signature defined in
 * http://salmon-protocol.googlecode.com/svn/trunk/draft-panzer-magicsig-00.html
 * It caters only the JSON format ones.
 * License: GPL v.3
 *
 * @author Nat Sakimura (http://www.sakimura.org/)
 * @version 0.5
 * @create 2010-05-09
**/

/**
 * Creating Magic Envelope Signature
 * @param  String $file     Data to be signed.
 * @param  String $datatype MIME type of $file
 * @param  String $pemfile  Filename of the PEM file that has signing key
 * @param  String $pass     The password for $pemfile
 * @return String Magic Signature in JSON format
 */
function salmon_magic_sign( $file, $datatype, $pemfile, $pass ) {
	$data = base64url_encode( $file );
	$m = $data . base64url_encode( $datatype ) . '.base64url.RSA-SHA256';
	// echo "\n========= M ==========\n" . $m . "\n";
	$hash = hash( 'sha256', $m );
	// Get Private Key
	$fp = fopen( $pemfile, 'r' );
	$priv_key = fread( $fp, 8192 );
	fclose( $fp );

	$res = openssl_get_privatekey( $priv_key, $pass );
	openssl_private_encrypt( $hash, $bsig, $res );
	$sig = array( 'value' => base64url_encode( $bsig ), 'keyhash' => $hash );

	$arr = array(
		'data' => $data,
		'data_type' => $datatype,
		'encoding' => 'base64url',
		'alg' => 'RSA-SHA256',
		'sigs' => array( $sig ),
	);
	return json_encode( $arr );
}

/**
 * Verifying the magic signature
 * @param  String $data    JSON formatted  Magic Signautre data
 * @param  String $pemfile The filename of the PEM with public key of the signer
 * @return true if the signature is valid. false if not.
 */

function salmon_magic_verify( $data, $pemfile ) {
	$fp = fopen( $pemfile, 'r' );
	$pub_key = fread( $fp, 8192 );
	fclose( $fp );
	openssl_get_publickey( $pub_key );
	$arr = json_decode( $data, true );

	$sigs = $arr['sigs'][0];
	$value = $sigs['value'];
	openssl_public_decrypt( base64url_decode( $value ), $nhash, $pub_key );

	// Compute Hash from data.
	$m = $arr['data'] . base64url_encode( $arr['data_type'] ) . '.base64url.RSA-SHA256';

	$chash = hash( 'sha256', $m );
	if ( $debug = 1 ) {
		echo PHP_EOL . $m . PHP_EOL;
		echo PHP_EOL . 'value  :' . $sigs['value'];
		echo PHP_EOL . 'keyhash:' . $sigs['keyhash'];
		echo PHP_EOL . 'newhash:' . $nhash;
		echo PHP_EOL . 'chash  :' . $chash;
		echo PHP_EOL . PHP_EOL;
	}

	// Hash Must Match
	if ( $chash == $nhash && $nhash == $sigs['keyhash'] ) {
		return true;
	} else {
		return false;
	}
}

/**
 *
 */
function salmon_get_private_key( $user_id ) {
	if ( get_user_meta( $user_id, 'salmon_private_key', true ) ) {
		return get_user_meta( $user_id, 'salmon_private_key', true );
	}

	$config = array(
		'private_key_bits' => 2048,
		'private_key_type' => OPENSSL_KEYTYPE_RSA,
	);

	$sig = openssl_pkey_new( $config );
	$detail = openssl_pkey_get_details( $sig );

	update_user_meta( $user_id, 'salmon_private_key', $detail['key'] );

	return $detail['key'];
}

/**
 *
 */
function salmon_get_public_key_object( $user_id, $key = null ) {
	$private_key = salmon_get_private_key( $user_id );

	$public_key = openssl_pkey_get_public( $private_key );
	$details = openssl_pkey_get_details( $public_key );

	if ( ! $key ) {
		return $details;
	}

	if ( array_key_exists( $key, $details ) ) {
		return $details[ $key ];
	}

	return null;
}

/**
 *
 */
function salmon_get_public_key( $user_id ) {
	return salmon_get_public_key_object( $user_id, 'key' );
}

/**
 *
 */
function salmon_get_magic_key( $user_id ) {
	$public_key = salmon_get_public_key_object( $user_id );

	return 'RSA.' . base64url_encode( $public_key['rsa']['n'] ) . '.' . base64url_encode( $public_key['rsa']['e'] );
}

/**
 *
 */
function salmon_get_encoded_public_key( $user_id ) {
	$public_key = salmon_get_public_key( $user_id );

	return base64url_encode( $public_key );
}
