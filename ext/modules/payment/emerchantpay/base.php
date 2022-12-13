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

/**
 * emerchantpay Base Class
 * Class emerchantpay_base
 */
abstract class emerchantpay_base
{
    const EMERCHANTPAY_CHECKOUT_METHOD_CODE = 'emerchantpay_checkout';

    const EMERCHANTPAY_CHECKOUT_TRANSACTIONS_TABLE_NAME = 'emerchantpay_checkout_transactions';
    const EMERCHANTPAY_CHECKOUT_CONSUMERS_TABLE_NAME    = 'emerchantpay_checkout_consumers';

    const ACTION_CAPTURE = 'doCapture';
    const ACTION_REFUND  = 'doRefund';
    const ACTION_VOID    = 'doVoid';

    /**
     * Payment method code
     *
     * @var string
     */
    public $code = null;

    /**
     * Get Transactions Table name for the Payment Module
     * @return string
     */
    abstract protected function getTableNameTransactions();

    /**
     * emerchantpay_base constructor.
     */
    public function __construct()
    {
    }

    /**
     * Get Admin Settings Key Prefix
     * @return string
     */
    protected function getConfigPrefix()
    {
        return
            sprintf(
                "MODULE_PAYMENT_%s_",
                strtoupper(
                    $this->code
                )
            );
    }
    /**
     * Checks if module is installed
     * @return bool
     * @throws \Exception
     */
    protected function getIsInstalled()
    {
        return defined(
            $this->getConfigPrefix() . "STATUS"
        );
    }

    /**
     * Extract Module Setting by Key
     * @param string $key
     * @return string|null
     */
    protected function getSetting($key)
    {
        $key = $this->getSettingKey($key);
        if (defined($key)) {
            return constant($key);
        }

        return null;
    }

    /**
     * Extract Complete Admin Setting Key
     * @param string $key
     * @return string
     */
    protected function getSettingKey($key)
    {
        return $this->getConfigPrefix() . $key;
    }

    /**
     * Extract Complete
     * @param string $key
     * @return int
     */
    protected function getIntSetting($key)
    {
        return intval(
            $this->getSetting($key)
        );
    }

    /**
     * Get Admin Boolean Setting Value
     * @param string $key
     * @return bool
     */
    protected function getBoolSetting($key)
    {
        return
            filter_var(
                $this->getSetting($key),
                FILTER_VALIDATE_BOOLEAN
            );
    }

    /**
     * Determines if a Payment Method is Enabled
     * @return bool
     */
    protected function getIsEnabled()
    {
        return $this->getBoolSetting('STATUS');
    }

    /**
     * Determines if the Module Admin Settings are properly configured
     * @return bool
     */
    protected function getIsConfigured()
    {
        return
            !empty($this->getSetting('USERNAME')) &&
            !empty($this->getSetting('PASSWORD'));
    }

    /**
     * Get If SSL Enabled for the Front Site
     * @return bool
     */
    protected function getIsSSLEnabled()
    {
        return
            (
            (defined('ENABLE_SSL') && (ENABLE_SSL || strtolower(ENABLE_SSL) == 'true'))
            ) &&
            (substr(HTTP_SERVER, 0, 5) == 'https') ? true : false;
    }

    /**
     * Determines the Environment Mode of a Payment Method
     * @return bool
     */
    protected function getIsLiveMode()
    {
        return $this->getBoolSetting('ENVIRONMENT');
    }

    /**
     * Set the needed Configuration for the Genesisi Gateway Client
     * @return void
     * @throws \Genesis\Exceptions\InvalidArgument
     */
    protected function setCredentials()
    {
        \Genesis\Config::setEndpoint(
            \Genesis\API\Constants\Endpoints::EMERCHANTPAY
        );

        \Genesis\Config::setUsername(
            $this->getSetting('USERNAME')
        );
        \Genesis\Config::setPassword(
            $this->getSetting('PASSWORD')
        );

        \Genesis\Config::setEnvironment(
            $this->getIsLiveMode()
                ? \Genesis\API\Constants\Environments::PRODUCTION
                : \Genesis\API\Constants\Environments::STAGING
        );
    }

    /**
     * Register Genesis Gateway Client
     * @return void
     */
    protected function initLibrary()
    {
        if (!class_exists("\\Genesis\\Genesis")) {
            require_once DIR_FS_CATALOG . '/includes/apps/emerchantpay/libs/genesis/vendor/autoload.php';
        }
    }

    /**
     * Get formatted datetime object
     * @param mixed $timestamp
     * @return string
     */
    protected static function formatTimeStamp($timestamp)
    {
        return ($timestamp instanceof DateTime)
            ? $timestamp->format('Y-m-d H:i:s')
            : $timestamp;
    }

    /**
     * Updates Order Status
     * @param int $orderId
     * @param int $orderStatusId
     */
    protected static function setOrderStatus($orderId, $orderStatusId)
    {
        // Update Order Status
        tep_db_query("UPDATE " . TABLE_ORDERS . "
                          SET `orders_status` = '" . abs(intval($orderStatusId)) . "', `last_modified` = NOW()
                          WHERE `orders_id` = '" . abs(intval($orderId)) . "'");
    }

    /**
     * Add transaction to the database
     *
     * @param array $data
     */
    protected function addTransaction($data)
    {
        try {
            $fields = implode(', ', array_map(
                function ($v, $k) {
                    return sprintf('`%s`', $k);
                },
                $data,
                array_keys(
                    $data
                )
            ));

            $values = implode(', ', array_map(
                function ($v) {
                    return "'" . filter_var($v, FILTER_SANITIZE_MAGIC_QUOTES) . "'";
                },
                $data,
                array_keys(
                    $data
                )
            ));

            tep_db_query("
				INSERT INTO
					`" . $this->getTableNameTransactions() . "` (" . $fields . ")
				VALUES
					(" . $values . ")
			");
        } catch (Exception $exception) {
        }
    }

    /**
     * Update existing transaction in the database
     *
     * @param array $data
     */
    protected function updateTransaction($data)
    {
        try {
            $fields = implode(', ', array_map(
                function ($v, $k) {
                    return sprintf("`%s` = '%s'", $k, $v);
                },
                $data,
                array_keys(
                    $data
                )
            ));

            tep_db_query("
				UPDATE
					`" . $this->getTableNameTransactions() . "`
				SET
					" . $fields . "
				WHERE
				    `unique_id` = '" . filter_var($data['unique_id'], FILTER_SANITIZE_MAGIC_QUOTES) . "'
			");
        } catch (Exception $exception) {

        }
    }

    /**
     * Store data to an existing / a new Transaction
     * @array $data
     * @return mixed
     */
    protected function doPopulateTransaction($data)
    {
        try {
            // Check if transaction exists
            $insertQuery = tep_db_query("
                SELECT
                    *
                FROM
                    `" . $this->getTableNameTransactions() . "`
                WHERE
                    `unique_id` = '" . filter_var($data['unique_id'], FILTER_SANITIZE_MAGIC_QUOTES) . "'
            ");

            if (tep_db_num_rows($insertQuery) > 0) {
                $this->updateTransaction($data);
            } else {
                $this->addTransaction($data);
            }
        } catch (Exception $exception) {
        }
    }
}
