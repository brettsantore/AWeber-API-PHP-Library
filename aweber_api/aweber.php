<?php

/**
 * AWeberServiceProvider
 *
 * Provides specific AWeber information or implementing OAuth.
 * @uses OAuthServiceProvider
 * @package
 * @version $id$
 */
class AWeberServiceProvider implements \AWeber\OAuth\ServiceProvider {

    /**
     * @var String Location for API calls
     */
    public $baseUri = 'https://api.aweber.com/1.0';

    /**
     * @var String Location to request an access token
     */
    public $accessTokenUrl = 'https://auth.aweber.com/1.0/oauth/access_token';

    /**
     * @var String Location to authorize an Application
     */
    public $authorizeUrl = 'https://auth.aweber.com/1.0/oauth/authorize';

    /**
     * @var String Location to request a request token
     */
    public $requestTokenUrl = 'https://auth.aweber.com/1.0/oauth/request_token';


    public function getBaseUri() {
        return $this->baseUri;
    }

    public function removeBaseUri($url) {
        return str_replace($this->getBaseUri(), '', $url);
    }

    public function getAccessTokenUrl() {
        return $this->accessTokenUrl;
    }

    public function getAuthorizeUrl() {
        return $this->authorizeUrl;
    }

    public function getRequestTokenUrl() {
        return $this->requestTokenUrl;
    }

    public function getAuthTokenFromUrl() { return ''; }
    public function getUserData() { return ''; }

}

/**
 * AWeberAPIBase
 *
 * Base object that all AWeberAPI objects inherit from.  Allows specific pieces
 * of functionality to be shared across any object in the API, such as the
 * ability to introspect the collections map.
 *
 * @package
 * @version $id$
 */
class AWeberAPIBase {

    /**
     * Maintains data about what children collections a given object type
     * contains.
     */
    static protected $_collectionMap = array(
        'account'              => array('lists', 'integrations'),
        'broadcast_campaign'   => array('links', 'messages', 'stats'),
        'followup_campaign'    => array('links', 'messages', 'stats'),
        'link'                 => array('clicks'),
        'list'                 => array('campaigns', 'custom_fields', 'subscribers',
                                        'web_forms', 'web_form_split_tests'),
        'web_form'             => array(),
        'web_form_split_test'  => array('components'),
    );

    /**
     * loadFromUrl
     *
     * Creates an object, either collection or entry, based on the given
     * URL.
     *
     * @param mixed $url    URL for this request
     * @access public
     * @return AWeberEntry or AWeberCollection
     */
    public function loadFromUrl($url) {
        $data = $this->adapter->request('GET', $url);
        return $this->readResponse($data, $url);
    }

    protected function _cleanUrl($url) {
        return str_replace($this->adapter->app->getBaseUri(), '', $url);
    }

    /**
     * readResponse
     *
     * Interprets a response, and creates the appropriate object from it.
     * @param mixed $response   Data returned from a request to the AWeberAPI
     * @param mixed $url        URL that this data was requested from
     * @access protected
     * @return mixed
     */
    protected function readResponse($response, $url) {
        $this->adapter->parseAsError($response);
        if (!empty($response['id']) || !empty($response['broadcast_id'])) {
            return new AWeberEntry($response, $url, $this->adapter);
        } else if (array_key_exists('entries', $response)) {
            return new AWeberCollection($response, $url, $this->adapter);
        }
        return false;
    }
}

/**
 * AWeberAPI
 *
 * Creates a connection to the AWeberAPI for a given consumer application.
 * This is generally the starting point for this library.  Instances can be
 * created directly with consumerKey and consumerSecret.
 * @uses AWeberAPIBase
 * @package
 * @version $id$
 */
class AWeberAPI extends AWeberAPIBase {

    /**
     * @var String Consumer Key
     */
    public $consumerKey    = false;

    /**
     * @var String Consumer Secret
     */
    public $consumerSecret = false;

    /**
     * @var Object - Populated in setAdapter()
     */
    public $adapter = false;

