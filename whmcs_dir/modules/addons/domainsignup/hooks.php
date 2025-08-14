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

add_hook('ClientAreaHeadOutput', 1, function($vars) {
    if($_GET["aman"] == 1) {
        $data = Capsule::table("mod_kyc_emailVerification")->get();
        echo "<pre>"; print_r($data); die;
    }

});


// checkout hook
// add_hook('ShoppingCartCheckoutOutput', 1, function($vars) {
//     try {

//         //  
//         $helper = new Helper;

//         $domains = $vars['cart']['domains'];
//         $in_domains = [];

//         foreach ($domains as $key => $domain) {
//             $in_domains[$key] = $domain['domain'];
//         }

//         $hasInTLD = !empty(array_filter($in_domains, fn($d) => stripos($d, '.in') !== false));

//         if(isset($_SESSION['uid']) && !empty($_SESSION['uid'])) {

//             $user = Capsule::table('tblclients')->where("id", $_SESSION['uid'])->first();

//             if($user->country == "IN" && $hasInTLD) {
//                 $not_exist = $helper->viewResellerClient($user->email, $user->id);
    
//                 if($not_exist['status'] == "kyc_success") {
//                     // return $not_exist['message'];
//                     // $_SESSION['CartError'] = $not_exist['message'];
//                     // header("Location: cart.php?a=view");
//                     // exit;
//                     return [
//                         $not_exist['message']
//                     ];
//                 }
    
//                 if($not_exist['status'] == "notexist_success" && $user->country == "IN" && $hasInTLD) {
//                     $addSend = $helper->addResellerClient((array) $user);
//                     if($addSend['status'] == "kyc_success") {
                        
//                         return [
//                             $addSend['message']
//                         ];
//                     }
//                 }
//             }
//         }

//     } catch(Exception $e) {
//         logActivity("Error to ClientAdd hook. Error: ".$e->getMessage());
//     }


// });

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