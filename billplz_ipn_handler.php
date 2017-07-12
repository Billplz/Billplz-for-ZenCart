<?php
require('includes/application_top.php');
require('includes/modules/payment/billplz/billplz.php');

$api_key = MODULE_PAYMENT_BILLPLZ_API_KEY;
$x_signature = MODULE_PAYMENT_BILLPLZ_X_SIGNATURE;
$collection_id = MODULE_PAYMENT_BILLPLZ_COLLECTION_ID;
$deliver = MODULE_PAYMENT_BILLPLZ_NOTIFY;

$billplz = new BillplzGuzzle($api_key);

if (isset($_GET['referer'])) {

    if ($_GET['referer'] === 'goto') {

        $amount = isset($_POST['amount']) ? $_POST['amount'] : exit();
        $mobile = isset($_POST['mobile']) ? $_POST['mobile'] : exit();
        $description = isset($_POST['description']) ? $_POST['description'] : exit();
        $email = isset($_POST['email']) ? $_POST['email'] : exit();
        $name = isset($_POST['name']) ? $_POST['name'] : exit();
        $ipn_url = isset($_POST['ipn_url']) ? $_POST['ipn_url'] : exit();

        $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : exit();
        $hash_verify = isset($_POST['hash_verify']) ? $_POST['hash_verify'] : exit();

        /*
         *  Prepare Data for Validation SHA256
         *  Objective: Prevent Data from being altered by the user
         *  Data arranged: amount+mobile+api_key+collection_id+deliver
         *                 +email+name+ipn_url+order_id
         *  Signed with: x_signature
         *  Rules: All Lower-Case + Strip to only 6 characters
         */

        $preparedString = strtolower($amount . $mobile . $api_key . $collection_id . $deliver . $email . $name . $ipn_url . $order_id);
        $verify_hash = substr(hash_hmac('sha256', $preparedString, $x_signature), 0, 5);

        if ($verify_hash !== $hash_verify) {
            exit('Data has been tampered. Exiting..');
        }

        $billplz
            ->setCollection($collection_id)
            ->setAmount($amount)
            ->setDeliver($deliver)
            ->setDescription($description)
            ->setEmail($email)
            ->setMobile($mobile)
            ->setName($name)
            ->setPassbackURL($ipn_url, $ipn_url)
            ->setReference_1($order_id)
            ->setReference_1_Label('ID')
            ->create_bill(true);

        header('Location: ' . $billplz->getURL());
    }

    exit;
}

if (isset($_GET['billplz']['id'])) {
    $data = BillplzGuzzle::getRedirectData($x_signature);
} else if (isset($_POST['id'])) {
    $data = BillplzGuzzle::getCallbackData($x_signature);
    sleep(10);
} else {
    exit;
}

$moreData = $billplz->check_bill($data['id']);
$order_id = $moreData['reference_1'];
$comment = 'Bill ID: ' . $data['id'] . ' | ';
$comment .= 'Bill URL: ' . $moreData['url'];

if ($data['paid']) {

    if ((int) MODULE_PAYMENT_BILLPLZ_ORDER_STATUS_ID > 0) {
        $order_status_id = (int) MODULE_PAYMENT_BILLPLZ_ORDER_STATUS_ID;
    } else {
        $order_status_id = (int) DEFAULT_ORDERS_STATUS_ID;
    }
    
    $update_db = array('orders_status' => $order_status_id);
    zen_db_perform(TABLE_ORDERS, $update_db, "update", "orders_id='" . $order_id . "' ");
    
    $sql_data_array = array(
        'orders_id' => (int) $order_id,
        'orders_status_id' => $order_status_id,
        'date_added' => 'now()',
        'customer_notified' => false,
        'comments' => $comment
    );
    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
 
}

/*
 * If this is a redirect
 */

if (isset($_GET['billplz']['id'])) {

    $ssl = "SSL";
    if (ENABLE_SSL !== "true") {
        $ssl = "NONSSL";
    }

    if ($data['paid']) {
        $db->Execute("DELETE FROM " . TABLE_CUSTOMERS_BASKET);
        unset($_SESSION['cart']);
        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PROCESS, '', $ssl, true, false));
    } else {
        $db->Execute("UPDATE " . TABLE_ORDERS_STATUS_HISTORY . " SET comments = '" . $comment . "' where orders_id = '" . (int) $order_id . "'");

        $nb_error = "Payment failed. Please make payment again. ";
        $messageStack->add_session('checkout_payment', $nb_error, 'error');

        zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', $ssl, true, false));
    }
} else {
    echo 'OK';
    exit;
}
