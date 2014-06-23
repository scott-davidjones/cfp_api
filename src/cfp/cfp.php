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

class CfpApi
{
    /**
     * CFP API Version
     * @var integer
     */
    public $apiVersion = 7;
    /**
     * authentication token
     * @var string
     */
    protected $token = '';

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
        $this->baseURL = "http://webservices.vebra.com/export/{$datafeed}/v{$this->version}";

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
    public function getPropertyList()
    {
        $this->propertiesURL = $this->branchURL."/property";

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
    public function getPropertyDetails($propertyID)
    {
        $propertyURL = $this->propertiesURL."/{$propertyID}";

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
        $sec = date("S", $time);
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
        $sec = date("S", $time);
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
        if ($this->token === '') {
            curl_setopt($cURL, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($cURL, CURLOPT_USERPWD, $this->credentials);
        } else {
            $headers = array();
            $headers[] = "Authorization: Basic ".$this->token;
            if ($modified) {
                $headers[] = "If-Modified-Since ".gmdate("D, d M Y H:i:s", $modified)." GMT";
            }
            curl_setopt($cURL, CURLOPT_HEADER, $headers);
        }
        
        curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($cURL);
        $info = curl_getinfo($cURL);
        curl_close($cURL);
        //if 401 rerun the call with non-token auth
        if ((int) $info['http_code'] === 401) {
            $result = $this->makeAPICall($url, $modified);
            $this->token = base64_encode($info['Token']);
        }
        //convert to XML document and return
        return new \SimpleXMLElement($result);
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
        echo "<pre>";
        var_dump($data);
        echo "</pre>";
        if ($end) {
            die("Debug: Terminated");
        }
    }
}
