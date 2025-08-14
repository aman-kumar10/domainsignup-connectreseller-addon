<?php

namespace WHMCS\Module\Addon\DomainSignup\Admin;

use Exception;
use WHMCS\Module\Addon\DomainSignup\Helper;
use WHMCS\Database\Capsule;

require "../includes/customfieldfunctions.php";

use Smarty;

global $whmcs;

class Controller
{
    private $params;


    private $tplVar = [];

    private $tplFileName;

    public $smarty;

    private $lang = [];

    /**
     * Constructor initializes parameters, paths, and language
     */
    public function __construct($params)
    {
        global $CONFIG;
        global $customadminpath;
        $this->params = $params;

        $module = $params['module'];

        $this->tplVar['adminpath']     = $customadminpath;
        $this->tplVar['rootURL']     = $CONFIG['SystemURL'];
        $this->tplVar['urlPath']     = $CONFIG['SystemURL'] . "/modules/addons/{$module}/";
        $this->tplVar['tplDIR']      = ROOTDIR . "/modules/addons/{$module}/templates/";
        $this->tplVar['header']      = ROOTDIR . "/modules/addons/{$module}/templates/header.tpl";
        $this->tplVar['modals']      = ROOTDIR . "/modules/addons/{$module}/templates/modals.tpl";
        $this->tplVar['moduleLink']  = $params['modulelink'];
        $this->tplVar['moduleName'] = $params['module'];

        $adminLang = $_SESSION['adminlang'] ?? 'english';
        $langFile  = __DIR__ . "/../../lang/{$adminLang}.php";

        if (!file_exists($langFile)) {
            $langFile = __DIR__ . "/../../lang/english.php";
        }

        global $_ADDONLANG;
        include($langFile);
        $this->lang = $_ADDONLANG;
    }

    /**
     * Dashboard tab handler
     */
    public function dashboard()
    {
        try {
            global $whmcs;
            $helper = new Helper;


            
           

            // Get products
            // $products = Capsule::table("tblproducts")->where("servertype", "iredmail")->get();
            // $this->tplVar["products"] = $products;

            $this->tplFileName = $this->tplVar['tab'] = __FUNCTION__;
            $this->output();
            
        } catch(Exception $e) {
            logActivity("Error in module Dashboard. Error: ". $e->getMessage());
        }
    }

    /**
     * Loads the assigned Smarty template
     */
    public function output()
    {
        try {
            $smarty = new Smarty();
    
            $smarty->assign('tplVar', $this->tplVar);
            $smarty->assign('LANG', $this->lang);
    
            $smarty->display($this->tplVar['tplDIR'] . $this->tplFileName . '.tpl');

        } catch(Exception $e) {
            logActivity("Error in module output. Error: ". $e->getMessage());
        }
    }
}
