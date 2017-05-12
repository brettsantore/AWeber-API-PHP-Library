<?php namespace AWeber\Exceptions\OAuth;

use AWeber\Exceptions\Exception;

/**
 * DataMissing
 *
 * Used when a specific piece or pieces of data was not found in the
 * response. This differs from the exception that might be thrown as
 * an AWeberOAuthException when parameters are not provided because
 * it is not the servers' expectations that were not met, but rather
 * the expecations of the client were not met by the server.
 *
 * @uses AWeberException
 * @package
 * @version $id$
 */
class OAuthDataMissing extends Exception {

    public function __construct($missing) {
        if (!is_array($missing)) $missing = array($missing);
        $this->missing = $missing;
        $required = join(', ', $this->missing);
        parent::__construct("OAuthDataMissing: Response was expected to contain: {$required}");

    }
}