    /**
     * Uses the app's authorization code to fetch an access token
     *
     * @param String Authorization code from authorize app page
     */
    public static function getDataFromAweberID($string) {
        list($consumerKey, $consumerSecret, $requestToken, $tokenSecret, $verifier) = AWeberAPI::_parseAweberID($string);

        if (!$verifier) {
            return null;
        }
        $aweber = new AweberAPI($consumerKey, $consumerSecret);
        $aweber->adapter->user->requestToken = $requestToken;
        $aweber->adapter->user->tokenSecret = $tokenSecret;
        $aweber->adapter->user->verifier = $verifier;
        list($accessToken, $accessSecret) = $aweber->getAccessToken();
        return array($consumerKey, $consumerSecret, $accessToken, $accessSecret);
    }

    protected static function _parseAWeberID($string) {
        $values = explode('|', $string);
        if (count($values) < 5) {
            return null;
        }
        return array_slice($values, 0, 5);
    }

    /**
     * Sets the consumer key and secret for the API object.  The
     * key and secret are listed in the My Apps page in the labs.aweber.com
     * Control Panel OR, in the case of distributed apps, will be returned
     * from the getDataFromAweberID() function
     *
     * @param String Consumer Key
     * @param String Consumer Secret
     * @return null
     */
    public function __construct($key, $secret) {
        // Load key / secret
        $this->consumerKey    = $key;
        $this->consumerSecret = $secret;

        $this->setAdapter();
    }

    /**
     * Returns the authorize URL by appending the request
     * token to the end of the Authorize URI, if it exists
     *
     * @return string The Authorization URL
     */
    public function getAuthorizeUrl() {
        $requestToken = $this->user->requestToken;
        return (empty($requestToken)) ?
            $this->adapter->app->getAuthorizeUrl()
                :
            $this->adapter->app->getAuthorizeUrl() . "?oauth_token={$this->user->requestToken}";
    }

    /**
     * Sets the adapter for use with the API
     */
    public function setAdapter($adapter=null) {
        if (empty($adapter)) {
            $serviceProvider = new AWeberServiceProvider();
            $adapter = new \AWeber\OAuth\Application($serviceProvider);
            $adapter->consumerKey = $this->consumerKey;
            $adapter->consumerSecret = $this->consumerSecret;
        }
        $this->adapter = $adapter;
    }

    /**
     * Fetches account data for the associated account
     *
     * @param String Access Token (Only optional/cached if you called getAccessToken() earlier
     *      on the same page)
     * @param String Access Token Secret (Only optional/cached if you called getAccessToken() earlier
     *      on the same page)
     * @return Object AWeberCollection Object with the requested
     *     account data
     */
    public function getAccount($token=false, $secret=false) {
        if ($token && $secret) {
            $user = new AWeber\OAuth\User();
            $user->accessToken = $token;
            $user->tokenSecret = $secret;
            $this->adapter->user = $user;
        }

        $body = $this->adapter->request('GET', '/accounts');
        $accounts = $this->readResponse($body, '/accounts');
        return $accounts[0];
    }

    /**
     * PHP Automagic
     */
    public function __get($item) {
        if ($item == 'user') return $this->adapter->user;
        trigger_error("Could not find \"{$item}\"");
    }

    /**
     * Request a request token from AWeber and associate the
     * provided $callbackUrl with the new token
     * @param String The URL where users should be redirected
     *     once they authorize your app
     * @return Array Contains the request token as the first item
     *     and the request token secret as the second item of the array
     */
    public function getRequestToken($callbackUrl) {
        $requestToken = $this->adapter->getRequestToken($callbackUrl);
        return array($requestToken, $this->user->tokenSecret);
    }

    /**
     * Request an access token using the request tokens stored in the
     * current user object.  You would want to first set the request tokens
     * on the user before calling this function via:
     *
     *    $aweber->user->tokenSecret  = $_COOKIE['requestTokenSecret'];
     *    $aweber->user->requestToken = $_GET['oauth_token'];
     *    $aweber->user->verifier     = $_GET['oauth_verifier'];
     *
     * @return Array Contains the access token as the first item
     *     and the access token secret as the second item of the array
     */
    public function getAccessToken() {
        return $this->adapter->getAccessToken();
    }
}

class AWeberCollection extends AWeberResponse implements ArrayAccess, Iterator, Countable {

    protected $pageSize = 100;
    protected $pageStart = 0;

