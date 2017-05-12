<?php namespace AWeber\OAuth;

interface Adapter {

    public function request($method, $uri, $data = array());
    public function getRequestToken($callbackUrl=false);

}