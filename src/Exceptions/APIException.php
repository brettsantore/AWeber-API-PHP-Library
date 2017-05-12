<?php namespace AWeber\Exceptions;

/**
 * Thrown when the API returns an error. (HTTP status >= 400)
 *
 * @uses    Exception
 * @package
 * @version $id$
 */
class APIException extends Exception
{

    public $type;
    public $status;
    public $message;
    public $documentation_url;
    public $url;

    public function __construct($error, $url) 
    {
        // record specific details of the API exception for processing
        $this->url = $url;
        $this->type = $error['type'];
        $this->status = array_key_exists('status', $error) ? $error['status'] : '';
        $this->message = $error['message'];
        $this->documentation_url = $error['documentation_url'];

        parent::__construct($this->message);
    }
}