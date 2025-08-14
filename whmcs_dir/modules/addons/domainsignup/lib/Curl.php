<?php

namespace WHMCS\Module\Addon\DomainSignup;
use WHMCS\Module\Addon\DomainSignup\Helper;
use WHMCS\Database\Capsule;

class Curl{
    private $baseUrl = '';
    public $token = '';
    private $key = '';
    public $method = 'GET';
    public $data = [];
    public $header = [];
    public $endPoint = '';
    public $action = '';
    public $curl = null;

    public function __construct(){  

        // Base url
        if (Capsule::table('tbladdonmodules')->where('setting', 'enableTestURL')->where('module', 'domainsignup')->where('value', 'on')->first()) {
            $this->baseUrl = Capsule::table('tbladdonmodules')->where('setting', 'testURL')->where('module', 'domainsignup')->value('value');
        }else{
            $this->baseUrl = Capsule::table('tbladdonmodules')->where('setting', 'stagURL')->where('module', 'domainsignup')->value('value');
        }

        // API key
        $this->key = Capsule::table('tbladdonmodules')->where('setting', 'apiKey')->where('module', 'domainsignup')->value('value');

    }

    /* Curl Handlig */
    function curlCall($method, $data, $action)
    {
        try {
            $queryString = '';
            if (!empty($data)) {
                $queryString = '&' . http_build_query($data);
            }

            $url = rtrim($this->baseUrl, '/') . "/{$action}?APIKey={$this->key}{$queryString}";

            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);       
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->header);

            // Execute request
            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            // Module log
            logModuleCall( 'domainsignup', $action, $url, $responseBody);

            return [
                'status_code' => $httpCode,
                'response'    => $responseBody
            ];

        } catch (\Exception $e) {
            return [
                'status_code' => 500,
                'error'       => $e->getMessage()
            ];
        }
    }

}