<?php
/**
 * Represents an account resource that can be queried through the WebFinger
 * protocol.
 */
class WebFingerAccount {

	private $acct;

	/**
	 * Initializes a WebFingerAccount from a "acct:name@domain.com" encoded
	 * email address.
	 * @param string $acct_string The WebFinger identifier.
	 * @return WebFingerAccount The populated account object which can be used
	 *     to query for WebFinger data.
	 */
	public static function from_acct_string($acct_string) {
		if (substr($acct_string, 0, 5) !== "acct:") {
			return false;
		}

		$account = new WebFingerAccount();
		$account->acct = $acct_string;

		return $account;
	}

	/**
	 * Returns the email address of this account.
	 * @return string The email address of this account.
	 */
	public function get_email() {
		return substr($this->acct, 5);
	}

	/**
	 * Returns the host name of this account.
	 * @return string The host name of this account.
	 */
	public function get_host() {
		return substr($this->acct, stripos($this->acct, '@'));
	}
}
