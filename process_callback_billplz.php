<?php
/**
 * Billplz ZenCart Plugin
 * 
 * @package Payment Gateway
 * @author Wanzul-Hosting.com <sales@wanzul-hosting.com>
 * @version 3.0.0
 */

require('includes/application_top.php');

if (!isset($_POST['id'])){
	exit;
}

function DapatkanInfoWoo($api_key, $verification2, $host)
{
    $process = curl_init($host . $verification2);
    curl_setopt($process, CURLOPT_HEADER, 0);
    curl_setopt($process, CURLOPT_USERPWD, $api_key . ":");
    curl_setopt($process, CURLOPT_TIMEOUT, 30);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
    $return = curl_exec($process);
    curl_close($process);
    $arra = json_decode($return, true);
    return $arra;
}

$api_key	  = MODULE_PAYMENT_BILLPLZ_ID;
$collection_id = MODULE_PAYMENT_BILLPLZ_VKEY;
$id = $_POST['id'];
$mode = $_GET['mode'];
$urlpath = $mode == '2' ? 'https://billplz-staging.herokuapp.com/api/v3/bills/' : 'https://www.billplz.com/api/v3/bills/';
$info = DapatkanInfoWoo($api_key,$id,$urlpath);

if (isset($info['error'])){
	exit();
}
if ($info['collection_id'] != $collection_id){
	exit();
}

$orderid = $info['reference_1'];
$status  = $info['paid'];
$state   = $info['state'];

	if ($status) 
	{
		$db->Execute("update " . TABLE_ORDERS . "
							set orders_status = 2
	                        where orders_id = '" . (int)$orderid . "'");
	}

?>
