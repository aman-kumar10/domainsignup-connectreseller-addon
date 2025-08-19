<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\DomainSignup\Helper;

if(!defined("WHMCS")) {
    die("This file can not be accessed directly!");
}

// Add client
add_hook("ClientAdd", 1, function($vars) {
    try {
        //  
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



// Return Checkout validations
add_hook('ShoppingCartValidateCheckout', 1, function($vars) {
    try {
        //  
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


add_hook('PreShoppingCartCheckout', 1, function($vars) {
    //
    try {

        $helper = new Helper;
        
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

// Display client KYC status admin area
add_hook('AdminAreaClientSummaryPage', 1, function($vars) {
    try {
        $helper = new Helper;

        if(isset($vars['userid']) && !empty($vars['userid'])) {
            $registrantStatus = $helper->getRegistrantClientStatus($vars['userid']);
            
            if ($registrantStatus['status'] === "Verified") {
                return '<div class="alert alert-success">Registrant client KYC status is verified.</div>';
            } else {
                return '<div class="alert alert-warning">Registrant client KYC verification is not verified.</div>';
            }
        }

    } catch(Exception $e) {
        logActivity("Error in client area summary page hook. Error: ".$e->getMessage());
    }
});