    protected function _updatePageSize() {

        # grab the url, or prev and next url and pull ws.size from it
        $url = $this->url;
        if (array_key_exists('next_collection_link', $this->data)) {
            $url = $this->data['next_collection_link'];

        } elseif (array_key_exists('prev_collection_link', $this->data)) {
            $url = $this->data['prev_collection_link'];
        }

        # scan querystring for ws_size
        $url_parts = parse_url($url);

        # we have a query string
        if (array_key_exists('query', $url_parts)) {
            parse_str($url_parts['query'], $params);

            # we have a ws_size
            if (array_key_exists('ws_size', $params)) {

                # set pageSize
                $this->pageSize = $params['ws_size'];
                return;
            }
        }

        # we dont have one, just count the # of entries
        $this->pageSize = count($this->data['entries']);
    }

    public function __construct($response, $url, $adapter) {
        parent::__construct($response, $url, $adapter);
        $this->_updatePageSize();
    }

    /**
     * @var array Holds list of keys that are not publicly accessible
     */
    protected $_privateData = array(
        'entries',
        'start',
        'next_collection_link',
    );

    /**
     * getById
     *
     * Gets an entry object of this collection type with the given id
     * @param mixed $id     ID of the entry you are requesting
     * @access public
     * @return AWeberEntry
     */
    public function getById($id) {
        $data = $this->adapter->request('GET', "{$this->url}/{$id}");
        $url = "{$this->url}/{$id}";
        return new AWeberEntry($data, $url, $this->adapter);
    }

    /** getParentEntry
     *
     * Gets an entry's parent entry
     * Returns NULL if no parent entry
     */
    public function getParentEntry(){
        $url_parts = explode('/', $this->url);
        $size = count($url_parts);

        # Remove collection id and slash from end of url
        $url = substr($this->url, 0, -strlen($url_parts[$size-1])-1);

        try {
            $data = $this->adapter->request('GET', $url);
            return new AWeberEntry($data, $url, $this->adapter);
        } catch (Exception $e) {
            return NULL;
        }
    }

    /**
     * _type
     *
     * Interpret what type of resources are held in this collection by
     * analyzing the URL
     *
     * @access protected
     * @return void
     */
    protected function _type() {
        $urlParts = explode('/', $this->url);
        $type = array_pop($urlParts);
        return $type;
    }

    /**
     * create
     *
     * Invoke the API method to CREATE a new entry resource.
     *
     * Note: Not all entry resources are eligible to be created, please
     *       refer to the AWeber API Reference Documentation at
     *       https://labs.aweber.com/docs/reference/1.0 for more
     *       details on which entry resources may be created and what
     *       attributes are required for creating resources.
     *
     * @access public
     * @param params mixed  associtative array of key/value pairs.
     * @return AWeberEntry(Resource) The new resource created
     */
    public function create($kv_pairs) {
        # Create Resource
        $params = array_merge(array('ws.op' => 'create'), $kv_pairs);
        $data = $this->adapter->request('POST', $this->url, $params, array('return' => 'headers'));

        # Return new Resource
        $url = $data['Location'];
        $resource_data = $this->adapter->request('GET', $url);
        return new AWeberEntry($resource_data, $url, $this->adapter);
    }

    /**
     * find
     *
     * Invoke the API 'find' operation on a collection to return a subset
     * of that collection.  Not all collections support the 'find' operation.
     * refer to https://labs.aweber.com/docs/reference/1.0 for more information.
     *
     * @param mixed $search_data   Associative array of key/value pairs used as search filters
     *                             * refer to https://labs.aweber.com/docs/reference/1.0 for a
     *                               complete list of valid search filters.
     *                             * filtering on attributes that require additional permissions to
     *                               display requires an app authorized with those additional permissions.
     * @access public
     * @return AWeberCollection
     */
    public function find($search_data) {
        # invoke find operation
        $params = array_merge($search_data, array('ws.op' => 'find'));
        $data = $this->adapter->request('GET', $this->url, $params);

        # get total size
        $ts_params = array_merge($params, array('ws.show' => 'total_size'));
        $total_size = $this->adapter->request('GET', $this->url, $ts_params, array('return' => 'integer'));
        $data['total_size'] = $total_size;

        # return collection
        return $this->readResponse($data, $this->url);
    }

    /*
     * ArrayAccess Functions
     *
     * Allows this object to be accessed via bracket notation (ie $obj[$x])
     * http://php.net/manual/en/class.arrayaccess.php
     */

