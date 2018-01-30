<?php
require_once 'class-webfinger.php';

/**
 * Represents a single Salmon entry retrieved from a post to the Salmon
 * endpoint.
 *
 * @author Matthias Pfefferle
 * @author Arne Roomann-Kurrik
 */
class Salmon_Entry {
	public $id;
	public $link;
	public $author_name;
	public $author_uri;
	public $thr_in_reply_to;
	public $content;
	public $title;
	public $updated;
	public $salmon_signature;
	public $webfinger;
	public $avatars;

	/**
	 * Determines whether the current element being parsed has a parent with
	 * the given name.
	 *
	 * @param array $atom An entry from xml_parse_into_struct.
	 * @param string $parent The parent element's name we are checking for.
	 * @param array $breadcrumbs An array of element names showing the current
	 *     parse tree.
	 *
	 * @return boolean True if the atom's parent's name is equal to the value
	 *     of $parent.
	 */
	public static function parent_is( $atom, $parent, $breadcrumbs ) {
		return ( $breadcrumbs[ $atom['level'] - 1 ] == $parent );
	}

	/**
	 * Converts an ATOM encoded Salmon post to a Salmon_Entry.
	 *
	 * @param string $atom_string The raw POST to the Salmon endpoint.
	 *
	 * @return Salmon_Entry An object representing the information in the POST.
	 */
	public static function from_atom( $atom_string ) {
		$xml_parser = xml_parser_create( '' );
		$xml_values = array();
		$xml_tags   = array();
		if ( ! $xml_parser ) {
			return false;
		}
		xml_parser_set_option( $xml_parser, XML_OPTION_TARGET_ENCODING, 'UTF-8' );
		xml_parser_set_option( $xml_parser, XML_OPTION_CASE_FOLDING, 0 );
		xml_parser_set_option( $xml_parser, XML_OPTION_SKIP_WHITE, 1 );
		xml_parse_into_struct( $xml_parser, trim( $atom_string ), $xml_values );
		xml_parser_free( $xml_parser );

		$entry       = new Salmon_Entry();
		$breadcrumbs = array();
		for ( $i = 0; $atom = $xml_values[ $i ]; $i ++ ) {
			// Only process one entry.  This could be generalized to a feed later.
			if ( strtolower( $atom['tag'] ) === 'entry' &&
				strtolower( $atom['type'] ) === 'close'
			) {
				break;
			}
			// Keep a "breadcrumb" list of the tag hierarchy we're currently in.
			$breadcrumbs[ $atom['level'] ] = $atom['tag'];

			// Parse individual attributes one at a time.
			switch ( strtolower( $atom['tag'] ) ) {
				case 'id':
					$entry->id = $atom['value'];
					break;
				case 'link':
					if ( $atom['attributes']['rel'] === 'alternate' && Salmon_Entry::parent_is( $atom, 'entry', $breadcrumbs ) ) {
						$entry->link = $atom['attributes']['href'];
					}
					break;
				case 'name':
					if ( Salmon_Entry::parent_is( $atom, 'author', $breadcrumbs ) ) {
						$entry->author_name = $atom['value'];
					}
					break;
				case 'uri':
					if ( Salmon_Entry::parent_is( $atom, 'author', $breadcrumbs ) ) {
						$entry->author_uri = $atom['value'];
					}
					break;
				case 'thr:in-reply-to':
					if ( $atom['value'] ) {
						$entry->thr_in_reply_to = $atom['value'];
					} else {
						$entry->thr_in_reply_to = $atom['attributes']['href'];
					}
					break;
				case 'content':
					$entry->content = $atom['value'];
					break;
				case 'title':
					$entry->title = $atom['value'];
					break;
				case 'updated':
					$entry->updated = $atom['value'];
					break;
				case 'sal:signature':
					$entry->salmon_signature = $atom['value'];
					break;
			}

			if ( $atom['tag'] === "link" && $atom['attributes']['rel'] === "avatar" ) {
				if ( $atom['attributes']['media:width'] > $atom['attributes']['media:height'] ) {
					$size = $atom['attributes']['media:width'];
				} else {
					$size = $atom['attributes']['media:height'];
				}

				$entry->avatars[ $size ] = $atom['attributes']['href'];
			}
		}

		//$entry->webfinger = WebFingerAccount::from_acct_string($entry->author_uri);
		return $entry;
	}

	/**
	 * Determines whether this Salmon_Entry's signature is valid.
	 * @return boolean True if the signature can be validated, False otherwise.
	 */
	public function validate() {
		return false;
	}

	/**
	 * Returns the data from this Salmon_Entry in a $commentdata format, suitable
	 * for passing to wp_new_comment.
	 *
	 * If the user's email address is a user of the current blog, this method
	 * retrieves the user's data and merges it into the $commentdata structure.
	 *
	 * @return array Data suitable for posting to wp_new_comment.
	 */
	public function to_commentdata() {
		$time    = strtotime( $this->updated );
		$matches = array();

		if ( url_to_postid( $this->thr_in_reply_to ) ) {
			$pid = url_to_postid( $this->thr_in_reply_to );
		} else {
			$pid = '';
		}

		$commentdata = array(
			'comment_post_ID'    => $pid,
			'comment_author'     => $this->author_name,
			'comment_author_url' => $this->author_uri,
			'comment_content'    => $this->content,
			'comment_date_gmt'   => date( 'Y-m-d H:i:s', $time ),
			'comment_date'       => get_date_from_gmt( date( 'Y-m-d H:i:s', $time ) )
			//'comment_type' => 'salmon'
		);

		// Pulls user data
		// TODO(kurrik): This probably needs to be refactored out to SalmonPress.php
		/*if ($this->webfinger !== false) {
		  $email = $this->webfinger->get_email();
		  $uid = email_exists($email);
		  if ($uid !== false) {
			$user_data = get_userdata($uid);
			$commentdata['comment_author'] = $user_data->display_name;
			$commentdata['comment_author_url'] = $user_data->user_url;
			$commentdata['comment_author_email'] = $email;
			$commentdata['user_id'] = $uid;
		  }
		}*/

		return $commentdata;
	}
}
