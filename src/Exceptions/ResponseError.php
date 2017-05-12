<?php namespace AWeber\Exceptions;

/**
 * AWeberResponseError
 *
 * This is raised when the server returns a non-JSON response. This
 * should only occur when there is a server or some type of connectivity
 * issue.
 *
 * @uses AWeberException
 * @package
 * @version $id$
 */
class ResponseError extends Exception {

    public function __construct($uri) {
        $this->uri = $uri;
        parent::__construct("Request for {$uri} did not respond properly.");
    }

}