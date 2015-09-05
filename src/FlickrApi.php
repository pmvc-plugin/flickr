<?php
namespace PMVC\PlugIn\flickr;

class FlickrApi
{

    private $debug = 0;
    private $frob;
    private $endpoint;
    private $token;
    const ApiHost = 'https://api.flickr.com';
    const AuthHost = 'https://www.flickr.com';

    function __construct($api_key, $api_secret,  $debug=0) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->debug = $debug;
        $this->api_host = self::ApiHost;
        $this->auth_host = self::AuthHost;
        $this->_debug("initialized with api key '{$this->api_key}', secret '{$this->api_secret}', talking to '{$this->api_host}'");
    }
    
    function call_method($method, $args=array(), $sign_call=0) {

        $defaultArgs = array(
            'format'=>'php_serial',
            'method'=>$method,
            'oauth_nonce' => rand(),
            'oauth_consumer_key' => $this->api_key,
            'oauth_timestamp' => gmdate('U'), 
            'oauth_signature_method' => 'HMAC-SHA1', 
            'oauth_version'=>'1.0',
            'oauth_token'=>$this->token->oauth_token
        );
        $args = array_merge($defaultArgs,$args); 
        $base_url = $this->api_host . "/services/rest/";
        
        $url = $this->_request_url($base_url, $args, $sign_call);
        $this->_debug("request url: {$url}");

        $rsp = file_get_contents($url);
        $rsp_obj = unserialize($rsp);

        $this->_debug("response content: \n{$rsp}");
        $this->_debug("response object: \n".print_r($rsp_obj, true));

        if (!$this->ok($rsp_obj)) {
            return $this->on_error($rsp_obj);
        } else {
            return $rsp_obj;
        }
    }
    
    #
    # should probably add support for uploading file data at some point
    #
    
    function upload_photo($args, $local_photo_path) {
        $base_url = $this->api_host . "/services/upload/";
        
        $args['api_key'] = $this->api_key;
        
        $args['api_sig'] = $this->sign_args($args, $this->api_secret);
        
        $args['photo'] = curl_file_create($local_photo_path);
                
        $curl = curl_init( $base_url );
        curl_setopt( $curl, CURLOPT_POST, true );
        curl_setopt( $curl, CURLOPT_POSTFIELDS, $args);
        curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1);
        $rsp = curl_exec( $curl );
        return $rsp;
    }
    
    function getToken()
    {
        return $this->token;
    }

    function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param string $perms [read|write|delete]
     */
    function getLoginUrl($return,$perms="delete")
    {
        $this->getRequestToken($return);
        $base_url = $this->auth_host . "/services/oauth/authorize";
        $args = array(
            'oauth_token'=>$this->token->oauth_token,
            'perms'=>$perms
        );
        $url = $this->_request_url($base_url, $args, false);
        return $url;
    }

    function getRequestToken($return)
    {
        $args = array(
            'oauth_consumer_key' => $this->api_key,
            'oauth_nonce' => rand(),
            'oauth_signature_method' => 'HMAC-SHA1', 
            'oauth_timestamp' => gmdate('U'), 
            'oauth_version'=>'1.0',
            'oauth_callback'=>$return
        );
        $base_url = $this->auth_host . "/services/oauth/request_token";
        $url = $this->_request_url($base_url, $args, true);
        $self = $this;
        \PMVC\plug('curl')->get($url, function($r) use ($self){
            parse_str($r->body,$data); 
            $this->token = (object)array(
                'oauth_token'=>$data['oauth_token'],
                'oauth_token_secret'=>$data['oauth_token_secret']
            );
        });
        \PMVC\plug('curl')->run();
    }

    function getAccessToken($request)
    {
        $args = array(
            'oauth_nonce' => rand(),
            'oauth_timestamp' => gmdate('U'), 
            'oauth_verifier'=>$request['oauth_verifier'],
            'oauth_consumer_key' => $this->api_key,
            'oauth_signature_method' => 'HMAC-SHA1', 
            'oauth_version'=>'1.0',
            'oauth_token'=>$this->token->oauth_token
        );
        $base_url = $this->auth_host . "/services/oauth/access_token";
        $url = $this->_request_url($base_url, $args, true);
        $self = $this;
        \PMVC\plug('curl')->get($url, function($r) use ($self){
            parse_str($r->body,$data); 
            $this->token = (object)$data;
        });
        \PMVC\plug('curl')->run();
    }
    
    function on_error($rsp) {
        return $rsp;
    }
    
    function ok($rsp)
    {
        return ($rsp['stat'] == 'ok') ? true : false;
    }
    
    function _request_url($base_url, $args=array(), $sign_call=0)
    {
        $secret = !empty($this->token->oauth_token_secret) 
            ? $this->token->oauth_token_secret 
            : null;
        $args['oauth_signature'] = \PMVC\plug('auth')->oauthSign(
            array('GET',$base_url,$args),
            $this->api_secret,
            $secret
        );
        $encoded_params = array();
        foreach ($args as $k => $v){
            $encoded_params[] = urlencode($k).'='.urlencode($v);
        }
        return $base_url.'?'.implode('&', $encoded_params);
    }

    private function _debug($str) {
        if ($this->debug) {
            echo "[debug] $str\n";
        }
    }
}

