<?php
/*
 * Copyright (C) 2018 emerchantpay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      emerchantpay
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

if (!class_exists("emerchantpay_base")) {
    require_once DIR_FS_CATALOG . 'ext/modules/payment/emerchantpay/base.php';
}

/**
 * emerchantpay Class for Managing Order Transactions
 * Class emerchantpay_order_transactions_panel
 */
class emerchantpay_order_transactions_panel
{
    /**
     * emerchantpay_order_transactions_panel constructor.
     */
    public function __construct()
    {
    }

    /**
     * Check Database Table exists
     * @param string $table_name
     * @return bool
     */
    protected static function getIsTableExists($table_name)
    {
        $query = tep_db_query(
            sprintf(
                "show tables like '%s'",
                $table_name
            )
        );

        return tep_db_num_rows($query) > 0;
    }

    /**
     * Determines if there are Transactions attached to Order
     * @param int $order_id
     * @param string $table_name
     * @return bool
     */
    protected static function getOrderTransactionExists($order_id, $table_name)
    {
        if (!static::getIsTableExists($table_name)) {
            return false;
        }

        $query = tep_db_query('select count(`unique_id`) as `transactions_count` from `' . $table_name . '`
                                where `order_id` = "' . intval($order_id) . '"');

        $fields = tep_db_fetch_array($query);
        return $fields['transactions_count'] > 0;
    }

    /**
     * Determines the Order Payment Method Code from Order
     * @param int $order_id
     * @return null
     */
    public static function getOrderMethodCode($order_id)
    {
        $paymentMethods = array(
            array(
                'method_code' => emerchantpay_base::EMERCHANTPAY_CHECKOUT_METHOD_CODE,
                'table_name'  => emerchantpay_base::EMERCHANTPAY_CHECKOUT_TRANSACTIONS_TABLE_NAME
            ),
            array(
                'method_code' => emerchantpay_base::EMERCHANTPAY_DIRECT_METHOD_CODE,
                'table_name'  => emerchantpay_base::EMERCHANTPAY_DIRECT_TRANSACTIONS_TABLE_NAME
            ),
        );

        foreach ($paymentMethods as $paymentMethod) {
            if (static::getOrderTransactionExists($order_id, $paymentMethod['table_name'])) {
                return $paymentMethod['method_code'];
            }
        }

        return null;
    }

    /**
     * Print Order Transactions Panel Content
     * @param int $order_id
     */
    public function display($order_id)
    {
        $methodCode = $this->getOrderMethodCode($order_id);

        if (!isset($methodCode)) {
            return;
        }

        if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $methodCode . '.php')) {
            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $methodCode . '.php');
            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $methodCode . '.php');

            $module = new $methodCode;
            $methodName = "displayTransactionsPanel";

            if (method_exists($module, $methodName)) {
                $content = call_user_func_array(
                    array($module, $methodName),
                    array($order_id)
                );

                if ($content) {
                    echo $content;
                }
            }
        }
    }

    /**
     * Handles Post Request of (Capture, Refund, Void) Transactions
     * @param int $order_id
     * @param array $requestData
     */
    public function handleReferenceTransactionPostRequest($order_id, &$requestData)
    {
        $action = $requestData['action'] ?: null;

        if (tep_not_null($order_id) && tep_not_null($action)) {
            switch ($action) {
                case emerchantpay_base::ACTION_CAPTURE:
                case emerchantpay_base::ACTION_REFUND:
                case emerchantpay_base::ACTION_VOID:
                    $methodCode = emerchantpay_order_transactions_panel::getOrderMethodCode($order_id);

                    if (tep_not_null($methodCode)) {
                        if (file_exists(DIR_FS_CATALOG_MODULES . 'payment/' . $methodCode . '.php')) {
                            require_once(DIR_FS_CATALOG_MODULES . 'payment/' . $methodCode . '.php');
                            require_once(DIR_FS_CATALOG_LANGUAGES . $_SESSION['language'] . '/modules/payment/' . $methodCode . '.php');
                            $module = new $methodCode;
                            if (method_exists($module, $action)) {
                                $data = array(
                                    'reference_id' => $requestData['reference_id'],
                                    'usage'        => $requestData['message'],
                                );

                                if ($action != emerchantpay_base::ACTION_VOID) {
                                    $data['amount']   = $requestData['amount'];
                                    $data['currency'] = null; //will be set later
                                }

                                $result = call_user_func_array(
                                    array($module, $action),
                                    array($data)
                                );
                            }
                        }
                    }
            }
        }
    }
}

$isRequestMethodPost =
    isset($_POST['reference_id']) &&
    isset($_POST['action']);

if (isset($_GET['oID'])) {
    $order_id = tep_db_prepare_input(
        trim($_GET['oID'])
    );
}

if (isset($order_id) && tep_not_null($order_id)) {
    $transactionsPanel = new emerchantpay_order_transactions_panel();

    if ($isRequestMethodPost) {
        $transactionsPanel->handleReferenceTransactionPostRequest($order_id, $_POST);
    }
    $transactionsPanel->display($order_id);
}