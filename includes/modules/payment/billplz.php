<?php

class billplz extends base
{

    public $code = 'billplz';
    public $title = 'Billplz';
    public $description = 'Accept Payment using Billplz';
    public $enabled = ((MODULE_PAYMENT_BILLPLZ_STATUS == 'True') ? true : false);
    public $sort_order = MODULE_PAYMENT_BILLPLZ_SORT_ORDER;
    public $order_status = null;
    public $form_action_url = null;

    public function __construct()
    {
        global $order;
        if ((int) MODULE_PAYMENT_BILLPLZ_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_BILLPLZ_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }

        /*
         * Set Form Action URL on Checkout
         */

        $this->form_action_url = zen_href_link('billplz_ipn_handler.php', 'referer=goto', 'SSL', false, false, true);
    }

    public function update_status()
    {
        global $db, $order;
        if (($this->enabled === true) && (int) MODULE_PAYMENT_BILLPLZ_ZONE > 0) {
            $check_flag = false;
            $check = $db->Execute("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . MODULE_PAYMENT_BILLPLZ_ZONE . "' AND zone_country_id = '" . $order->billing['country']['id'] . "' ORDER BY zone_id");

            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if (!$check_flag) {
                $this->enabled = false;
            }
        }
    }

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        return [
            'id' => $this->code,
            'module' => 'Billplz Payment Gateway',
            'icon' => 'Billplz Payment Gateway'
        ];
    }

    public function pre_confirmation_check()
    {
        //Nothing
        return false;
    }

    public function confirmation()
    {
        global $db, $order, $currencies;
        /*
         *  Create the order based on customer id
         */
        $customer_id = $_SESSION['customer_id'];
        $customer_query = "SELECT c.`customers_firstname` , c.`customers_lastname` , c.`customers_email_address` , c.`customers_telephone`, ab.`entry_company` ,  ab.`entry_street_address` ,  ab.`entry_suburb` , ab.`entry_postcode` , ab.`entry_city` , ab.`entry_state` , ab.`entry_country_id` , 
              ab.`entry_zone_id` FROM " . TABLE_CUSTOMERS . " c JOIN " . TABLE_ADDRESS_BOOK . " ab ON ( c.`customers_default_address_id` = ab.`address_book_id` ) 
              WHERE c.`customers_id` = " . $customer_id;
        $customer_info = $db->Execute($customer_query);
        $customer_info->fields['format_id'] = zen_get_address_format_id($customer_info->fields['entry_country_id']);
        $customer_info->fields['country_name'] = zen_get_country_name($customer_info->fields['entry_country_id']);

        $curr_obj = $order->info;
        $OrderAmt = number_format($order->info['total'] * $order->info['currency_value'], $currencies->get_decimal_places($order->info['currency']), '.', '');
        $order_query = array('customers_id' => $customer_id,
            'customers_name' => $customer_info->fields['customers_firstname'] . " " . $customer_info->fields['customers_lastname'],
            'customers_company' => $customer_info->fields['entry_company'],
            'customers_street_address' => $customer_info->fields['entry_street_address'],
            'customers_suburb' => $customer_info->fields['entry_suburb'],
            'customers_city' => $customer_info->fields['entry_city'],
            'customers_postcode' => $customer_info->fields['entry_postcode'],
            'customers_state' => $customer_info->fields['entry_state'],
            'customers_country' => $customer_info->fields['country_name'],
            'customers_telephone' => $customer_info->fields['customers_telephone'],
            'customers_email_address' => $customer_info->fields['customers_email_address'],
            'customers_address_format_id' => $customer_info->fields['format_id'],
            'delivery_name' => $customer_info->fields['customers_firstname'] . " " . $customer_info->fields['customers_lastname'],
            'delivery_company' => $customer_info->fields['entry_company'],
            'delivery_street_address' => $customer_info->fields['entry_street_address'],
            'delivery_suburb' => $customer_info->fields['entry_suburb'],
            'delivery_city' => $customer_info->fields['entry_city'],
            'delivery_postcode' => $customer_info->fields['entry_postcode'],
            'delivery_state' => $customer_info->fields['entry_state'],
            'delivery_country' => $customer_info->fields['country_name'],
            'delivery_address_format_id' => $customer_info->fields['format_id'],
            'billing_name' => $customer_info->fields['customers_firstname'] . " " . $customer_info->fields['customers_lastname'],
            'billing_company' => $customer_info->fields['entry_company'],
            'billing_street_address' => $customer_info->fields['entry_street_address'],
            'billing_suburb' => $customer_info->fields['entry_suburb'],
            'billing_city' => $customer_info->fields['entry_city'],
            'billing_postcode' => $customer_info->fields['entry_postcode'],
            'billing_state' => $customer_info->fields['entry_state'],
            'billing_country' => $customer_info->fields['country_name'],
            'billing_address_format_id' => $customer_info->fields['format_id'],
            'shipping_method' => $order->info['shipping_method'],
            'shipping_module_code' => $order->info['shipping_module_code'],
            'payment_method' => $this->description,
            'payment_module_code' => $this->code,
            'coupon_code' => $order->info['coupon_code'],
            'date_purchased' => 'now()',
            'orders_status' => DEFAULT_ORDERS_STATUS_ID,
            'currency' => $order->info['currency'],
            'currency_value' => $order->info['currency_value'],
            'order_total' => $OrderAmt,
            'order_tax' => '0.00',
            'paypal_ipn_id' => '0',
            'ip_address' => $_SERVER['REMOTE_ADDR'] . " - " . $_SERVER['REMOTE_ADDR']
        );
        zen_db_perform(TABLE_ORDERS, $order_query);
        $order_id = $db->insert_ID();

        /*
         * Insert Order status History
         */
        $order_status = array('orders_id' => $order_id,
            'orders_status_id' => DEFAULT_ORDERS_STATUS_ID,
            'date_added' => 'now()'
        );
        zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $order_status);

