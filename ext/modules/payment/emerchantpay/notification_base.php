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

if (!class_exists('emerchantpay_base')) {
    require_once DIR_FS_CATALOG . "ext/modules/payment/emerchantpay/base.php";
}

/**
 * Base Abstract emerchantpay Notification Class
 * Class emerchantpay_notification_base
 */
abstract class emerchantpay_notification_base extends emerchantpay_base
{
    /**
     * Process Genesis Notification Object - Order Status & Transaction
     * @param stdClass $reconcile
     * @return mixed
     */
    abstract protected function processNotificationObject($reconcile);

    /**
     * emerchantpay_notification_base constructor.
     */
    public function __construct()
    {
        ini_set('display_errors', 'Off');
        error_reporting(0);

        $this->initLibrary();
    }

    /**
     * Determines if a Notification from Genesis is Valid
     * @param array $requestData
     * @return bool
     */
    protected function isValidNotification($requestData)
    {
        return
            isset($requestData['signature']) &&
            (
                isset($requestData['unique_id']) ||
                isset($requestData['wpf_unique_id'])
            );
    }

    /**
     * Get Order Id By Genesis Unique Id
     * @param string $unique_id Genesis Unique Id
     * @return mixed bool on failed, int on success
     */
    protected function getOrderByTransaction($unique_id)
    {
        $query = tep_db_query('select `order_id` from `' . $this->getTableNameTransactions() . '`
                                where `unique_id` = "' . filter_var($unique_id, FILTER_SANITIZE_MAGIC_QUOTES) . '"');

        if (tep_db_num_rows($query) < 1) {
            return null;
        }

        $fields = tep_db_fetch_array($query);
        return $fields['order_id'];
    }

    /**
     * Check if Order Exists
     * @param int $order_id
     * @return bool
     */
    protected function getOrderExists($order_id)
    {
        $orderQuery = tep_db_query("SELECT `orders_id`, `orders_status`, `currency`, `currency_value` FROM " . TABLE_ORDERS . " WHERE `orders_id` = '" . abs(intval($order_id)) . "'");

        return
            tep_db_num_rows($orderQuery) > 0;
    }

    /**
     * Handles Genesis Notification & Renders Response
     * @param array $requestData
     * @return bool
     * @throws \Genesis\Exceptions\InvalidArgument
     */
    public function handleNotification($requestData)
    {
        if (!$this->isValidNotification($requestData)) {
            return false;
        }

        if (!$this->getIsInstalled()) {
            return false;
        }

        $this->setCredentials();

        $notification = new \Genesis\Api\Notification($requestData);

        if (!$notification->isAuthentic()) {
            return false;
        }

        $notification->initReconciliation();

        $reconcile = $notification->getReconciliationObject();

        if ($this->processNotificationObject($reconcile)) {
            $notification->renderResponse();
            return true;
        }

        return false;
    }

    /**
     * Create osCommerce Order Core History
     * @param int $order_id
     * @param int $order_status_id
     * @param stdClass $payment
     */
    protected function addOrderNotificationHistory($order_id, $order_status_id, $payment)
    {
        // Add Order Status History Entry
        $sql_data_array = array(
            'orders_id'         => $order_id,
            'orders_status_id'  => $order_status_id,
            'date_added'        => 'now()',
            'customer_notified' => '1',
            'comments'          =>
                sprintf(
                    "[Notification]" .  PHP_EOL .
                    "- Unique ID: %s" . PHP_EOL .
                    "- Status: %s".     PHP_EOL .
                    "- Message: %s",
                    $payment->unique_id,
                    $payment->status,
                    $payment->message
                ),
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }
}