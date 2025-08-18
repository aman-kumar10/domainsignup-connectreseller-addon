<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\DomainSignup\Admin\AdminDispatcher;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}


function domainsignup_config()
{
    return [
        'name' => 'Domain Signup',
        'description' => 'Domain Signup Addon Module',
        'author' => 'WHMCS GLOBAL SERVICES',
        'language' => 'english',
        'version' => '1.0',
        'fields' => [
            'productionURL' => [
                'FriendlyName' => 'Production URL',
                'Type' => 'text',
                'Size' => '225',
            ],
            'testURL' => [
                'FriendlyName' => 'Test URL',
                'Type' => 'text',
                'Size' => '225',
            ],
            'apiKey' => [
                'FriendlyName' => 'API Key',
                'Type' => 'text',
                'Size' => '225',
            ],
            'enableTestURL' => [
                'FriendlyName' => 'Enable Test Mode',
                'Type' => 'yesno',
            ],
        ]
    ];
}


function domainsignup_activate()
{
    try {
        // Create custom client field for storing Registrant Contact Id
        if(Capsule::table('tblcustomfields')->where('fieldname','like','registrantContactId|%')->count()==0){
            Capsule::table('tblcustomfields')->insert([
                'type'=>'client', 'relid'=>0, 'fieldname'=>'registrantContactId|Registrant Contact Id', 'fieldtype'=>'text', 'description'=>'', 'fieldoptions'=>'', 'regexpr'=>'', 'adminonly'=> '', 'required'=>'', 'showorder'=>'', 'showinvoice'=>'', 'sortorder'=>0,
            ]);
        }

        // KYC email verification
        if (!Capsule::schema()->hasTable('mod_kyc_emailVerification')) {
            Capsule::schema()->create('mod_kyc_emailVerification', function ($table) {
                $table->increments('id');
                $table->text('clientId');
                $table->string('registrantId');
                $table->string('status');
                $table->string('send');
                $table->timestamps();
            });
        }

        return [
            'status' => 'success',
            'description' => 'Domain Signup addon module has been activated.',
        ];
    } catch (\Exception $e) {
        return [
            'status' => "error",
            'description' => 'Unable to activate the Domain Signup addon module: ' . $e->getMessage(),
        ];
    }
}


function domainsignup_deactivate()
{
    try {
        // 
        return [
            'status' => 'success',
            'description' => 'Your Domain Signup addon module has been deactivated',
        ];
    } catch (\Exception $e) {
        return [
            "status" => "error",
            "description" => "Unable to deactivate the Domain Signup addon module: {$e->getMessage()}",
        ];
    }
}


function domainsignup_output($vars)
{
    // 
    $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'dashboard';

    $dispatcher = new AdminDispatcher();
    $response = $dispatcher->dispatch($action, $vars);
    return $response;
}