        /*
         * Insert Order Sub-Total
         */
        $order_total = array('orders_id' => $order_id,
            'title' => 'Sub-Total',
            'text' => $OrderAmt,
            'value' => $OrderAmt,
            'class' => "ot_subtotal",
            'sort_order' => "1");
        zen_db_perform(TABLE_ORDERS_TOTAL, $order_total);

        /*
         * Insert Shipping Total
         */

        $order_total = array('orders_id' => $order_id,
            'title' => '',
            'text' => $order->info['shipping_cost'],
            'value' => $order->info['shipping_cost'],
            'class' => "ot_shipping",
            'sort_order' => "2");
        zen_db_perform(TABLE_ORDERS_TOTAL, $order_total);

        /*
         * Insert Order Total with Currency Symbol
         */
        $order_total = array('orders_id' => $order_id,
            'title' => 'Total',
            'text' => $order->info['currency'] . " " . $OrderAmt,
            'value' => $OrderAmt,
            'class' => "ot_total",
            'sort_order' => "3");
        zen_db_perform(TABLE_ORDERS_TOTAL, $order_total);

        foreach ($order->products as $product) {
            $sql_data_array = array(
                'orders_id' => $order_id,
                'products_id' => $product['id'],
                'products_model' => $product['model'],
                'products_name' => $product['name'],
                'products_price' => $product['price'],
                'final_price' => $product['final_price'],
                'products_tax' => $product['tax'],
                'products_quantity' => $product['qty'],
                'onetime_charges' => $product['onetime_charges'],
                'products_priced_by_attribute' => $product['products_priced_by_attribute'],
                'product_is_free' => $product['product_is_free'],
                'products_discount_type' => $product['products_discount_type'],
                'products_discount_type_from' => $product['products_discount_type_from'],
                'products_prid' => '');
            zen_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);

