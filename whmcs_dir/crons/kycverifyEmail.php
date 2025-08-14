<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\DomainSignup\Helper;

$whmcspath = "";
if (file_exists(dirname(__FILE__) . "/config.php"))
    require_once dirname(__FILE__) . "/config.php";

if (!empty($whmcspath)) {
    require_once $whmcspath . "/init.php";
} else {
    require(__DIR__ . "/../init.php");
}

$helper = new Helper();
try {
    logActivity("KYC Verification Email Cron started on " . date('Y-m-d H:i:s'));

    $clients = Capsule::table("mod_kyc_emailVerification")->get();

    foreach ($clients as $client) {
        try {
            $result = $helper->sendKYCEmail($client->clientId);

            if (!$result) {
                logActivity("KYC email not sent for clientId {$client->clientId} (conditions not met or failed send)");
            } 
        } catch (\Exception $e) {
            logActivity("KYC send failed for clientId {$client->clientId}. Error: " . $e->getMessage());
            continue; 
        }
    }

    logActivity("KYC Verification Email Cron completed on " . date('Y-m-d H:i:s'));

} catch (\Exception $e) {
    logActivity("Exception in KYC Verification Email Cron: " . $e->getMessage());
}
