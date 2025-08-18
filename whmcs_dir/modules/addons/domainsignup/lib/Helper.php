<?php

namespace WHMCS\Module\Addon\DomainSignup;

use Exception;
use WHMCS\Database\Capsule;

class Helper
{

    public function viewResellerClient($email, $userid = null) {
        try {

            $curl = new Curl();

            $data = [
                "UserName" => $email
            ];

            $response = $curl->curlCall("GET", $data, "ViewClient");

            if($response['status_code'] == 200) {
                $response_data = json_decode($response['response'], true);
                $status = $response_data['responseMsg']['statusCode'];
                if($status == 200) {

                    if($userid) {
                        $sendKYCEmail = $this->sendKYCEmail(uid: $userid);
                        if($sendKYCEmail['status'] == "emailSend") {
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

    public function addResellerClient($vars) {
        try {

            $curl = new Curl();

            $phone_numbe = $vars['phonenumber'];
            $parts = explode('.', ltrim($phone_numbe, '+'));
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

            // Add client
            $response = $curl->curlCall("GET", $data, "AddClient");
            if($response['status_code'] == 200) {
                $response_data = json_decode($response['response'], true);
                $client_id = $response_data['responseData']['clientId'];

                $registrant_sendData = [
                    'Id' => $client_id
                ];

                // Get registrant ID
                $registrantContactId = $curl->curlCall("GET", $registrant_sendData, "DefaultRegistrantContact");
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

                // View registrant
                $view_sendData = [
                    'RegistrantContactId' => $registrant_id
                ];

                // View Registrant Status
                $registrant_data = $curl->curlCall("GET", $view_sendData, "ViewRegistrant");
                if($registrant_data['status_code'] == 200) {
                    $registrantData = json_decode($registrant_data['response'], true);
                    $registrant_status = $registrantData['responseData']['kycStatus'];

                    if($registrant_status != "Verified") {
                        // Send KYC Email
                        $sendKYCEmail = $this->sendKYCEmail($userID);
                        if($sendKYCEmail['status'] == "emailSend") {
                            return ["status" => "kyc_success",
                                    "message" => "Your KYC status is unverified. We have sent you a KYC verification email — please check your inbox and follow the instructions to complete the KYC verification."
                                ];
                        }
                    }

                }

            }

        } catch(Exception $e) {
            logActivity("Error in Add Reseller Client. Error".$e->getMessage());
        }
    }


    public function sendKYCEmail($uid) {
        try {

            $curl = new Curl();

            $field_id = Capsule::table('tblcustomfields')->where('fieldname', 'like', 'registrantContactId|%')->where('type', 'client')->value('id');
            $registrantID =  Capsule::table('tblcustomfieldsvalues')->where("fieldid", $field_id)->where("relid", $uid)->value("value");

            if (!$registrantID) {
                logActivity("No registrantID found for clientId {$uid}");
                return false;
            }
            
            // Check registrant status
            $status_data = [
                'RegistrantContactId' => $registrantID
            ];
            $viewRegistrantStatus = $curl->curlCall("GET", $status_data, "ViewRegistrant");

            if(!empty($viewRegistrantStatus['status_code']) && $viewRegistrantStatus['status_code'] == 200) {
                $registrantData = json_decode($viewRegistrantStatus['response'], true);
                $registrant_status = $registrantData['responseData']['kycStatus'];

                if($registrant_status == "Verified") {
                    Capsule::table('mod_kyc_emailVerification')->updateOrInsert(
                        ['clientId' => $uid],
                        [
                            'registrantId' => $registrantID,
                            'status'       => $registrant_status,
                            'send'         => 0,
                            'updated_at'   => date('Y-m-d H:i:s'),
                        ]
                    );

                    return ["status" => "statusUpdated", "message" => "The KYC verification had beed verified by the user."];

                } else {

                    $exist_data = Capsule::table("mod_kyc_emailVerification")->where("clientId", $uid)->where("registrantId", $registrantID)->first();

                    if(!$exist_data || $exist_data->status != "Verified") {
                        
                        $kyc_sendData = [
                            'registrantContactId' => $registrantID
                        ];

                        // Send KYC email
                        $sendEmail = $curl->curlCall("GET", $kyc_sendData, "sendKYCMail");
                        if(!empty($sendEmail['status_code']) && $sendEmail['status_code'] == 200) {
                            Capsule::table('mod_kyc_emailVerification')->updateOrInsert(
                                ['clientId' => $uid],
                                [
                                    'registrantId' => $registrantID,
                                    'status'       => "NA",
                                    'send'         => 1,
                                    'updated_at'   => date('Y-m-d H:i:s'),
                                ]
                            );

                            return ["status" => "emailSend", "message" => "The KYC verification email has beed sent to client: #{$uid}."];
                        }
                    }
                }

            }

        } catch(Exception $e) {
            logActivity("Unable to send the KYC Email for clientId {$uid}". $e->getMessage());
        }
    }

    
}