    public function offsetSet($offset, $value)  {}
    public function offsetUnset($offset)        {}
    public function offsetExists($offset) {

        if ($offset >=0 && $offset < $this->total_size) {
            return true;
        }
        return false;
    }
    protected function _fetchCollectionData($offset) {

        # we dont have a next page, we're done
        if (!array_key_exists('next_collection_link', $this->data)) {
            return null;
        }

        # snag query string args from collection
        $parsed = parse_url($this->data['next_collection_link']);

        # parse the query string to get params
        $pairs = explode('&', $parsed['query']);
        foreach ($pairs as $pair) {
            list($key, $val) = explode('=', $pair);
            $params[$key] = $val;
        }

        # calculate new args
        $limit = $params['ws.size'];
        $pagination_offset = intval($offset / $limit) * $limit;
        $params['ws.start'] = $pagination_offset;

        # fetch data, exclude query string
        $url_parts = explode('?', $this->url);
        $data = $this->adapter->request('GET', $url_parts[0], $params);
        $this->pageStart = $params['ws.start'];
        $this->pageSize = $params['ws.size'];

        $collection_data = array('entries', 'next_collection_link', 'prev_collection_link', 'ws.start');

        foreach ($collection_data as $item) {
            if (!array_key_exists($item, $this->data)) {
                continue;
            }
            if (!array_key_exists($item, $data)) {
                continue;
            }
            $this->data[$item] = $data[$item];
        }
    }

    public function offsetGet($offset) {

        if (!$this->offsetExists($offset)) {
            return null;
        }

        $limit = $this->pageSize;
        $pagination_offset = intval($offset / $limit) * $limit;

        # load collection page if needed
        if ($pagination_offset !== $this->pageStart) {
            $this->_fetchCollectionData($offset);
        }

        $entry = $this->data['entries'][$offset - $pagination_offset];

        # we have an entry, cast it to an AWeberEntry and return it
        $entry_url = $this->adapter->app->removeBaseUri($entry['self_link']);
        return new AWeberEntry($entry, $entry_url, $this->adapter);
    }

    /*
     * Iterator
     */
    protected $_iterationKey = 0;

    public function current() {
        return $this->offsetGet($this->_iterationKey);
    }

    public function key() {
        return $this->_iterationKey;
    }

    public function next() {
        $this->_iterationKey++;
    }

    public function rewind() {
        $this->_iterationKey = 0;
    }

    public function valid() {
        return $this->offsetExists($this->key());
    }

    /*
     * Countable interface methods
     * Allows PHP's count() and sizeOf() functions to act on this object
     * http://www.php.net/manual/en/class.countable.php
     */

    public function count() {
        return $this->total_size;
    }
}
class AWeberEntry extends AWeberResponse {

    /**
     * @var array Holds list of data keys that are not publicly accessible
     */
    protected $_privateData = array(
        'resource_type_link',
        'http_etag',
    );

    /**
     * @var array   Stores local modifications that have not been saved
     */
    protected $_localDiff = array();

    /**
     * @var array Holds AWeberCollection objects already instantiated, keyed by
     *      their resource name (plural)
     */
    protected $_collections = array();

    /**
     * attrs
     *
     * Provides a simple array of all the available data (and collections) available
     * in this entry.
     *
     * @access public
     * @return array
     */
    public function attrs() {
        $attrs = array();
        foreach ($this->data as $key => $value) {
            if (!in_array($key, $this->_privateData) && !strpos($key, 'collection_link')) {
                $attrs[$key] = $value;
            }
        }
        if (!empty(AWeberAPI::$_collectionMap[$this->type])) {
            foreach (AWeberAPI::$_collectionMap[$this->type] as $child) {
                $attrs[$child] = 'collection';
            }
        }
        return $attrs;
    }

    /**
     * _type
     *
     * Used to pull the name of this resource from its resource_type_link
     * @access protected
     * @return String
     */
    protected function _type() {
        if (empty($this->type)) {
            if (!empty($this->data['resource_type_link'])) {
                list($url, $type) = explode('#', $this->data['resource_type_link']);
                $this->type = $type;
            } elseif (!empty($this->data['broadcast_id'])) {
                $this->type = 'broadcast';
            } else {
                return null;
            }
        }
        return $this->type;
    }

    /**
     * delete
     *
     * Delete this object from the AWeber system.  May not be supported
     * by all entry types.
     * @access public
     * @return boolean  Returns true if it is successfully deleted, false
     *      if the delete request failed.
     */
    public function delete() {
        $this->adapter->request('DELETE', $this->url, array(), array('return' => 'status'));
        return true;
    }

