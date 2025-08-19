<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\DomainSignup\Helper;

if(!defined("WHMCS")) {
    die("This file can not be accessed directly!");
}


/* 
 Add Reseller client 
 While new WHMCS client added
*/ 
add_hook("ClientAdd", 1, function($vars) 
{
    try {

        $helper = new Helper;

        if(isset($vars['userid']) && $vars['country'] == "IN") {
            $client_exist = $helper->viewResellerClient($vars['email'], $vars['userid']);
            if($client_exist['status'] == "notexist_success") {
                $helper->addResellerClient($vars);
            }
        }

    } catch(Exception $e) {
        logActivity("Error to ClientAdd hook. Error: ".$e->getMessage());
    }
});


/*
 Return Checkout validations,
 Reseller Client KYC Email Verification
*/
add_hook('ShoppingCartValidateCheckout', 1, function($vars) 
{
    try {

        $helper = new Helper;

        $domains = $_SESSION['cart']['domains'];
        $in_domains = [];

        foreach ($domains as $key => $domain) {
            $in_domains[$key] = $domain['domain'];
        }

        $hasInTLD = !empty(array_filter($in_domains, fn($d) => stripos($d, '.in') !== false));

        if(isset($_SESSION['uid']) && !empty($_SESSION['uid'])) {

            $user = Capsule::table('tblclients')->where("id", $_SESSION['uid'])->first();

            if($user->country == "IN" && $hasInTLD) {
                $not_exist = $helper->viewResellerClient($user->email, $user->id);
    
                if($not_exist['status'] == "kyc_success") {
                    return [
                        $not_exist['message']
                    ];
                }
    
                if($not_exist['status'] == "notexist_success" && $user->country == "IN" && $hasInTLD) {
                    $addSend = $helper->addResellerClient((array) $user);
                    if($addSend['status'] == "kyc_success") {
                        return [
                            $addSend['message']
                        ];
                    }
                }
            }
        }

    } catch(Exception $e) {
        logActivity("Error to ClientAdd hook. Error: ".$e->getMessage());
    }
});


/* 
 Admin side domain checkout
*/
add_hook('PreShoppingCartCheckout', 1, function($vars) 
{
    try {

        $helper = new Helper;
        
        // Admin checkout (works only when admin place the order)
        if(isset($_SESSION['adminid']) && !empty($_SESSION['adminid'])) {
            
            $userId = Capsule::table("tblorders")->where("id", $_SESSION['orderdetails']['OrderID'])->value("userid");
            $user = Capsule::table('tblclients')->where("id", $userId)->first();

            $domains = $vars['products'];
            $in_domains = [];

            foreach ($domains as $key => $domain) {
                $in_domains[$key] = $domain['domain'];
            }

            $hasInTLD = !empty(array_filter($in_domains, fn($d) => stripos($d, '.in') !== false));

            if($user->country == "IN" && $hasInTLD) {

                $not_exist = $helper->viewResellerClient($user->email, $user->id);
    
                if($not_exist['status'] == "kyc_success") {
                    return [
                        $not_exist['message']
                    ];
                }
    
                if($not_exist['status'] == "notexist_success" && $user->country == "IN" && $hasInTLD) {
                    $addSend = $helper->addResellerClient((array) $user);
                    if($addSend['status'] == "kyc_success") {
                        return [
                            $addSend['message']
                        ];
                    }
                }
            }

        }
    } catch(Exception $e) {
        logActivity("Error in PreShoppingCartCheckout hook. Error:".$e->getMessage());
    }
});


/*
 Display client KYC verification status.
 On client summary page at admin side
*/
add_hook('AdminAreaClientSummaryPage', 1, function($vars) 
{
    try {

        $helper = new Helper;

        if(isset($vars['userid']) && !empty($vars['userid'])) {
            $registrantStatus = $helper->getRegistrantClientStatus($vars['userid']);
            
            // Resturn the registrant status 
            if ($registrantStatus['status'] === "Verified") {
                return '<div class="alert alert-success">KYC verification for the registrant client has been completed.</div>';
            } else {
                return '<div class="alert alert-warning">KYC verification for the registrant client has not been completed yet.</div>';
            }
        }

    } catch(Exception $e) {
        logActivity("Error in client area summary page hook. Error: ".$e->getMessage());
    }
});

