<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
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
 * @author      eMerchantPay
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

chdir("../../../../");

require "includes/application_top.php";

if (!class_exists("emerchantpay_notification_base")) {
    require_once DIR_FS_CATALOG . 'ext/modules/payment/emerchantpay/notification_base.php';
}

/**
 * Direct Payment Method Notification Handler Class
 * Class emerchantpay_direct_notification
 */
class emerchantpay_direct_notification extends emerchantpay_notification_base
{
    protected function getTableNameTransactions()
    {
        return static::EMERCHANTPAY_DIRECT_TRANSACTIONS_TABLE_NAME;
    }

    public function __construct()
    {
        $this->code = static::EMERCHANTPAY_DIRECT_METHOD_CODE;
        parent::__construct();
    }

    protected function isValidNotification($requestData)
    {
        return
            parent::isValidNotification($requestData) &&
            isset($requestData['unique_id']);
    }

    function setCredentials()
    {
        parent::setCredentials();

        \Genesis\Config::setToken(
            $this->getSetting('TOKEN')
        );
    }

    protected function processNotificationObject($reconcile)
    {
        $timestamp = static::formatTimeStamp($reconcile->timestamp);

        $order_id = $this->getOrderByTransaction($reconcile->unique_id);

        if (!$this->getOrderExists($order_id)) {
            return false;
        }

        switch ($reconcile->status) {
            case \Genesis\API\Constants\Transaction\States::APPROVED:
                $order_status_id = $this->getIntSetting('PROCESSED_ORDER_STATUS_ID');
                break;
            case \Genesis\API\Constants\Transaction\States::ERROR:
            case \Genesis\API\Constants\Transaction\States::DECLINED:
                $order_status_id = $this->getIntSetting('FAILED_ORDER_STATUS_ID');
                break;
            default:
                $order_status_id = $this->getIntSetting('ORDER_STATUS_ID');
        }

        static::setOrderStatus(
            $order_id,
            $order_status_id
        );

        $this->addOrderNotificationHistory(
            $order_id,
            $order_status_id,
            $reconcile
        );

        $data = array(
            'order_id'          => $order_id,
            'unique_id'         => $reconcile->unique_id,
            'type'              => $reconcile->transaction_type,
            'mode'              => $reconcile->mode,
            'status'            => $reconcile->status,
            'currency'          => $reconcile->currency,
            'amount'            => $reconcile->amount,
            'timestamp'         => $timestamp,
            'terminal_token'    => isset($reconcile->terminal_token) ? $reconcile->terminal_token : '',
            'message'           => isset($reconcile->message) ? $reconcile->message : '',
            'technical_message' => isset($reconcile->technical_message) ? $reconcile->technical_message : '',
        );

        $this->doPopulateTransaction($data);

        return true;
    }
}

$notification = new emerchantpay_direct_notification();

$notification->handleNotification($_POST);

exit(0);