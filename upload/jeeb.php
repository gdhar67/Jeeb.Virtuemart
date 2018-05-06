<?php

function jeeblog($contents)
{
    error_log($contents);
}

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin'))
{
    require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentJeeb extends vmPSPlugin
{

    /**
     * @param $subject
     * @param $config
     */
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable   = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $varsToPush        = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     *
     * @return
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Jeeb Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return array
     */
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id'         => 'int(1) UNSIGNED',
            'order_number'                => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name'                => 'varchar(5000)',
            'token'                => 'varchar(5000)',
            'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency'            => 'char(3)',
            'logo'			  => 'varchar(5000)'
        );

        return $SQLfields;
    }


    /**
     * Display stored payment data for an order
     *
     * @param $virtuemart_order_id
     * @param $virtuemart_payment_id
     *
     * @return
     */
    function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_payment_id)
    {
        if (!$this->selectedThisByMethodId($virtuemart_payment_id))
        {
            return NULL; // Another method was selected, do nothing
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemart_order_id)))
        {
            return NULL;
        }
        VmConfig::loadJLang('com_virtuemart');

        $html = '<table class="adminlist">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('JEEB_PAYMENT_NAME', $paymentTable->payment_name);
        $html .= $this->getHtmlRowBE('JEEB_PAYMENT_TOTAL_CURRENCY', $paymentTable->payment_order_total . ' ' . $paymentTable->payment_currency);
        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * @param VirtueMartCart $cart
     * @param                $method
     * @param array          $cart_prices
     *
     * @return
     */
    function getCosts(VirtueMartCart $cart, $method, $cart_prices)
    {
        if (preg_match('/%$/', $method->cost_percent_total))
        {
            $cost_percent_total = substr($method->cost_percent_total, 0, -1);
        }
        else
        {
            $cost_percent_total = $method->cost_percent_total;
        }

        return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $cart
     * @param $method
     * @param $cart_prices
     *
     * @return boolean
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        // $this->convert($method);
        //         $params = new JParameter($payment->payment_params);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amount      = $cart_prices['salesPrice'];
        $amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
            OR
            ($method->min_amount <= $amount AND ($method->max_amount == 0)));
        if (!$amount_cond)
        {
            return false;
        }
        $countries = array();
        if (!empty($method->countries))
        {
            if (!is_array($method->countries))
            {
                $countries[0] = $method->countries;
            }
            else
            {
                $countries = $method->countries;
            }
        }

        // probably did not gave his BT:ST address
        if (!is_array($address))
        {
            $address                          = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id']))
        {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0)
        {
            return true;
        }

        return false;
    }

    /**
     * @param $method
     */
    // function convert($method)
    // {
    //     $method->min_amount = (float)$method->min_amount;
    //     $method->max_amount = (float)$method->max_amount;
    // }

    /*
     * We must reimplement this triggers for joomla 1.7
     */

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     * @param $jplugin_id
     *
     * @return
     */
    function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return $this->onStoreInstallPluginTable($jplugin_id);
    }

    /*
     * plgVmonSelectedCalculatePricePayment
     * Calculate the price (value, tax_id) of the selected method
     * It is called by the calculator
     * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param                $cart_prices_name
     *
     * @return
     */

    public function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
    {
        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     *
     * @return
     */
    function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id)))
        {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element))
        {
            return false;
        }
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     *
     * @param VirtueMartCart $cart
     * @param array          $cart_prices
     * @param                $paymentCounter
     *
     * @return
     */
    function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        return $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param $virtuemart_order_id
     * @param $virtuamart_paymentmethod_id
     * @param $payment_name
     */
    public function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id  method used for this order
     *
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     */
    function plgVmonShowOrderPrintPayment($order_number, $method_id)
    {
        return $this->onShowOrderPrint($order_number, $method_id);
    }

    /**
     * @param $name
     * @param $id
     * @param $data
     *
     * @return
     */
    function plgVmDeclarePluginParamsPayment($name, $id, &$data)
    {
        return $this->declarePluginParams('payment', $name, $id, $data);
    }
    function plgVmDeclarePluginParamsPaymentVM3( &$data) {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * @param $name
     * @param $id
     * @param $table
     *
     * @return
     */
    function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        return $this->setOnTablePluginParams($name, $id, $table);
    }


    /**
     * This event is fired by Offline Payment. It can be used to validate the payment data as entered by the user.
     *
     * @return
     */
    function plgVmOnPaymentNotification ()
    {
        if (!class_exists ('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $postdata = file_get_contents("php://input");
        $json = json_decode($postdata, true);

        $modelOrder = VmModel::getModel ('orders');
        $order      = $modelOrder->getOrder($json['orderNo']);
        if (!$order)
        {
            bplog('order could not be loaded '.$json['orderNo']);
            return NULL;
        }

        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

        // Call Jeeb
        $network_uri = "https://core.jeeb.io/api/";


        jeeblog("Entered Jeeb-Notification");
        if ( $json['stateId']== 2 ) {
          jeeblog('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
        }
        else if ( $json['stateId']== 3 ) {
          jeeblog('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
          $order['order_status'] = 'U';
          $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
        }
        else if ( $json['stateId']== 4 ) {
          jeeblog('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
          $data = array(
            "token" => $json["token"]
          );

          $data_string = json_encode($data);
          $api_key = $method->merchant_apikey;
          $url = $network_uri.'payments/' . $api_key . '/confirm';
          jeeblog("Signature:".$api_key." Base-Url:".$network_uri." Url:".$url);

          $ch = curl_init($url);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json',
              'Content-Length: ' . strlen($data_string))
          );

          $result = curl_exec($ch);
          $data = json_decode( $result , true);
          jeeblog("data = ".var_export($data, TRUE));


          if($data['result']['isConfirmed']){
            jeeblog('Payment confirmed by jeeb');
          $order['order_status'] = 'C';
          $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);
          }
          else {
            jeeblog('Payment rejected by jeeb');
          }
        }
        else if ( $json['stateId']== 5 ) {
          jeeblog('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
          $order['order_status'] = 'X';
          $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        }
        else if ( $json['stateId']== 6 ) {
          jeeblog('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
          $order['order_status'] = 'X';
          $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        }
        else if ( $json['stateId']== 7 ) {
          jeeblog('Order Id received = '.$json['orderNo'].' stateId = '.$json['stateId']);
          $order['order_status'] = 'X';
          $modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);

        }
        else{
          jeeblog('Cannot read state id sent by Jeeb');
        }
    }

    /**
     * @param $html
     *
     * @return bool|null|string
     */
    function plgVmOnPaymentResponseReceived (&$html)
    {
      jeeblog("Entered - Payment Recieved");

        if (!class_exists ('VirtueMartCart'))
        {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'cart.php');
        }
        if (!class_exists ('shopFunctionsF'))
        {
            require(JPATH_VM_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
        }
        if (!class_exists ('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        // the payment itself should send the parameter needed.
        $virtuemart_paymentmethod_id = JRequest::getInt ('pm', 0);
        $order_number                = JRequest::getString ('on', 0);
        $vendorId                    = 0;

        if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id)))
        {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement ($method->payment_element))
        {
            return NULL;
        }

        if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number)))
        {
            return NULL;
        }
        if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id)))
        {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        $payment_name = $this->renderPluginName ($method);
        $html         = $this->_getPaymentResponseHtml ($paymentTable, $payment_name);

        //We delete the old stuff
        // get the correct cart / session
        return TRUE;
    }

    /**
     * This shows the plugin for choosing in the payment list of the checkout process.
     *
     * @param VirtueMartCart $cart
     * @param integer        $selected
     * @param                $htmlIn
     *
     * @return
     */
    function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $session = JFactory::getSession ();
        $errors  = $session->get ('errorMessages', 0, 'vm');

        if($errors != "")
        {
            $errors = unserialize($errors);
            $session->set ('errorMessages', "", 'vm');
        }
        else
        {
            $errors = array();
        }

        return $this->displayListFE ($cart, $selected, $htmlIn);
    }

    /**
     * getGMTTimeStamp:
     *
     * this function creates a timestamp formatted as per requirement in the
     * documentation
     *
     * @return string The formatted timestamp
     */
    public function getGMTTimeStamp()
    {
        /* Format: YYYYDDMMHHNNSSKKK000sOOO
            YYYY is a 4-digit year
            DD is a 2-digit zero-padded day of month
            MM is a 2-digit zero-padded month of year (January = 01)
            HH is a 2-digit zero-padded hour of day in 24-hour clock format (midnight =0)
            NN is a 2-digit zero-padded minute of hour
            SS is a 2-digit zero-padded second of minute
            KKK is a 3-digit zero-padded millisecond of second
            000 is a Static 0 characters, as Jeeb does not store nanoseconds
            sOOO is a Time zone offset, where s is + or -, and OOO = minutes, from GMT.
         */
        $tz_minutes = date('Z') / 60;

        if ($tz_minutes >= 0)
        {
            $tz_minutes = '+' . sprintf("%03d",$tz_minutes); //Zero padding in-case $tz_minutes is 0
        }

        $stamp = date('YdmHis000000') . $tz_minutes; //In some locales, in some situations (i.e. Magento 1.4.0.1) some digits are missing. Added 5 zeroes and truncating to the required length. Terrible terrible hack.

        return $stamp;
    }

    /**
     * @param       $data
     * @param array $outputArray
     *
     * @return
     */
    private function makeXMLTree ($data, &$outputArray = array())
    {
        $parser = xml_parser_create();
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        $result = xml_parse_into_struct($parser, $data, $values, $tags);
        xml_parser_free($parser);
        if ($result == 0)
        {
            return false;
        }

        $hash_stack = array();
        foreach ($values as $key => $val)
        {
            switch ($val['type'])
            {
            case 'open':
                array_push($hash_stack, $val['tag']);
                break;
            case 'close':
                array_pop($hash_stack);
                break;
            case 'complete':
                array_push($hash_stack, $val['tag']);
                // ATTN, I really hope this is sanitized
                eval("\$outputArray['" . implode($hash_stack, "']['") . "'] = \"{$val['value']}\";");
                array_pop($hash_stack);
                break;
            }
        }

        return true;
    }

    /**
     * @param $cart
     * @param $order
     *
     * @return
     */
    function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id)))
        {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element))
        {
            return false;
        }
        //         $params = new JParameter($payment->payment_params);
        // $lang     = JFactory::getLanguage();
        // $filename = 'com_virtuemart';
        // $lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;
        $html     = "";

        VmConfig::loadJLang('com_virtuemart',true);
        VmConfig::loadJLang('com_virtuemart_orders', TRUE);

        $this->getPaymentCurrency($method);


        if (!class_exists('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }

        $this->getPaymentCurrency($method, true);
        $currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
        $email_currency = $this->getEmailCurrency($method);


        // jeeblog(print_r( $order, true ));

        jeeblog("Entered Confirm payment.....");

        $network_uri = "https://core.jeeb.io/api/" ;
        $baseCur     = $method->baseCur;
        $target_cur  = $method->targetCur;
        $lang        = $method->lang=="none"? NULL : $method->lang ;


        // Convert irr to btn
        $amount = convertIrrToBtc ( $network_uri, $order['details']['BT']->order_total, $method->merchant_apikey, $baseCur );

        jeeblog("Url:".$network_uri." Bitcoin:".$btc." Notification Url:".(JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component')).' callback Url:'. (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'))));
        $params = array(
          'orderNo'          => $order['details']['BT']->virtuemart_order_id,
          'value'            => $amount,
          'webhookUrl'       => (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component')),
          'callBackUrl'      => (JROUTE::_ (JURI::root () . 'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id . '&Itemid=' . JRequest::getInt ('Itemid'))),
          'allowReject'      => $method->network == "test" ? false : true,
          "coins"            => $target_cur,
          "allowTestNet"     => $method->network == "test" ? true : false,
          "language"         => $lang
        );
        // Create Invoice for payment in the Jeeb server
        $token = createInvoice ( $network_uri, $amount, $params, $method->merchant_apikey );
        jeeblog('Token:'.$token);

        // Redirecting user for the payment
        redirectPayment ( $network_uri, $token );
        exit;


    }

    /**
     * @param $virtualmart_order_id
     * @param $html
     */
    function _handlePaymentCancel ($virtuemart_order_id, $html)
    {
        if (!class_exists ('VirtueMartModelOrders'))
        {
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        }
        $modelOrder = VmModel::getModel ('orders');
        $modelOrder->remove (array('virtuemart_order_id' => $virtuemart_order_id));
        // error while processing the payment
        $mainframe = JFactory::getApplication ();
        $mainframe->redirect (JRoute::_ ('index.php?option=com_virtuemart&view=cart&task=editpayment'), $html);
    }

    /**
     * takes a string and returns an array of characters
     *
     * @param string $input string of characters
     * @return array
     */
    function toCharArray($input)
    {
        $len = strlen ( $input );
        for($j = 0; $j < $len; $j ++)
        {
            $char [$j] = substr ( $input, $j, 1 );
        }
        return ($char);
    }
    /**
     * @param $virtuemart_paymentmethod_id
     * @param $paymentCurrencyId
     * @return bool|null
     */
    function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return NULL; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return FALSE;
        }
        if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
            // JError::raiseWarning(500, $db->getErrorMsg());
            return '';
        }
        if (empty($payments[0]->email_currency)) {
            $vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
            $db = JFactory::getDBO();
            $q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
            $db->setQuery($q);
            $emailCurrencyId = $db->loadResult();
        } else {
            $emailCurrencyId = $payments[0]->email_currency;
        }
    }
}

