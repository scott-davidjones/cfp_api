<?php
/**
 * The Property Software Group API Integration 
 *
 * @package     CFP
 * @category    API Integration
 * @author      autumn.dev
 * @copyright   Copyright 2014 - autumn.dev
 * @link        http://www.autumndev.co.uk
 */

namespace Cfp;

session_start();

class Cfp
{
    /**
     * CFP API Version
     * @var integer
     */
    public $apiVersion = 7;

    /**
     * URL for API call
     * @var string
     */
    protected $baseURL = '';

    /**
     * Branch URL
     * @var string
     */
    protected $branchURL = '';

    /**
     * Properties List URL 
     * @var string
     */
    protected $propertiesURL = '';

    /**
     * Username and password credentials
     * @var string
     */
    private $credentials;

    /**
     * Constructor
     * 
     * @param string $user    username
     * @param string $pass    password
     * @param string $version API Version Number
     *
     * @return void
     */
    public function __construct($user, $pass, $datafeed, $debug = false, $version = null)
    {
        $this->credentials = $user.":".$pass;
        $this->debug = $debug;
        if ($version !== null) {
            $this->version = $version;
        }
        $this->baseURL = "http://webservices.vebra.com/export/{$datafeed}/v{$this->apiVersion}";
        
        unset($user, $pass, $datafeed, $debug, $version);
    }

    /**
     * returns CFP branches based on datafeed
     * 
     * @param  string $datafeed CFP Datafeed ID
     * 
     * @return object SimpleXMLElement
     */
    public function getBranches()
    {
        $url = $this->baseURL."/branch";

        if ($this->debug === true) {
            $this->debugOuput($url);
        }

        return $this->makeAPICall($url);
    }

    /**
     * returns CFP Branch details 
     * 
     * @param  mixed $agentID 
     * 
     * @return object SimpleXMLElement
     */
    public function getBranchDetails($agentID)
    {
        $this->branchURL = $this->baseURL."/branch/{$agentID}";

        if ($this->debug === true) {
            $this->debugOuput($this->branchURL);
        }

        return $this->makeAPICall($this->branchURL);
    }

    /**
     * returns CFP Branch Properties List  
     * 
     * @return object SimpleXMLElement
     */
    public function getPropertyList($url)
    {
        $this->propertiesURL = $url."/property";

        if ($this->debug === true) {
            $this->debugOuput($this->propertiesURL);
        }

        return $this->makeAPICall($this->propertiesURL);
    }

    /**
     * returns CFP branch property details
     * 
     * @param  mixed $propertyID 
     * 
     * @return object SimpleXMLElement
     */
    public function getPropertyDetails($propertyID, $url = null)
    {  
        if ($url !== null) {
            $propertyURL = $url;
        } else {
            $propertyURL = $this->propertiesURL."/{$propertyID}";
        }

        if ($this->debug === true) {
            $this->debugOuput($propertyURL);
        }

        return $this->makeAPICall($propertyURL);
    }

    /**
     * Gets updated properties
     * 
     * @param  int $time unix timestamp
     * 
     * @return object SimpleXMLElement
     */
    public function getUpdatedProperties($time)
    {
        $year = date("Y", $time);
        $month = date("m", $time);
        $day = date("d", $time);
        $hour = date("H", $time);
        $min = date("i", $time);
        $sec = date("s", $time);
        $url = $this->baseURL."/property/{$year}/{$month}/{$day}/{$hour}/{$min}/{$sec}";

        if ($this->debug === true) {
            $this->debugOuput($url);
        }

        return $this->makeAPICall($url);
    }

    /**
     * Gets updated files
     * 
     * @param  int $time unix timestamp
     * 
     * @return object SimpleXMLElement
     */
    public function getUpdatedFiles($time)
    {
        $year = date("Y", $time);
        $month = date("m", $time);
        $day = date("d", $time);
        $hour = date("H", $time);
        $min = date("i", $time);
        $sec = date("s", $time);
        $url = $this->baseURL."/files/{$year}/{$month}/{$day}/{$hour}/{$min}/{$sec}";

        if ($this->debug === true) {
            $this->debugOuput($url);
        }

        return $this->makeAPICall($url);
    }

    /**
     * makes an API request
     *
     * if the result is a 401 remake the request with
     * user/pass combo not token and save the token.
     * 
     * @param  string  $url      [description]
     * @param  boolean $new      [description]
     * @param  boolean $modified [description]
     * 
     * @return Object XML Document
     */
    private function makeAPICall($url, $modified = false)
    {
        $cURL = curl_init($url);
        $headers = array();
        //do we already have a token
        //if not normal login
        $token = $this->getToken();
        if ($token === false) {
            curl_setopt($cURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($cURL, CURLOPT_USERPWD, $this->credentials);
        } else {
            //token found use token to login
            $headers[] = "Authorization: Basic ".$token;
        }
        //if we have a modified time then set header
        if ($modified !== false) {
            $headers[] = "If-Modified-Since ".gmdate("D, d M Y H:i:s", $modified)." GMT";
        }

        curl_setopt($cURL, CURLINFO_HEADER_OUT, true);
        curl_setopt($cURL, CURLOPT_HEADER, true);
        curl_setopt($cURL, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        //execute
        $result = curl_exec($cURL);
        //get response information
        $info = curl_getinfo($cURL);
        $this->debugOuput($info);
        $this->debugOuput($result);
        curl_close($cURL);

        list($header, $body) = explode("\r\n\r\n", $result, 2);
        $this->checkHeaders($header);
        
        //if 401 throw exception
        if ((int) $info['http_code'] === 401) {
            throw new \Exception('Could not authenticate with API Server.');
        }
        //if 304 not modified throw exception
        if ((int) $info['http_code'] === 304) {
            throw new \Exception('No new modifications found.');
        }
        //convert to XML document and return
        return new \SimpleXMLElement(trim($body));
    }

    /**
     * checks headers and saves token if needed
     * 
     * @param  string $headers response headers
     * 
     * @return void
     */
    private function checkHeaders($headers)
    {
        $headerArray = $this->getHeaders($headers);
        foreach ($headerArray as $header => $value) {
            if ($header == 'Token') {
                $this->saveToken(trim($value));
            }
        }
    }
    /**
     * Saves the token to file.
     * 
     * @return void
     */
    private function saveToken($token)
    {
        $_SESSION['token'] = base64_encode($token);
        $_SESSION['token_expires'] = time() + (60 * 60);
    }

    /**
     * gets the token
     * 
     * @return mixed
     */
    private function getToken()
    {
        $this->debugOuput($_SESSION);
        if (!isset($_SESSION['token']) || time() > $_SESSION['token_expires']) {
            return false;
        }
        return $_SESSION['token'];
    }

    /**
     * substitue for http_parse_headers
     * 
     * @param  string $rawHeaders
     * 
     * @return array
     */
    private function getHeaders($rawHeaders)
    {
        $headers = array();
        foreach (explode("\n", $rawHeaders) as $key => $head) {
            $head = explode(':', $head, 2);
            
            if (isset($head[1])) {
                $headers[$head[0]] = trim($head[1]);
            }
        }
        return $headers;
    }

    /**
     * outputs debugging information
     * 
     * @param  Mixed   $data data to be shown on screen
     * @param  boolean $end  terminate?
     * 
     * @return void
     */
    private function debugOuput($data, $end = false)
    {
        if ($this->debug === true) {
            echo "<pre>";
            var_dump($data);
            echo "</pre>";
            if ($end) {
                die("Debug: Terminated");
            }
        }
    }
}