    /**
     * move
     *
     * Invoke the API method to MOVE an entry resource to a different List.
     *
     * Note: Not all entry resources are eligible to be moved, please
     *       refer to the AWeber API Reference Documentation at
     *       https://labs.aweber.com/docs/reference/1.0 for more
     *       details on which entry resources may be moved and if there
     *       are any requirements for moving that resource.
     *
     * @access public
     * @param AWeberEntry(List)   List to move Resource (this) too.
     * @return mixed AWeberEntry(Resource) Resource created on List ($list)
     *                                     or False if resource was not created.
     */
    public function move($list, $last_followup_message_number_sent=NULL) {
        # Move Resource
        $params = array(
            'ws.op' => 'move',
            'list_link' => $list->self_link
        );
        if (isset($last_followup_message_number_sent)) {
            $params['last_followup_message_number_sent'] = $last_followup_message_number_sent;
        }

        $data = $this->adapter->request('POST', $this->url, $params, array('return' => 'headers'));

        # Return new Resource
        $url = $data['Location'];
        $resource_data = $this->adapter->request('GET', $url);
        return new AWeberEntry($resource_data, $url, $this->adapter);
    }

    /**
     * save
     *
     * Saves the current state of this object if it has been changed.
     * @access public
     * @return void
     */
    public function save() {
        if (!empty($this->_localDiff)) {
            $data = $this->adapter->request('PATCH', $this->url, $this->_localDiff, array('return' => 'status'));
        }
        $this->_localDiff = array();
        return true;

    }

    /**
     * __get
     *
     * Used to look up items in data, and special properties like type and
     * child collections dynamically.
     *
     * @param String $value     Attribute being accessed
     * @access public
     * @throws AWeberResourceNotImplemented
     * @return mixed
     */
    public function __get($value) {
        if (in_array($value, $this->_privateData)) {
            return null;
        }
        if (!empty($this->data) && array_key_exists($value, $this->data)) {
            if (is_array($this->data[$value])) {
                $array = new AWeberEntryDataArray($this->data[$value], $value, $this);
                $this->data[$value] = $array;
            }
            return $this->data[$value];
        }
        if ($value == 'type') return $this->_type();
        if ($this->_isChildCollection($value)) {
            return $this->_getCollection($value);
        }
        throw new AWeberResourceNotImplemented($this, $value);
    }

    /**
     * __set
     *
     * If the key provided is part of the data array, then update it in the
     * data array.  Otherwise, use the default __set() behavior.
     *
     * @param mixed $key        Key of the attr being set
     * @param mixed $value      Value being set to the $key attr
     * @access public
     */
    public function __set($key, $value) {
        if (array_key_exists($key, $this->data)) {
            $this->_localDiff[$key] = $value;
            return $this->data[$key] = $value;
        } else {
            return parent::__set($key, $value);
        }
    }

    /**
     * findSubscribers
     *
     * Looks through all lists for subscribers
     * that match the given filter
     * @access public
     * @return AWeberCollection
     */
    public function findSubscribers($search_data) {
        $this->_methodFor(array('account'));
        $params = array_merge($search_data, array('ws.op' => 'findSubscribers'));
        $data = $this->adapter->request('GET', $this->url, $params);

        $ts_params = array_merge($params, array('ws.show' => 'total_size'));
        $total_size = $this->adapter->request('GET', $this->url, $ts_params, array('return' => 'integer'));

        # return collection
        $data['total_size'] = $total_size;
        $url = $this->url . '?'. http_build_query($params);
        return new AWeberCollection($data, $url, $this->adapter);
    }

    /**
     * getActivity
     *
     * Returns analytics activity for a given subscriber
     * @access public
     * @return AWeberCollection
     */
    public function getActivity() {
        $this->_methodFor(array('subscriber'));
        $params = array('ws.op' => 'getActivity');
        $data = $this->adapter->request('GET', $this->url, $params);

        $ts_params = array_merge($params, array('ws.show' => 'total_size'));
        $total_size = $this->adapter->request('GET', $this->url, $ts_params, array('return' => 'integer'));

        # return collection
        $data['total_size'] = $total_size;
        $url = $this->url . '?'. http_build_query($params);
        return new AWeberCollection($data, $url, $this->adapter);
    }