            $db->Execute("UPDATE " . TABLE_PRODUCTS . " SET products_quantity = products_quantity - '" . $product['qty'] . "' WHERE products_id = '" . $product['id'] . "'");
        }

        $_SESSION['order_id_billplz'] = $order_id;
        return false;
    }
    /*
     * Method yang dipanggil pada Order Confirmation Page
     */

    public function process_button()
    {
        global $order, $currencies, $db;

        $amount = $order->info['total'] = zen_round($order->info['total'], 2);
        $mobile = preg_replace('/\D/', '', $order->customer['telephone']);
        $api_key = MODULE_PAYMENT_BILLPLZ_API_KEY;
        $x_signature = MODULE_PAYMENT_BILLPLZ_X_SIGNATURE;
        $collection_id = MODULE_PAYMENT_BILLPLZ_COLLECTION_ID;
        $deliver = MODULE_PAYMENT_BILLPLZ_NOTIFY;
        $description = '';
        foreach ($order->products as $key => $value) {
            $description .= $value['name'] . " x " . $value['qty'] . ". ";
        }
        $email = $order->customer['email_address'];
        $first_name = replace_accents($order->customer['firstname']);
        $last_name = replace_accents($order->customer['lastname']);
        $name = $first_name . ' ' . $last_name;
        $ipn_url = zen_href_link('billplz_ipn_handler.php', '', 'SSL', false, false, true);

        $order_id = $_SESSION['order_id_billplz'];
        unset($_SESSION['order_id_billplz']);

        /*
         *  Prepare Data for Validation SHA256
         *  Objective: Prevent Data from being altered by the user
         *  Data arranged: amount+mobile+api_key+collection_id+deliver
         *                 +email+name+ipn_url+order_id
         *  Signed with: x_signature
         *  Rules: All Lower-Case + Strip to only 6 characters
         */

        $preparedString = strtolower($amount . $mobile . $api_key . $collection_id . $deliver . $email . $name . $ipn_url . $order_id);
        $hash_verify = substr(hash_hmac('sha256', $preparedString, $x_signature), 0, 5);

        $str_display = zen_draw_hidden_field('description', $description);
        $str_display .= zen_draw_hidden_field('email', $email);
        $str_display .= zen_draw_hidden_field('name', $name);
        $str_display .= zen_draw_hidden_field('ipn_url', $ipn_url);
        $str_display .= zen_draw_hidden_field('order_id', $order_id);
        $str_display .= zen_draw_hidden_field('mobile', $mobile);
        $str_display .= zen_draw_hidden_field('amount', $amount);
        $str_display .= zen_draw_hidden_field('hash_verify', $hash_verify);

        return $str_display;
    }

    public function before_process()
    {
        //Nothing
    }

    public function after_process()
    {
        //Nothing
    }

    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_BILLPLZ_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    private static function getDBPresetFormat()
    {
        $array = [
            'configuration_title' => [
                'Enable Billplz',
                'API Secret Key',
                'X Signature Key',
                'Collection ID',
                'Send Bills to Customer',
                'Sort Order of display',
                'Set Order Status',
            ],
            'configuration_key' => [
                'MODULE_PAYMENT_BILLPLZ_STATUS',
                'MODULE_PAYMENT_BILLPLZ_API_KEY',
                'MODULE_PAYMENT_BILLPLZ_X_SIGNATURE',
                'MODULE_PAYMENT_BILLPLZ_COLLECTION_ID',
                'MODULE_PAYMENT_BILLPLZ_NOTIFY',
                'MODULE_PAYMENT_BILLPLZ_SORT_ORDER',
                'MODULE_PAYMENT_BILLPLZ_ORDER_STATUS_ID',
            ],
            'configuration_value' => [
                'True',
                '',
                '',
                '',
                '',
                '0',
                '0',
            ],
            'configuration_description' => [
                'Do you want to accept payment using Billplz?',
                'Your Billplz API Secret Key',
                'Your Billplz X Signature Key',
                'Your Billplz Collection ID. If unsure, leave blank',
                'We recommend to set to Do Not Send',
                'Sort order of display. Lowest is displayed first.',
                'Set the status of orders made with this payment module to this value',
            ],
            'configuration_group_id' => [
                '6',
                '6',
                '6',
                '6',
                '6',
                '6',
                '6',
            ],
            'sort_order' => [
                '1',
                '2',
                '3',
                '4',
                '5',
                '6',
                '7',
            ],
            'set_function' => [
                'zen_cfg_select_option(array(\'True\', \'False\'),',
                'NULL',
                'NULL',
                'NULL',
                //'zen_cfg_select_option(array(\'True\', \'False\'),',
                'zen_cfg_select_drop_down(array(array(\'id\'=>\'0\', \'text\'=>\'Do Not Send\'),array(\'id\'=>\'1\', \'text\'=>\'Send Email (FREE)\'),array(\'id\'=>\'2\', \'text\'=>\'Send SMS (RM0.15)\'),array(\'id\'=>\'3\', \'text\'=>\'Send Both (RM0.15)\')),',
                'NULL',
                'zen_cfg_pull_down_order_statuses(',
            ],
            'use_function' => [
                'NULL',
                'NULL',
                'NULL',
                'NULL',
                'NULL',
                'NULL',
                'zen_get_order_status_name',
            ],
            'date_added' => [
                'now()',
                'now()',
                'now()',
                'now()',
                'now()',
                'now()',
                'now()',
            ]
        ];
        return $array;
    }

    public function install()
    {
        global $db;
        $dbpreset = self::getDBPresetFormat();
        $this->installDatabase($dbpreset);
        $this->notify('NOTIFY_PAYMENT_BILLPLZ_INSTALLED');
    }

    private function installDatabase($dbpreset)
    {
        global $db;
        for ($i = 0; $i < sizeof($dbpreset['configuration_title']); $i++) {
            $sql = 'INSERT INTO ' . TABLE_CONFIGURATION . ' (';

            $arrayKeys = array_keys($dbpreset);
            $lastArrayKey = end($arrayKeys);

            foreach ($arrayKeys as $key) {
                if ($key != $lastArrayKey) {
                    $sql .= $key . ', ';
                } else {
                    $sql .= $key;
                }
            }

            $sql .= ') values (';

            foreach ($arrayKeys as $key) {
                if ($dbpreset[$key][$i] !== 'NULL' && $dbpreset[$key][$i] !== 'now()') {
                    $sql .= '"' . $dbpreset[$key][$i] . '"';
                } else {
                    $sql .= $dbpreset[$key][$i];
                }
                if ($key != $lastArrayKey) {
                    $sql .= ', ';
                }
            }
            $sql .= ')';

            $db->Execute($sql);
        }
    }

    public function remove()
    {
        global $db;
        $db->Execute('DELETE FROM ' . TABLE_CONFIGURATION . ' WHERE configuration_key LIKE "MODULE_PAYMENT_BILLPLZ_%"');
        $this->notify('NOTIFY_PAYMENT_BILLPLZ_UNINSTALLED');
    }

    public function keys()
    {
        return [
            'MODULE_PAYMENT_BILLPLZ_STATUS',
            'MODULE_PAYMENT_BILLPLZ_SORT_ORDER',
            'MODULE_PAYMENT_BILLPLZ_ORDER_STATUS_ID',
            'MODULE_PAYMENT_BILLPLZ_ZONE',
            'MODULE_PAYMENT_BILLPLZ_API_KEY',
            'MODULE_PAYMENT_BILLPLZ_X_SIGNATURE',
            'MODULE_PAYMENT_BILLPLZ_COLLECTION_ID',
            'MODULE_PAYMENT_BILLPLZ_NOTIFY',
        ];
    }
}
