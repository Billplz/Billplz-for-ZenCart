<?php

/**
 * Billplz ZenCart Plugin
 * 
 * @package Payment Gateway
 * @author Wanzul-Hosting.com <sales@wanzul-hosting.com>
 * @version 2.0.0
 */

if (!isset($_GET['billplz']['id'])){
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
 
require('includes/application_top.php');
global $db, $order;

$api_key	  = MODULE_PAYMENT_BILLPLZ_ID;
$collection_id = MODULE_PAYMENT_BILLPLZ_VKEY;
$id = $_GET['billplz']['id'];
$mode = $_GET['mode'];
$urlpath = $mode == '2' ? 'https://billplz-staging.herokuapp.com/api/v3/bills/' : 'https://www.billplz.com/api/v3/bills/';
$info = DapatkanInfoWoo($api_key,$id,$urlpath);

if (isset($info['error'])){
	exit("ID Tak betul");
}
if ($info['collection_id'] != $collection_id){
	exit("Collection ID Palsu");
}

$orderid = $info['reference_1'];
$status  = $info['paid'];
$state   = $info['state'];

$ssl = "NONSSL";
if ( ENABLE_SSL != "false" ) 
{
	$ssl = "SSL";
}

	if ($status) 
	{
		$db->Execute("update " . TABLE_ORDERS . "
							set orders_status = 2
	                        where orders_id = '" . (int)$orderid . "'");

		$db->Execute("delete from ". TABLE_CUSTOMERS_BASKET);
		unset($_SESSION['cart']);
		$jump = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF'])."/index.php?main_page=checkout_success";
		header('Location: '.$jump);
	}
	else
	{
		$nb_error = "Unsuccessfull Payment. Please make payment again. ";
		$messageStack->add_session('checkout_payment', $nb_error, 'error');
		zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', $ssl, true, false));
	}
exit();
?>