    /** getParentEntry
     *
     * Gets an entry's parent entry
     * Returns NULL if no parent entry
     */
    public function getParentEntry(){
        $url_parts = explode('/', $this->url);
        $size = count($url_parts);

        #Remove entry id and slash from end of url
        $url = substr($this->url, 0, -strlen($url_parts[$size-1])-1);

        #Remove collection name and slash from end of url
        $url = substr($url, 0, -strlen($url_parts[$size-2])-1);

        try {
            $data = $this->adapter->request('GET', $url);
            return new AWeberEntry($data, $url, $this->adapter);
        } catch (Exception $e) {
            return NULL;
        }
    }

    /**
     * getWebForms
     *
     * Gets all web_forms for this account
     * @access public
     * @return array
     */
    public function getWebForms() {
        $this->_methodFor(array('account'));
        $data = $this->adapter->request('GET', $this->url.'?ws.op=getWebForms', array(),
            array('allow_empty' => true));
        return $this->_parseNamedOperation($data);
    }


    /**
     * getWebFormSplitTests
     *
     * Gets all web_form split tests for this account
     * @access public
     * @return array
     */
    public function getWebFormSplitTests() {
        $this->_methodFor(array('account'));
        $data = $this->adapter->request('GET', $this->url.'?ws.op=getWebFormSplitTests', array(),
            array('allow_empty' => true));
        return $this->_parseNamedOperation($data);
    }

    /**
     * _parseNamedOperation
     *
     * Turns a dumb array of json into an array of Entries.  This is NOT
     * a collection, but simply an array of entries, as returned from a
     * named operation.
     *
     * @param array $data
     * @access protected
     * @return array
     */
    protected function _parseNamedOperation($data) {
        $results = array();
        foreach($data as $entryData) {
            $results[] = new AWeberEntry($entryData, str_replace($this->adapter->app->getBaseUri(), '',
                $entryData['self_link']), $this->adapter);
        }
        return $results;
    }

    /**
     * _methodFor
     *
     * Raises exception if $this->type is not in array entryTypes.
     * Used to restrict methods to specific entry type(s).
     * @param mixed $entryTypes Array of entry types as strings, ie array('account')
     * @access protected
     * @return void
     */
    protected function _methodFor($entryTypes) {
        if (in_array($this->type, $entryTypes)) return true;
        throw new AWeberMethodNotImplemented($this);
    }

    /**
     * _getCollection
     *
     * Returns the AWeberCollection object representing the given
     * collection name, relative to this entry.
     *
     * @param String $value The name of the sub-collection
     * @access protected
     * @return AWeberCollection
     */
    protected function _getCollection($value) {
        if (empty($this->_collections[$value])) {
            $url = "{$this->url}/{$value}";
            $data = $this->adapter->request('GET', $url);
            $this->_collections[$value] = new AWeberCollection($data, $url, $this->adapter);
        }
        return $this->_collections[$value];
    }


    /**
     * _isChildCollection
     *
     * Is the given name of a collection a child collection of this entry?
     *
     * @param String $value The name of the collection we are looking for
     * @access protected
     * @return boolean
     * @throws AWeberResourceNotImplemented
     */
    protected function _isChildCollection($value) {
        $this->_type();
        if (!empty(AWeberAPI::$_collectionMap[$this->type]) &&
            in_array($value, AWeberAPI::$_collectionMap[$this->type])) return true;
        return false;
    }

}

class AWeberEntryDataArray implements ArrayAccess, Countable, Iterator  {
    private $counter = 0;

    protected $data;
    protected $keys;
    protected $name;
    protected $parent;

    public function __construct($data, $name, $parent) {
        $this->data = $data;
        $this->keys = array_keys($data);
        $this->name = $name;
        $this->parent = $parent;
    }

    public function count() {
        return sizeOf($this->data);
    }

    public function offsetExists($offset) {
        return (isset($this->data[$offset]));
    }

    public function offsetGet($offset) {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->data[$offset] = $value;
        $this->parent->{$this->name} = $this->data;
        return $value;
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }

    public function rewind() {
        $this->counter = 0;
    }

    public function current() {
        return $this->data[$this->key()];
    }

    public function key() {
        return $this->keys[$this->counter];
    }

    public function next() {
        $this->counter++;
    }

    public function valid() {
        if ($this->counter >= sizeOf($this->data)) {
            return false;
        }
        return true;
    }


}

