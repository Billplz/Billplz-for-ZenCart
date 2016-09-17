<?php

/**
 * Billplz ZenCart Plugin
 * 
 * @package Payment Gateway
 * @author Wanzul-Hosting.com <sales@wanzul-hosting.com>
 * @version 3.0.0
 */
if (!isset($_POST['securityToken'])) {
    exit('Security Token Not Set');
}
if (!isset($_POST['passwordapi'])) {
    exit('Password API Not Set');
}

function DapatkanLink($host, $api_key, $billplz_data) {
    $process = curl_init($host);
    curl_setopt($process, CURLOPT_HEADER, 0);
    curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
    curl_setopt($process, CURLOPT_TIMEOUT, 30);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
    $return = curl_exec($process);
    curl_close($process);
    $arr = json_decode($return, true);
    
    if (isset($arr['error'])) {
        unset($billplz_data['mobile']);
        $process = curl_init($host);
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($process, CURLOPT_POSTFIELDS, http_build_query($billplz_data));
        $return = curl_exec($process);
        curl_close($process);
        $arr = json_decode($return, true);
        if (isset($arr['error'])) {
            $arr = array("url" => "https://fb.com/billplzplugin");
            return $arr;
        } else {
            return $arr;
        }
    } else {
        return $arr;
    }
}

require('includes/application_top.php');

if (!password_verify(MODULE_PAYMENT_BILLPLZ_ID, $_POST['passwordapi'])) {
    exit('Wrong Password API');
}

$securityToken = $_POST['securityToken'];
$mode = $_POST['modestaging'];
$urlpath = $mode == '2' ? 'https://billplz-staging.herokuapp.com/api/v3/bills/' : 'https://www.billplz.com/api/v3/bills/';
//$currency = $_POST['currency'];
$bill_desc = $_POST['bill_desc'];
$orderid = $_POST['orderid'];
$amount = $_POST['amount'];
$name = $_POST['bill_name'];
$country = $_POST['country'];
$mobile = $_POST['bill_mobile'];
$email = $_POST['bill_email'];
//$returnurl = '';
//$callbackurl = '';
$api_key = MODULE_PAYMENT_BILLPLZ_ID;
$collection_id = MODULE_PAYMENT_BILLPLZ_VKEY;

//number intelligence
$custTel = $mobile;
$custTel2 = substr($mobile, 0, 1);
if ($custTel2 == '+') {
    $custTel3 = substr($mobile, 1, 1);
    if ($custTel3 != '6') {
        $custTel = "+6" . $mobile;
    }
} else if ($custTel2 == '6') {
    
} else {
    if ($custTel != '') {
        $custTel = "+6" . $mobile;
    }
}
//number intelligence
$redirecturl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/processbillplz.php?securityToken=" . $securityToken . "&mode=" . $mode;
$callbackurl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/process_callback_billplz.php?securityToken" . $securityToken . "&mode=" . $mode;

$data = array(
    'amount' => $amount * 100,
    'name' => $name,
    'email' => $email,
    'collection_id' => $collection_id,
    'mobile' => $custTel,
    'reference_1_label' => "ID",
    'reference_1' => $orderid,
    'deliver' => 'false',
    'description' => $bill_desc,
    'redirect_url' => $redirecturl,
    'callback_url' => $callbackurl
);

$url = DapatkanLink($urlpath, $api_key, $data);
header('Location: ' . $url['url'].'?auto_submit=fpx');