function convertIrrToBtc($url, $amount, $signature, $baseCur) {
  jeeblog("Entered into convertIrrToBtc...");
  $ch = curl_init($url.'currency?'.$signature.'&value='.$amount.'&base='.$baseCur.'&target=btc');
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json')
);

$result = curl_exec($ch);
$data = json_decode( $result , true);
jeeblog("Response => ".var_export($data, TRUE));
// Return the equivalent bitcoin value acquired from Jeeb server.
return (float) $data["result"];

}

// Create Invoice which can be paid over Jeeb's Payment Gateway.
function createInvoice($url, $amount, $options = array(), $signature) {
    jeeblog("Entered into createInvoice...");
    $post = json_encode($options);

    $ch = curl_init($url.'payments/' . $signature . '/issue/');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'Content-Length: ' . strlen($post))
    );

    $result = curl_exec($ch);
    $data = json_decode( $result , true);
    jeeblog("data = ".var_export($data, TRUE));

    return $data['result']['token'];

}

// Redirect to Jeeb's payment Gateway.
function redirectPayment($url, $token) {
  jeeblog("Entered into redirectPayment...");
  // Using Auto-submit form to redirect user with the token
  echo "<form id='form' method='post' action='".$url."payments/invoice'>".
          "<input type='hidden' autocomplete='off' name='token' value='".$token."'/>".
         "</form>".
         "<script type='text/javascript'>".
              "document.getElementById('form').submit();".
         "</script>";
}

defined('_JEXEC') or die('Restricted access');

/*
 * This class is used by VirtueMart Payment  Plugins
 * which uses JParameter
 * So It should be an extension of JElement
 * Those plugins cannot be configured througth the Plugin Manager anyway.
 */
if (!class_exists( 'VmConfig' ))
{
    require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart'.DS.'helpers'.DS.'config.php');
}
if (!class_exists('ShopFunctions'))
{
    require(JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'shopfunctions.php');
}

// Check to ensure this file is within the rest of the framework
defined('JPATH_BASE') or die();