/**
 * AWeberResponse
 *
 * Base class for objects that represent a response from the AWeberAPI.
 * Responses will exist as one of the two AWeberResponse subclasses:
 *  - AWeberEntry - a single instance of an AWeber resource
 *  - AWeberCollection - a collection of AWeber resources
 * @uses AWeberAPIBase
 * @package
 * @version $id$
 */
class AWeberResponse extends AWeberAPIBase {

    public $adapter = false;
    public $data = array();
    public $_dynamicData = array();

    /**
     * __construct
     *
     * Creates a new AWeberRespones
     *
     * @param mixed $response       Data returned by the API servers
     * @param mixed $url            URL we hit to get the data
     * @param mixed $adapter        OAuth adapter used for future interactions
     * @access public
     * @return void
     */
    public function __construct($response, $url, $adapter) {
        $this->adapter = $adapter;
        $this->url     = $url;
        $this->data    = $response;
    }

    /**
     * __set
     *
     * Manual re-implementation of __set, allows sub classes to access
     * the default behavior by using the parent:: format.
     *
     * @param mixed $key        Key of the attr being set
     * @param mixed $value      Value being set to the attr
     * @access public
     */
    public function __set($key, $value) {
        $this->{$key} = $value;
    }

    /**
     * __get
     *
     * PHP "MagicMethod" to allow for dynamic objects.  Defers first to the
     * data in $this->data.
     *
     * @param String $value  Name of the attribute requested
     * @access public
     * @return mixed
     */
    public function __get($value) {
        if (in_array($value, $this->_privateData)) {
            return null;
        }
        if (array_key_exists($value, $this->data)) {
            return $this->data[$value];
        }
        if ($value == 'type') return $this->_type();
    }

}




/**
 * Thrown when the API returns an error. (HTTP status >= 400)
 *
 *
 * @uses AWeberException
 * @package
 * @version $id$
 */
class AWeberAPIException extends \AWeber\Exceptions\Exception {

    public $type;
    public $status;
    public $message;
    public $documentation_url;
    public $url;

    public function __construct($error, $url) {
        // record specific details of the API exception for processing
        $this->url = $url;
        $this->type = $error['type'];
        $this->status = array_key_exists('status', $error) ? $error['status'] : '';
        $this->message = $error['message'];
        $this->documentation_url = $error['documentation_url'];

        parent::__construct($this->message);
    }
}

/**
 * Thrown when attempting to use a resource that is not implemented.
 *
 * @uses AWeberException
 * @package
 * @version $id$
 */
class AWeberResourceNotImplemented extends \AWeber\Exceptions\Exception {

    public function __construct($object, $value) {
        $this->object = $object;
        $this->value = $value;
        parent::__construct("Resource \"{$value}\" is not implemented on this resource.");
    }
}

/**
 * AWeberMethodNotImplemented
 *
 * Thrown when attempting to call a method that is not implemented for a resource
 * / collection.  Differs from standard method not defined errors, as this will
 * be thrown when the method is infact implemented on the base class, but the
 * current resource type does not provide access to that method (ie calling
 * getByMessageNumber on a web_forms collection).
 *
 * @uses AWeberException
 * @package
 * @version $id$
 */
class AWeberMethodNotImplemented extends \AWeber\Exceptions\Exception {

    public function __construct($object) {
        $this->object = $object;
        parent::__construct("This method is not implemented by the current resource.");

    }
}

/**
 * AWeberOAuthException
 *
 * OAuth exception, as generated by an API JSON error response
 * @uses AWeberException
 * @package
 * @version $id$
 */
class AWeberOAuthException extends \AWeber\Exceptions\Exception {

    public function __construct($type, $message) {
        $this->type = $type;
        $this->message = $message;
        parent::__construct("{$type}: {$message}");
    }
}

/**
 * AWeberOAuthDataMissing
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
class AWeberOAuthDataMissing extends \AWeber\Exceptions\Exception {

    public function __construct($missing) {
        if (!is_array($missing)) $missing = array($missing);
        $this->missing = $missing;
        $required = join(', ', $this->missing);
        parent::__construct("OAuthDataMissing: Response was expected to contain: {$required}");

    }
}

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
class AWeberResponseError extends \AWeber\Exceptions\Exception {

    public function __construct($uri) {
        $this->uri = $uri;
        parent::__construct("Request for {$uri} did not respond properly.");
    }

}







