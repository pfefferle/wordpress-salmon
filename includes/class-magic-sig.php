<?php
define( 'MAGIC_SIG_NS', 'http://salmon-protocol.org/ns/magic-env' );

class Magic_Sig {

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public static function get_public_key( $user_id, $force = false ) {
		$key = get_user_meta( $user_id, 'magic_sig_public_key' );

		if ( $key && ! $force ) {
			return $key[0];
		}

		Magic_Sig::generate_key_pair( $user_id );
		$key = get_user_meta( $user_id, 'magic_sig_public_key' );

		return $key[0];
	}

	/**
	 * Generates the pair keys
	 *
	 * @param int $user_id
	 */
	public static function generate_key_pair( $user_id ) {
		$config = array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		);

		$key = openssl_pkey_new( $config );

		$priv_key = null;

		openssl_pkey_export( $key, $priv_key );

		// private key
		update_user_meta( $user_id, 'magic_sig_private_key', $priv_key );

		$detail = openssl_pkey_get_details( $key );

		// public key
		update_user_meta( $user_id, 'magic_sig_public_key', $detail['key'] );
	}

	/**
	 * Returns the generated public key
	 *
	 * @param int $user_id the user id
	 * @param boolean $force force a re-generation of the key-pair
	 *
	 * @return string the magic key
	 */
	public static function get_magic_public_key( $user_id, $force = false ) {
		$key = self::get_public_key( $user_id, $force );

		$magic_public_key = self::to_string( $key );

		if ( false === $magic_public_key ) {
			return self::get_magic_public_key( $user_id, true );
		}

		return $magic_public_key;
	}

	/**
	 * Convert key to string
	 *
	 * @param string $sig
	 *
	 * @return string
	 */
	public static function to_string( $key ) {
		$key = openssl_pkey_get_public( $key );

		$details = openssl_pkey_get_details( $key );

		if ( ! $key || ! $details ) {
			return false;
		}

		$mod = base64_url_encode( $details['rsa']['n'] );
		$exp = base64_url_encode( $details['rsa']['e'] );

		return 'RSA.' . $mod . '.' . $exp;
	}

	public static function parse( $text ) {
		$dom = new DOMDocument();
		$dom->loadXML( $text );

		return Magic_Sig::from_dom( $dom );
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return array|bool
	 */
	public static function from_dom( $dom ) {
		$env_element = $dom->getElementsByTagNameNS( MAGIC_SIG_NS, 'env' )->item( 0 );
		if ( ! $env_element ) {
			$env_element = $dom->getElementsByTagNameNS( MAGIC_SIG_NS, 'provenance' )->item( 0 );
		}

		if ( ! $env_element ) {
			return false;
		}

		$data_element = $env_element->getElementsByTagNameNS( MAGIC_SIG_NS, 'data' )->item( 0 );
		$sig_element  = $env_element->getElementsByTagNameNS( MAGIC_SIG_NS, 'sig' )->item( 0 );

		return array(
			'data'      => preg_replace( '/\s/', '', $data_element->nodeValue ), // phpcs:ignore
			'data_type' => $data_element->getAttribute( 'type' ),
			'encoding'  => $env_element->getElementsByTagNameNS( MAGIC_SIG_NS, 'encoding' )->item( 0 )->nodeValue,
			'alg'       => $env_element->getElementsByTagNameNS( MAGIC_SIG_NS, 'alg' )->item( 0 )->nodeValue,
			'sig'       => preg_replace( '/\s/', '', $sig_element->nodeValue ), // phpcs:ignore
		);
	}

	public static function verify( $xml, $user_id ) {
		$key = get_user_meta( $user_id, 'magic_sig_private_key' );
		$env = Magic_Sig::parse( $xml );

		$text = base64_url_decode( $env['data'] );

		$signer_uri = Magic_Sig::get_author( $text );
	}

	public static function get_author( $text ) {
		$doc = new DOMDocument();
		if ( ! $doc->loadXML( $text ) ) {
			return false;
		}

		if ( $doc->documentElement->tagName === 'entry' ) { // phpcs:ignore
			$authors = $doc->documentElement->getElementsByTagName( 'author' ); // phpcs:ignore
			foreach ( $authors as $author ) {
				$uris = $author->getElementsByTagName( 'uri' );
				foreach ( $uris as $uri ) {
					return $uri->nodeValue; // phpcs:ignore
				}
			}
		}
	}
}
