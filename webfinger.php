<?php
/**
 * Copyright 2009 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
 
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

