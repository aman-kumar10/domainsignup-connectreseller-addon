<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\DomainSignup\Helper;
use Exception;

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

        if(isset($vars['userid'])) {
            $client_exist = viewReseller($vars['email'], $vars['userid']);
            if($client_exist['status'] == "notexist_success") {
                addReseller($vars);
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

        $domains = $_SESSION['cart']['domains'];
        $in_domains = [];

        foreach ($domains as $key => $domain) {
            $in_domains[$key] = $domain['domain'];
        }

        $hasInTLD = !empty(array_filter($in_domains, fn($d) => stripos($d, '.in') !== false));

        if(isset($_SESSION['uid']) && !empty($_SESSION['uid'])) {

            $user = Capsule::table('tblclients')->where("id", $_SESSION['uid'])->first();

            if($user->country == "IN" && $hasInTLD) {
                $not_exist = viewReseller($user->email, $user->id);
    
                if($not_exist['status'] == "kyc_success") {
                    return [
                        $not_exist['message']
                    ];
                }
    
                if($not_exist['status'] == "notexist_success" && $user->country == "IN" && $hasInTLD) {
                    $addSend = addReseller((array) $user);
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

                $not_exist = viewReseller($user->email, $user->id);
    
                if($not_exist['status'] == "kyc_success") {
                    return [
                        $not_exist['message']
                    ];
                }
    
                if($not_exist['status'] == "notexist_success" && $user->country == "IN" && $hasInTLD) {
                    $addSend = addReseller((array) $user);
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

        if(isset($vars['userid']) && !empty($vars['userid'])) {
            $registrantStatus = getRegistrantStatus($vars['userid']);
            
            // Resturn the registrant status 
            if (isset($registrantStatus['status']) && $registrantStatus['status'] === "Verified") {
                return '<div class="alert alert-success" style="display:inline-block; padding:6px 12px; border-radius:4px;">KYC Verification Status: <strong>'.$registrantStatus['status'].'</strong></span></div>';
            } else {
                if(!empty($registrantStatus['status'])) {
                    $status = $registrantStatus['status'];
                } else {
                    $status = "Not Verified";
                }
                return '<div class="alert alert-warning" style="display:inline-block; padding:6px 12px; border-radius:4px;">KYC Verification Status: <strong>'.$status.'</strong></span></div>';
            }
        }

    } catch(Exception $e) {
        logActivity("Error in client area summary page hook. Error: ".$e->getMessage());
    }
});


/*
 Send KYC verification email on Daily cron Job
*/
add_hook('DailyCronJob', 1, function($vars) {
    try {
        logActivity("KYC Verification Email Cron started on " . date('Y-m-d H:i:s'));

        $clients = Capsule::table("tblclients")->get();

        $field_id = Capsule::table('tblcustomfields')->where('fieldname', 'like', 'registrantContactId|%')->where('type', 'client')->value('id');

        foreach ($clients as $client) {
            try {

                $registrantID =  Capsule::table('tblcustomfieldsvalues')->where("fieldid", $field_id)->where("relid", $client->id)->value("value");

                if($registrantID && $client->country == 'IN') {
                    $result = sendKYCverifyEmail($client->id);
                }

                if (!$result) {
                    logActivity("KYC email not sent for clientId {$client->clientId} (conditions not met or failed send)");
                } 
            } catch (Exception $e) {
                logActivity("KYC send failed for clientId {$client->clientId}. Error: " . $e->getMessage());
                continue; 
            }
        }

        logActivity("KYC Verification Email Cron completed on " . date('Y-m-d H:i:s'));

    } catch (Exception $e) {
        logActivity("Exception in KYC Verification Email Cron: " . $e->getMessage());
    }
});



/** *********************************** FUNCTIONS *********************************** */

/**
 * View Reseller client
 ** Send Email verification Email 
*/
function viewReseller($email, $userid = null) {
    try {

        $data = [
            "UserName" => $email
        ];

        // Curl Call for ViewClient
        $response = callCurl("GET", $data, "ViewClient");

        if($response['status_code'] == 200) {
            $response_data = json_decode($response['response'], true);
            $status = $response_data['responseMsg']['statusCode'];
            if($status == 200) {

                $userData = Capsule::table('tblclients')->where("id", $userid)->first();
                if($userid && $userData->country == 'IN') {
                    // Send KYC verification Email
                    $sendKYCverifyEmail = sendKYCverifyEmail($userid);
                    if($sendKYCverifyEmail['status'] == "emailSend") {
                        return ["status" => "kyc_success",
                                "message" => "Your KYC status is unverified. We have sent you a KYC verification email — please check your inbox and follow the instructions to complete the KYC verification."
                            ];
                    }
                }

                return ["status" => "exist_success",
                    "message" => "User Exist"
                ];

            } else {
                return ["status" => "notexist_success",
                    "message" => "User Doesn't Exist"
                ];
            }

        } 

    } catch(Exception $e) {
        logActivity("Error in Add Reseller Client. Error".$e->getMessage());
    }
}

/**
 * Add new Reseller client
 ** Send Email verification Email 
*/
function addReseller($vars) {
    try {

        $phone_num = $vars['phonenumber'];
        $parts = explode('.', ltrim($phone_num, '+'));
        $country_code = isset($parts[0]) ? trim($parts[0]) : ''; // Country code
        $ph_no = isset($parts[1]) ? str_replace(' ', '', trim($parts[1])) : ''; // phone number

        $data = [
            'FirstName' => $vars['firstname'],
            'LastName' => $vars['lastname'],
            'UserName' => $vars['email'],
            'Password' => $vars['firstname']."@123",
            'CompanyName' => $vars['companyname'],
            'Address1' => $vars['address1'],
            'City' => $vars['city'],
            'StateName' => $vars['state'],
            'CountryName' => $vars['country'],
            'Zip' => $vars['postcode'],
            'PhoneNo_cc' => $country_code,
            'PhoneNo' => $ph_no,
        ];

        // Curl Call for AddClient
        $response = callCurl("GET", $data, "AddClient");
        if($response['status_code'] == 200) {
            $response_data = json_decode($response['response'], true);
            $client_id = $response_data['responseData']['clientId'];

            $registrant_sendData = [
                'Id' => $client_id
            ];

            // Curl Call for DefaultRegistrantContact
            $registrantContactId = callCurl("GET", $registrant_sendData, "DefaultRegistrantContact");
            if($registrantContactId['status_code'] == 200) {
                $userID = $vars['id'] ?? $vars['userid'] ?? null;
                $registrant = json_decode($registrantContactId['response'], true);
                $registrant_id = $registrant['responseData']['registrantContactId'];

                $field_id = Capsule::table('tblcustomfields')->where('fieldname', 'like', 'registrantContactId|%')->where('type', 'client')->value('id');

                // Insert Registrant ID
                Capsule::table('tblcustomfieldsvalues')->updateOrInsert(
                    [
                        'fieldid' => $field_id,
                        'relid'   => $userID
                    ],
                    [
                        'value'   => $registrant_id
                    ]
                );
            }

            // Send KYC Email
            if($vars['country'] == "IN") {
                $sendKYCverifyEmail = sendKYCverifyEmail($userID);
                if($sendKYCverifyEmail['status'] == "emailSend") {
                    return ["status" => "kyc_success",
                            "message" => "Your KYC status is unverified. We have sent you a KYC verification email — please check your inbox and follow the instructions to complete the KYC verification."
                        ];
                }
            }

        }

    } catch(Exception $e) {
        logActivity("Error in Add Reseller Client. Error".$e->getMessage());
    }
}


/**
 * Send Email verification Email 
 */
function sendKYCverifyEmail($uid) {
    try {

        // retrive the registrant KYC verification status
        $viewRegistrantStatus = getRegistrantStatus($uid);

        if($viewRegistrantStatus['status'] == "Verified") {
            Capsule::table('mod_kyc_emailVerification')->updateOrInsert(
                ['clientId' => $uid],
                [
                    'registrantId' => $viewRegistrantStatus['registrant_id'],
                    'status'       => $viewRegistrantStatus['status'],
                    'send'         => 0,
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]
            );

            return ["status" => "statusUpdated", "message" => "The KYC verification had beed verified by the user."];

        } else {

            $exist_data = Capsule::table("mod_kyc_emailVerification")->where("clientId", $uid)->first();

            if(!$exist_data || $exist_data->status != "Verified") {
                
                $kyc_sendData = [
                    'registrantContactId' => $viewRegistrantStatus['registrant_id']
                ];

                // Curl Call for sendKYCMail
                $sendEmail = callCurl("GET", $kyc_sendData, "sendKYCMail");
                if(!empty($sendEmail['status_code']) && $sendEmail['status_code'] == 200) {
                    Capsule::table('mod_kyc_emailVerification')->updateOrInsert(
                        ['clientId' => $uid],
                        [
                            'registrantId' => $viewRegistrantStatus['registrant_id'],
                            'status'       => $viewRegistrantStatus['status'],
                            'send'         => 1,
                            'updated_at'   => date('Y-m-d H:i:s'),
                        ]
                    );

                    return ["status" => "emailSend", "message" => "The KYC verification email has beed sent to client: #{$uid}."];
                }
            }
        }

    } catch(Exception $e) {
        logActivity("Unable to send the KYC Email for clientId {$uid}". $e->getMessage());
    }
}

/**
 * Get Reseller client KYC Email verification status
 */
function getRegistrantStatus($uid) {
    try {

        $field_id = Capsule::table('tblcustomfields')->where('fieldname', 'like', 'registrantContactId|%')->where('type', 'client')->value('id');
        $registrantID =  Capsule::table('tblcustomfieldsvalues')->where("fieldid", $field_id)->where("relid", $uid)->value("value");

        if(!$registrantID) {
            logActivity("No registrantID found for clientId {$uid}");
            return false;
        }

        $status_data = [
            'RegistrantContactId' => $registrantID
        ];
        // Curl Call for ViewRegistrant
        $viewRegistrantStatus = callCurl("GET", $status_data, "ViewRegistrant");

        if($viewRegistrantStatus['status_code'] == 200) {
            $registrantData = json_decode($viewRegistrantStatus['response'], true);
            $rawStatus = $registrantData['responseData']['kycStatus'] ?? null;

            if ($rawStatus === true) {
                $registrant_status = "Verified"; 
            } elseif (empty($rawStatus) || $rawStatus === false) { 
                $registrant_status = "Not Verified"; 
            } else {
                $registrant_status = (string) $rawStatus; 
            }

            return [
                "status" => $registrant_status,
                "registrant_id" => $registrantID
            ];
        }


    } catch(Exception $e) {
        logActivity("Error to get client registrant KYC status. Error: ".$e->getMessage());
    }
}



/**
 * ******************************************************** CURL Call ********************************************************
 */
function callCurl($method, $data, $action)
{
    try {

        // $baseUrl = 'https://api.connectreseller.com/ConnectReseller/ESHOP';
        $baseUrl = 'https://stgapi.connectreseller.com/ConnectReseller/ESHOP';
        $apiKey = decrypt(Capsule::table('tblregistrars')->where('registrar', 'connectreseller')->where('setting', 'APIKey')->value('value'));
        $header = [];

        $queryString = '';
        if (!empty($data)) {
            $queryString = '&' . http_build_query($data);
        }

        $url = rtrim($baseUrl, '/') . "/{$action}?APIKey={$apiKey}{$queryString}";

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);       
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

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

    } catch (Exception $e) {
        return [
            'status_code' => 500,
            'error'       => $e->getMessage()
        ];
    }
}