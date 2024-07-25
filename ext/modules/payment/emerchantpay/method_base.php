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

use Genesis\Api\Constants\Transaction\Types;

if (!class_exists('emerchantpay_base')) {
    require_once DIR_FS_CATALOG . "ext/modules/payment/emerchantpay/base.php";
}

/**
 * Base Abstract Payment Payment Method
 * Class emerchantpay_method_base
 */
abstract class emerchantpay_method_base extends emerchantpay_base
{
    /**
     * Return common usage
     * @const string
     */
    const TRANSACTION_USAGE = 'Payment via';

    /**
     * Return platform prefix
     * @const string
     */
    const PLATFORM_TRANSACTION_PREFIX = 'osc-';

    /**
     * Return Success Action
     * @const string
     */
    const ACTION_SUCCESS    = 'success';
    /**
     * Return Failure Action
     * @const string
     */
    const ACTION_FAILURE    = 'failure';
    /**
     * Return Cancel Action
     * @const string
     */
    const ACTION_CANCEL     = 'cancel';

    /**
     * Google Pay Transaction Prefix
     */
    const GOOGLE_PAY_TRANSACTION_PREFIX = 'google_pay_';

    /**
     * Google Pay Payment Method Authorize
     */
    const GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE = 'authorize';

    /**
     * Google Pay Payment Method Sale
     */
    const GOOGLE_PAY_PAYMENT_TYPE_SALE = 'sale';

    /**
     * PayPal Transaction Prefix
     */
    const PAYPAL_TRANSACTION_PREFIX = 'pay_pal_';

    /**
     * PayPal Payment Method Authorize
     */
    const PAYPAL_PAYMENT_TYPE_AUTHORIZE = 'authorize';

    /**
     * PayPal Payment Method Sale
     */
    const PAYPAL_PAYMENT_TYPE_SALE = 'sale';

    /**
     * PayPal Payment Method Express
     */
    const PAYPAL_PAYMENT_TYPE_EXPRESS = 'express';

    /**
     * Apple Pay Transaction Prefix
     */
    const APPLE_PAY_TRANSACTION_PREFIX = 'apple_pay_';

    /**
     * Apple Pay Payment Method Authorize
     */
    const APPLE_PAY_PAYMENT_TYPE_AUTHORIZE = 'authorize';

    /**
     * Apple Pay Payment Method Sale
     */
    const APPLE_PAY_PAYMENT_TYPE_SALE = 'sale';

    /**
     * Return Module Version
     * @var string
     */
    public $version         = '1.6.7';
    /**
     * Return Module Version
     * @var string
     */
    public $signature       = null;
    /**
     * Return Genesis Client Version
     * @var string
     */
    public $api_version     = null;
    /**
     * Is this module Enabled?
     *
     * @var bool|mixed
     */
    public $enabled         = false;
    /**
     * Payment method title
     *
     * @var string
     */
    public $title           = null;
    /**
     * Payment method (Front Site) title
     *
     * @var string
     */
    public $public_title    = null;
    /**
     * Payment method description
     *
     * @var string
     */
    public $description     = null;
    /**
     * Payment method sort (display) order
     *
     * @var string
     */
    public $sort_order      = null;
    /**
     * Payment method's order status
     *
     * @var string
     */
    public $order_status    = null;
    /**
     * Store the order GLOBAL variable as private
     *
     * @var array|null|order
     */
    protected $order;
    /**
     * Used to store the Response Object, after payment is executed.
     * @var stdClass
     */
    protected $responseObject;
    /**
     * Admin Setting Sort Order Attribute
     * @var string
     */
    protected $sortOrderAttributes = "array(''maxlength'' => ''3'')";
    /**
     * Admin Setting Attribute for Required Fields
     * @var string
     */
    protected $requiredOptionsAttributes = "array(''required'' => ''required'')";

    /**
     * Get If Module Requires SSL or not
     * @return bool
     */
    abstract protected function getModuleRequiresSSL();

    /**
     * Process Request to the Gateway
     * @return bool
     */
    abstract protected function doBeforeProcessPayment();

    /**
     * Send transaction to Genesis
     *
     * @param stdClass $data
     * @return stdClass
     * @throws Exception
     */
    abstract protected function pay($data);

    /**
     * emerchantpay_method_base constructor.
     */
    public function __construct()
    {
        $this->initLibrary();
        $this->registerAdminScriptsAndFunctions();
        $this->init();
    }

    /**
     * Do Init Module (Status, Description, Messages)
     * @return void
     */
    protected function init() {
        global $order;

        $this->api_version  = \Genesis\Config::getVersion();

        $this->signature 	= sprintf(
            "emerchantpay|%s|%s",
            $this->code,
            $this->version
        );

        $this->title 		= $this->getSetting('TEXT_TITLE');
        $this->public_title = $this->getSetting('TEXT_PUBLIC_TITLE');
        $this->description 	= $this->getSetting('TEXT_DESCRIPTION');
        $this->sort_order 	= $this->getSetting('SORT_ORDER');

        $this->enabled 		= $this->getIsEnabled();

        $this->order        = $order;

        if ( isset($this->order) && is_object($this->order) ) {
            $this->update_status();
        }

        if ($this->getIsInstalled()) {
            if (!$this->getIsConfigured()) {
                $this->title .= '<span class="error-emerchantpay"> (Not Configured)</span>';
            } elseif (!$this->getIsEnabled()) {
                $this->title .= '<span class="error-emerchantpay"> (Disabled)</span>';
            } elseif ($this->getModuleRequiresSSL() && !$this->getIsSSLEnabled()) {
                $this->title .= '<span class="error-emerchantpay"> (SSL NOT Enabled)</span>';
                $this->description =
                    '<div class="secError">' .
                    $this->getSetting('ERROR_SSL') .
                    '</div>' .
                    $this->description;
            } elseif (!$this->getIsLiveMode()) {
                $this->title .= '<span class="warning-emerchantpay"> (Staging Mode)</span>';
            } else {
                $this->title .= '<span class="success-emerchantpay"> (Live Mode)</span>';
            }
        }

        if ($this->getIsInstalled() && !$this->doCheckAndPatchOrdersCoreTemplateFile(true)) {
            $ordersPHPFile = DIR_FS_ADMIN . "orders.php";
            $this->displayAdminErrorAndDisableModule(
                sprintf("Orders Template file was not modified! " .
                    "Please, give write permission to file \"%s\" and reinstall plugin or contact support for more info!",
                    $ordersPHPFile
                )
            );
        } else {
            try {
                \Genesis\Utils\Requirements::verify();
            } catch (Exception $e) {
                $this->displayAdminErrorAndDisableModule($e->getMessage());
            }
        }

        if ($this->getIntSetting('ORDER_STATUS_ID') > 0) {
            $this->order_status = $this->getIntSetting('ORDER_STATUS_ID');
        }

        // Set the Gateway Credentials
        $this->setCredentials();
    }

    /**
     * Handle Response from Genesis Gateway (Update Order Status & Create Transaction)
     * @return bool
     */
    protected function doAfterProcessPayment()
    {
        global $insert_id;

        if (isset($this->responseObject) && isset($this->responseObject->unique_id)) {
            $timestamp = static::formatTimeStamp($this->responseObject->timestamp);

            $data = array(
                'type' => ($this->responseObject->transaction_type ?: 'checkout'),
                'reference_id' => '0',
                'order_id' => $insert_id,
                'unique_id' => $this->responseObject->unique_id,
                'mode' => $this->responseObject->mode,
                'status' => $this->responseObject->status,
                'amount' => $this->responseObject->amount,
                'currency' => $this->responseObject->currency,
                'message' =>
                    isset($this->responseObject->message)
                        ? $this->responseObject->message
                        : '',
                'technical_message' =>
                    isset($this->responseObject->technical_message)
                        ? $this->responseObject->technical_message
                        : '',
                'timestamp' => $timestamp,
            );

            $this->doPopulateTransaction($data);

            if (isset($this->responseObject->redirect_url)) {
                tep_redirect($this->responseObject->redirect_url);
            } else {
                $this->processUpdateOrder($insert_id);
            }
        }
        return true;
    }

    /**
     * Extends the parameters needed for displaying the admin-page components
     * @param array $data
     * @return void
     */
    protected function extendOrderTransPanelData(&$data)
    {
        $data->params['modal'] = array(
            'capture' => array(
                'allowed' => $this->getBoolSetting("ALLOW_PARTIAL_CAPTURE"),
                'form' => array(
                    'action' => static::ACTION_CAPTURE,
                ),
                'input' => array(
                    'visible' => true,
                )
            ),
            'refund' => array(
                'allowed' => $this->getBoolSetting("ALLOW_PARTIAL_REFUND"),
                'form' => array(
                    'action' => static::ACTION_REFUND,
                ),
                'input' => array(
                    'visible' => true,
                )
            ),
            'void' => array(
                'allowed' => $this->getBoolSetting("ALLOW_VOID_TRANSACTIONS"),
                'form' => array(
                    'action' => static::ACTION_VOID,
                ),
                'input' => array(
                    'visible' => false,
                )
            )
        );

        $data->translations = array(
            'panel' => array(
                'title' => $this->getSetting("LABEL_ORDER_TRANS_TITLE"),
                'transactions' => array(
                    'header' => array(
                        'id' 			 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_ID"),
                        'type' 			 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_TYPE"),
                        'timestamp'	 	 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_TIMESTAMP"),
                        'amount' 		 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_AMOUNT"),
                        'status' 		 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_STATUS"),
                        'message' 		 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_MESSAGE"),
                        'mode' 			 => $this->getSetting("LABEL_ORDER_TRANS_HEADER_MODE"),
                        'action_capture' => $this->getSetting("LABEL_ORDER_TRANS_HEADER_ACTION_CAPTURE"),
                        'action_refund'  => $this->getSetting("LABEL_ORDER_TRANS_HEADER_ACTION_REFUND"),
                        'action_void'    => $this->getSetting("LABEL_ORDER_TRANS_HEADER_ACTION_VOID")
                    )
                )
            ),
            'modal' => array(
                'capture' => array(
                    'title' => $this->getSetting("LABEL_CAPTURE_TRAN_TITLE"),
                    'input' => array(
                        'label' 		  => $this->getSetting("LABEL_ORDER_TRANS_MODAL_AMOUNT_LABEL_CAPTURE"),
                        'warning_tooltip' => $this->getSetting("MESSAGE_CAPTURE_PARTIAL_DENIED")
                    ),
                    'buttons' => array(
                        'submit' => array(
                            'title' => $this->getSetting("LABEL_BUTTON_CAPTURE")
                        ),
                        'cancel' => array(
                            'title' => $this->getSetting("LABEL_BUTTON_CANCEL")
                        )
                    )
                ),
                'refund' => array(
                    'title' => $this->getSetting("LABEL_REFUND_TRAN_TITLE"),
                    'input' => array(
                        'label'           => $this->getSetting("LABEL_ORDER_TRANS_MODAL_AMOUNT_LABEL_REFUND"),
                        'warning_tooltip' => $this->getSetting("MESSAGE_REFUND_PARTIAL_DENIED")
                    ),
                    'buttons' => array(
                        'submit' => array(
                            'title' => $this->getSetting("LABEL_BUTTON_REFUND")
                        ),
                        'cancel' => array(
                            'title' => $this->getSetting("LABEL_BUTTON_CANCEL")
                        )
                    )
                ),
                'void' => array(
                    'title' => $this->getSetting("LABEL_VOID_TRAN_TITLE"),
                    'input' => array(
                        'label' => null,
                        'warning_tooltip' => $this->getSetting("MESSAGE_VOID_DENIED")
                    ),
                    'buttons' => array(
                        'submit' => array(
                            'title' => $this->getSetting("LABEL_BUTTON_VOID")
                        ),
                        'cancel' => array(
                            'title' => $this->getSetting("LABEL_BUTTON_CANCEL")
                        )
                    )
                )
            )
        );
    }

    /**
     * Get saved transaction by id
     *
     * @param string $unique_id
     *
     * @return mixed null on fail, row on success
     */
    protected function getTransactionById($unique_id)
    {
        $query = tep_db_query("
            SELECT * FROM
              `" . $this->getTableNameTransactions() . "`
            WHERE
              `unique_id` = '" . filter_var($unique_id, FILTER_SANITIZE_MAGIC_QUOTES) . "'
            LIMIT 1
        ");

        if (tep_db_num_rows($query) > 0) {
            return tep_db_fetch_array($query);
        }

        return null;
    }

    /**
     * Process Reference (Capture, Refund, Void) Transaction to the Gateway
     * @param string $transactionType
     * @param array $data
     * @param array $initialTransaction
     * @return bool
     */
    protected function doExecuteReferenceTransaction($transactionType, $data, $initialTransaction)
    {
        global $messageStack;

        try {
            if (!isset($data['remote_ip'])) {
                $data['remote_ip'] = static::getServerRemoteAddress();
            }

            if (array_key_exists('currency', $data) && is_null($data['currency'])) {
                $data['currency'] = $initialTransaction['currency'];
            }

            if (!isset($data['transaction_id'])) {
                $data['transaction_id'] = $this->getGeneratedTransactionId(self::PLATFORM_TRANSACTION_PREFIX);
            }

            if (empty(\Genesis\Config::getToken())) {
                \Genesis\Config::setToken($initialTransaction['terminal_token']);
            }

            $genesis = $this->executeReferenceTransaction($transactionType, $data, $initialTransaction);

            $responseObject = $genesis->response()->getResponseObject();

            if (isset($responseObject->unique_id)) {
                $data = $this->handleReferenceTransactionResponse($responseObject, $initialTransaction);
                unset($data['order_status_id']);

                $this->doPopulateTransaction($data);

                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $messageStack->add_session($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * @param $transactionType
     * @param $data
     * @param $initialTransaction
     * @return \Genesis\Genesis
     * @throws \Genesis\Exceptions\DeprecatedMethod
     * @throws \Genesis\Exceptions\InvalidArgument
     * @throws \Genesis\Exceptions\InvalidMethod
     * @throws \Genesis\Exceptions\InvalidResponse
     */
    protected function executeReferenceTransaction($transactionType, $data, $initialTransaction)
    {
        $genesis = new \Genesis\Genesis($transactionType);

        $request = $genesis->request();

        foreach ($data as $key => $value) {
            $methodName = sprintf(
                "set%s",
                \Genesis\Utils\Common::snakeCaseToCamelCase($key)
            );
            call_user_func_array(
                array($request, $methodName),
                array($value)
            );
        }

        $captureType = Types::getCaptureTransactionClass(Types::KLARNA_AUTHORIZE);
        $refundType  = Types::getRefundTransactionClass(Types::KLARNA_CAPTURE);

        if ($transactionType == $captureType || $transactionType == $refundType) {
            $klarnaData  = emp_get_klarna_data($initialTransaction['order_id']);
            $klarnaItems = emp_get_klarna_custom_param_items($klarnaData, true);

            $request->setItems($klarnaItems);
        }

        $genesis->execute();

        return $genesis;
    }

    /**
     * @param \stdClass $responseObject
     * @param array $initialTransaction
     * @return array
     */
    protected function handleReferenceTransactionResponse($responseObject, $initialTransaction)
    {
        global $messageStack;

        $timestamp = $this->formatTimeStamp($responseObject->timestamp);

        if ($responseObject->status == \Genesis\Api\Constants\Transaction\States::APPROVED) {
            $messageStack->add_session($responseObject->message, 'success');
        } else {
            $messageStack->add_session($responseObject->message, 'error');
        }

        $data = array(
            'order_id' => $initialTransaction['order_id'],
            'reference_id' => $initialTransaction['unique_id'],
            'unique_id' => $responseObject->unique_id,
            'type' => $responseObject->transaction_type,
            'mode' => $responseObject->mode,
            'status' => $responseObject->status,
            'amount' => (isset($responseObject->amount) ? $responseObject->amount : "0"),
            'currency' => $responseObject->currency,
            'timestamp' => $timestamp,
            'terminal_token' =>
                isset($responseObject->terminal_token)
                    ? $responseObject->terminal_token
                    : $initialTransaction['terminal_token'],
            'message' =>
                isset($responseObject->message)
                    ? $responseObject->message
                    : '',
            'technical_message' =>
                isset($responseObject->technical_message)
                    ? $responseObject->technical_message
                    : '',
        );

        $data = array_merge(
            $data,
            $this->getReferenceLabels($responseObject->transaction_type)
        );

        if (isset($data['type']) && isset($data['order_status_id'])) {
            $this->recordReferenceHistory($responseObject, $data, $initialTransaction);
        }

        $data['type'] = $responseObject->transaction_type;

        return $data;
    }

    /**
     * @param \stdClass $responseObject
     * @param array $data
     * @param array $initialTransaction
     */
    protected function recordReferenceHistory($responseObject, $data, $initialTransaction)
    {
        static::setOrderStatus(
            $initialTransaction['order_id'],
            $data['order_status_id']
        );

        static::performOrderStatusHistory(
            array(
                'type'              => $data['type'],
                'orders_id'         => $initialTransaction['order_id'],
                'order_status_id'   => $data['order_status_id'],
                'transaction_type'  => $responseObject->transaction_type,
                'payment'           => array(
                    'unique_id'       => $responseObject->unique_id,
                    'status'          => $responseObject->status,
                    'message'         => $responseObject->message
                )
            )
        );
    }

    /**
     * @param string $transactionType
     * @return array
     */
    protected function getReferenceLabels($transactionType)
    {
        $data = array();

        switch ($transactionType) {
            case Types::CAPTURE:
                $data['type'] = $this->getSetting('LABEL_ORDER_TRANS_HEADER_ACTION_CAPTURE');
                $data['order_status_id'] = $this->getSetting('PROCESSED_ORDER_STATUS_ID');
                break;

            case Types::REFUND:
                $data['type'] = $this->getSetting('LABEL_ORDER_TRANS_HEADER_ACTION_REFUND');
                $data['order_status_id'] = $this->getSetting('REFUNDED_ORDER_STATUS_ID');
                break;

            case Types::VOID:
                $data['type'] = $this->getSetting('LABEL_ORDER_TRANS_HEADER_ACTION_VOID');
                $data['order_status_id'] = $this->getSetting('CANCELED_ORDER_STATUS_ID');
                break;
        }

        return $data;
    }

    /**
     * Process Capture Transaction to the Genesis Gatewayu
     * @param array $data
     * @return bool
     */
    public function doCapture($data)
    {
        $initialTransaction = $this->getTransactionById($data['reference_id']);

        return
            $this->doExecuteReferenceTransaction(
                Types::getCaptureTransactionClass($initialTransaction['type']),
                $data,
                $initialTransaction
            );
    }

    /**
     * Process Refund Transaction to the Genesis Gatewayu
     * @param array $data
     * @return bool
     */
    public function doRefund($data)
    {
        $initialTransaction = $this->getTransactionById($data['reference_id']);

        return
            $this->doExecuteReferenceTransaction(
                Types::getRefundTransactionClass($initialTransaction['type']),
                $data,
                $initialTransaction
            );
    }

    /**
     * Process Void Transaction to the Genesis Gatewayu
     * @param array $data
     * @return bool
     */
    public function doVoid($data)
    {
        $initialTransaction = $this->getTransactionById($data['reference_id']);

        return
            $this->doExecuteReferenceTransaction(
                'Financial\\' . ucfirst(Types::VOID),
                $data,
                $initialTransaction
            );
    }

    /**
     * Build Currency array from the order currency code
     * @param string $currencyCode
     * @return array|bool
     */
    protected static function getCurrencyData($currencyCode)
    {
        $sql = "select * from `" . TABLE_CURRENCIES . "`
                WHERE `code` = '" . filter_var($currencyCode, FILTER_SANITIZE_MAGIC_QUOTES) . "'";

        $query = tep_db_query($sql);

        if (tep_db_num_rows($query) == 1) {
            $fields = tep_db_fetch_array($query);

            $currencySymbol = ($fields['symbol_left'] ?: $fields['symbol_right']);
            return array(
                'sign' => $currencySymbol,
                'iso_code' => $currencyCode,
                'decimalPlaces' => $fields['decimal_places'],
                'decimalSeparator' => $fields['decimal_point'],
                'thousandSeparator' => "" /* Genesis does not allow thousand separator */
            );
        }

        return false;
    }

    /**
     * Determine if transaction can be captured
     * @param array $transaction
     * @param array $configuredTransactions
     * @return bool
     */
    protected static function getCanCaptureTransaction($transaction, $configuredTransactions)
    {
        if (!self::hasApprovedState($transaction['status'])) {
            return false;
        }

        if (self::isTransactionWithCustomAttribute($transaction['type'])) {
            return self::checkReferenceActionByCustomAttr(
                self::ACTION_CAPTURE,
                $transaction['type'],
                $configuredTransactions
            );
        }

        return Types::isAuthorize($transaction['type']);
    }

    /**
     * Determine if transaction can be refunded
     *
     * @param array $transaction
     * @param array $configuredTransactions
     * @return bool
     */
    protected static function getCanRefundTransaction($transaction, $configuredTransactions)
    {
        if (!self::hasApprovedState($transaction['status'])) {
            return false;
        }

        if (self::isTransactionWithCustomAttribute($transaction['type'])) {
            return self::checkReferenceActionByCustomAttr(
                self::ACTION_REFUND,
                $transaction['type'],
                $configuredTransactions
            );
        }

        return Types::canRefund($transaction['type']);
    }

    /**
     * Determine if transaction can be voided
     * @param array $transaction
     * @return bool
     */
    protected static function getCanVoidTransaction($transaction)
    {
        return Types::canVoid($transaction['type']) && self::hasApprovedState($transaction['status']);
    }

    /**
     * Get the Selected Checkout method transaction types
     *
     * @return array
     */
    protected function getCheckoutSelectedTypes()
    {
        return array_map(
            'trim',
            explode(',', $this->getSetting('TRANSACTION_TYPES'))
        );
    }

    /**
     * Get the sum of the ammount for a list of transaction types and status
     * @param int $order_id
     * @param string $reference_id
     * @param array $types
     * @param string $status
     * @return float
     */
    protected function getTransactionsSumAmount($order_id, $reference_id, $types, $status)
    {
        $transactions = $this->getTransactionsByTypeAndStatus($order_id, $reference_id, $types, $status);
        $totalAmount = 0;

        /** @var $transaction */
        if ($transactions && is_array($transactions)) {
            foreach ($transactions as $transaction) {
                $totalAmount +=  $transaction['amount'];
            }
        }

        return $totalAmount;
    }

    /**
     * Get the detailed transactions list of an order for transaction types and status
     * @param int $order_id
     * @param string $reference_id
     * @param array $transaction_types
     * @param string $status
     * @return array
     */
    protected function getTransactionsByTypeAndStatus($order_id, $reference_id, $transaction_types, $status)
    {
        $query = tep_db_query("SELECT
                                  *
                                FROM `" . $this->getTableNameTransactions() . "` as t
                                WHERE (t.`order_id` = '" . abs(intval($order_id)) . "') and " .
            (!empty($reference_id)
                ? " (t.`reference_id` = '" . $reference_id . "') and "
                : "") . "
                (t.`type` in ('" .
            (is_array($transaction_types)
                ? implode("','", $transaction_types)
                : $transaction_types) . "')) and
                (t.`status` = '" . $status . "')
            ");

        if (tep_db_num_rows($query) > 0) {
            $transactions = array();

            while ($transactionFields = tep_db_fetch_array($query)) {
                $transactions[] = $transactionFields;
            }
            return $transactions;
        }

        return false;
    }

    /**
     * Get a formatted transaction value for the Admin Transactions Panel
     * @param float $amount
     * @param array $currency
     * @return string
     */
    protected static function formatTransactionValue($amount, $currency)
    {
        return number_format(
            $amount,
            $currency['decimalPlaces'],
            $currency['decimalSeparator'],
            $currency["thousandSeparator"]
        );
    }

    /**
     * Recursive function used in the process of sorting
     * the Transactions list
     *
     * @param $array_out array
     * @param $val array
     * @param $array_asc array
     */
    protected function sortTransactionByRelation(&$array_out, $val, $array_asc)
    {
        if (isset($val['org_key'])) {
            $array_out[$val['org_key']] = $val;

            if (isset($val['children']) && sizeof($val['children'])) {
                foreach ($val['children'] as $id) {
                    $this->sortTransactionByRelation($array_out, $array_asc[$id], $array_asc);
                }
            }

            unset($array_out[$val['org_key']]['children'], $array_out[$val['org_key']]['org_key']);
        }
    }

    /**
     * Return HTML of the JS & CSS Resource Files
     * @return string
     */
    private static function getOrderTransPanelResourcesHtml()
    {
        $html = "";

        $html .= emp_add_external_resources(
            array(
                "bootstrap.min.js",
                "bootstrap.min.css",
                "font-awesome.min.css"
            )
        );

        $html .= emp_add_external_resources(
            array(
                "bootstrapValidator.min.js",
                "treegrid/treegrid.min.js",
                "jquery.number.min.js",
                "treegrid/treegrid.min.css",
                "bootstrapValidator.min.css",
                "admin_order.css"
            )
        );

        return $html;
    }

    /**
     * Build HTML for the Order Admin PAge
     * @param stdClass $data
     * @return string
     */
    private static function getAdminTransPanelHTML($data)
    {
        $module_name = $data->params['module_name'];
        ob_start();
        ?>
        <div class="panel-group" id="accordion" style="margin-top: 30pt;">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <a data-toggle="<?php echo $module_name;?>-collapse" data-target="#transactionsTable" href="javascript:void(1);">
                            <span class="emerchantpay-logo">
                                <?php echo $data->translations['panel']['title']; ?>
                            </span>
                        </a>
                    </h4>
                </div>
                <div id="collapseOne" class="">
                    <table id="transactionsTable" class="table table-hover tree">
                        <thead>
                        <tr>
                            <?php
                            $headerTranslations = $data->translations['panel']['transactions']['header'];
                            ?>
                            <th><?php echo $headerTranslations['id']; ?></th>
                            <th><?php echo $headerTranslations['type']; ?></th>
                            <th><?php echo $headerTranslations['timestamp']; ?></th>
                            <th><?php echo $headerTranslations['amount']; ?></th>
                            <th><?php echo $headerTranslations['status']; ?></th>
                            <th><?php echo $headerTranslations['message']; ?></th>
                            <th><?php echo $headerTranslations['mode']; ?></th>
                            <th><?php echo $headerTranslations['action_capture']; ?></th>
                            <th><?php echo $headerTranslations['action_refund']; ?></th>
                            <th><?php echo $headerTranslations['action_void']; ?></th>
                        </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($data->transactions as $transaction) { ?>
                            <tr class="treegrid-<?php echo $transaction['unique_id'];?> <?php if(strlen($transaction['reference_id']) > 1): ?> treegrid-parent-<?php echo $transaction['reference_id'];?> <?php endif;?>">
                                <td class="text-left">
                                    <?php echo $transaction['unique_id'];?>
                                </td>
                                <td class="text-left">
                                    <?php echo $transaction['type']; ?>
                                </td>
                                <td class="text-left">
                                    <?php echo $transaction['timestamp']; ?>
                                </td>
                                <td class="text-right">
                                    <?php echo $transaction['amount']; ?>
                                </td>
                                <td class="text-left">
                                    <?php echo $transaction['status']; ?>
                                </td>
                                <td class="text-left">
                                    <?php echo $transaction['message']; ?>
                                </td>
                                <td class="text-left">
                                    <?php echo $transaction['mode']; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($transaction['can_capture']) { ?>
                                        <div class="transaction-action-button">
                                            <a class="button btn btn-transaction btn-success" id="button-capture" role="button"
                                               data-post-action="<?php echo $data->params['modal']['capture']['form']['action'];?>"
                                               data-toggle="<?php echo $module_name;?>-tooltip" data-placement="bottom"
                                               data-title="<?php echo $data->translations['modal']['capture']['title'];?>"
                                               data-reference-id="<?php echo $transaction['unique_id'];?>"
                                               data-amount="<?php echo $transaction['available_amount'];?>">
                                                <i class="fa fa-check fa-lg"></i>
                                            </a>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($transaction['can_refund']) { ?>
                                        <div class="transaction-action-button">
                                            <a class="button btn btn-transaction btn-warning" id="button-refund" role="button"
                                               data-post-action="<?php echo $data->params['modal']['refund']['form']['action'];?>"
                                               data-toggle="<?php echo $module_name;?>-tooltip" data-placement="bottom"
                                               title="<?php echo $data->translations['modal']['refund']['title'];?>"
                                               data-reference-id="<?php echo $transaction['unique_id'];?>"
                                               data-amount="<?php echo $transaction['available_amount'];?>">
                                                <i class="fa fa-reply fa-lg"></i>
                                            </a>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($transaction['can_void']) { ?>
                                        <div class="transaction-action-button">
                                            <a class="button btn btn-transaction btn-danger" id="button-void"
                                               data-post-action="<?php echo $data->params['modal']['void']['form']['action'];?>"
                                               data-toggle="<?php echo $module_name;?>-tooltip"
                                               data-placement="bottom"
                                                <?php if (!$data->params['modal']['void']['allowed']) { ?>
                                                    title="Cancel Transaction is currently disabled! <br /> This option can be enabled in the <strong>Module Settings</strong>, but it depends on the <strong>acquirer</strong>. For further Information please contact your <strong>Account Manager</strong>"
                                                <?php } elseif ($transaction['void_exists']) { ?>
                                                    title="There is already an approved <strong>Cancel Transaction</strong> for <strong><?php echo ucfirst($transaction['type']);?> Transaction</strong> with Unique Id: <strong><?php echo $transaction['unique_id'];?></strong>"
                                                <?php } ?>

                                                <?php if (!$data->params['modal']['void']['allowed'] || $transaction['void_exists']) { ?>
                                                    disabled="disabled"
                                                <?php } else { ?>
                                                    title="<?php echo $data->translations['modal']['void']['title'];?>"
                                                <?php } ?>

                                               role="button" data-post-action="doVoid" data-reference-id="<?php echo $transaction['unique_id'];?>">
                                               <i class="fa fa-remove fa-lg"></i>
                                            </a>
                                                <span class="btn btn-primary" id="img_loading_void" style="display:none;">
                                                    <i class="fa fa-circle-o-notch fa-spin fa-lg"></i>
                                                </span>
                                        </div>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="<?php echo $module_name;?>-modal" class="modal fade">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">
                            <i class="icon-times"></i>
                        </button>
                        <span class="<?php echo $module_name;?>-modal-title emerchantpay-logo"></span>
                    </div>
                    <div class="modal-body">
                        <form id="<?php echo $module_name?>-modal-form" class="modal-form" name="<?php echo $module_name?>-modal-form" data-action="<?php echo tep_href_link(FILENAME_ORDERS, tep_get_all_get_params(array('action')) . 'action=edit');?>" data-method="post">
                            <input type="hidden" name="reference_id" value="" />
                            <input type="hidden" name="action" value="" />


                            <div id="<?php echo $module_name;?>_capture_trans_info" class="row" style="display: none;">
                                <div class="col-xs-12">
                                    <div class="alert alert-info">
                                        You are allowed to process only full capture through this panel!
                                        <br/>
                                        This option can be enabled in the <strong>Module Settings</strong>, but it depends on the <strong>acquirer</strong>.
                                        For further Information please contact your <strong>Account Manager</strong>.
                                    </div>
                                </div>
                            </div>

                            <div id="<?php echo $module_name;?>_refund_trans_info" class="row" style="display: none;">
                                <div class="col-xs-12">
                                    <div class="alert alert-info">
                                        You are allowed to process only full refund through this panel!
                                        <br/>
                                        This option can be enabled in the <strong>Module Settings</strong>, but it depends on the <strong>acquirer</strong>.
                                        For further Information please contact your <strong>Account Manager</strong>.
                                    </div>
                                </div>
                            </div>

                            <div id="<?php echo $module_name;?>_void_trans_info" class="row" style="display: none;">
                                <div class="col-xs-12">
                                    <div class="alert alert-warning">
                                        This service is only available for particular acquirers!
                                        <br/>
                                        For further Information please contact your Account Manager.
                                    </div>
                                </div>
                            </div>

                            <div class="form-group amount-input">
                                <label for="<?php echo $module_name;?>_transaction_amount">Amount:</label>
                                <div class="input-group">
                                    <span class="input-group-addon" data-toggle="<?php echo $module_name;?>-tooltip" data-placement="top" title="<?php echo $data->params['currency']['iso_code'];?>"><?php echo $data->params['currency']['sign'];?></span>
                                    <input type="text" class="form-control" id="<?php echo $module_name;?>_transaction_amount" name="amount" placeholder="Amount..." />
                                </div>
                                <span class="help-block" id="<?php echo $module_name;?>-amount-error-container"></span>
                            </div>

                            <div class="form-group usage-input">
                                <label for="<?php echo $module_name;?>_transaction_message">Message (optional):</label>
                                <textarea class="form-control form-message" rows="3" id="<?php echo $module_name;?>_transaction_message" name="message" placeholder="Message"></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <span class="form-loading">
                            <i class="fa fa-spinner fa-spin fa-lg"></i>
                        </span>
                        <span class="form-buttons">
                            <button class="btn btn-default" data-dismiss="modal" aria-hidden="true">Cancel</button>
                            <button id="<?php echo $module_name;?>-modal-submit" class="btn btn-submit btn-primary">Submit</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    /**
     * Build JS for the Order Admin Page
     * @param stdClass $data
     * @return string
     */
    private static function getAdminTransactionsPanelJS($data)
    {
        $module_name = $data->params['module_name'];
        ob_start();
        ?>
        <script type="text/javascript">
            var modalPopupDecimalValueFormatConsts = {
                decimalPlaces       : <?php echo $data->params['currency']['decimalPlaces'];?>,
                decimalSeparator    : "<?php echo $data->params['currency']['decimalSeparator'];?>",
                thousandSeparator   : "<?php echo $data->params['currency']['thousandSeparator'];?>"
            };

            (function($) {
                jQuery.exists = function(selector) {
                    return ($(selector).length > 0);
                }
            }(window.jQuery));

            function submitForm() {
                var $modalForm = $('#<?php echo $module_name;?>-modal-form');
                var submitFormPostAction = $modalForm.attr('data-post-action');
                $modalForm.find('input:hidden[name="action"]').val(submitFormPostAction);

                var $modalDialog = $modalForm.parents().eq(4).find('.modal-dialog');

                $.ajax({
                    url:    $modalForm.attr('data-action'),
                    type:   $modalForm.attr('data-method'),
                    data:   $modalForm.serialize(),
                    beforeSend: function () {
                        $modalDialog.find('.form-buttons').hide();
                        $modalDialog.find('.form-loading').show();
                    },
                    complete: function() {
                        $modalDialog.find('.form-loading').hide();
                        $modalDialog.find('.form-buttons').show();
                    },
                    success: function (data) {
                        location.reload();
                    },
                    error: function(xhr) {
                    }
                });
            }

            $(document).ready(function() {

                jQuery("a[data-toggle='<?php echo $module_name;?>-collapse']").click(function() {
                    var targetId = jQuery(this).attr('data-target');

                    jQuery(targetId).toggle('slow');
                });

                jQuery(".tree").treegrid({
                    expanderExpandedClass:  "treegrid-expander-expanded",
                    expanderCollapsedClass: "treegrid-expander-collapsed"
                });

                $('.btn-transaction').click(function() {
                    if (jQuery(this).is('[disabled]'))
                        return ;

                    transactionModal($(this).attr('data-post-action'), $(this).attr('data-reference-id'), $(this).attr('data-amount'));
                });

                $('.btn-submit').click(function() {
                    submitForm();
                });



                var modalObj = $('#<?php echo $module_name;?>-modal'),
                    transactionAmountInput = $('#<?php echo $module_name;?>_transaction_amount', modalObj);

                modalObj.on('hide.bs.modal', function() {
                    destroyBootstrapValidator('#<?php echo $module_name;?>-modal-form');
                });

                modalObj.on('shown.bs.modal', function() {
                    /* enable the submit button just in case (if the bootstrapValidator is enabled it will disable the button if necessary */
                    $('#<?php echo $module_name;?>-modal-submit').removeAttr('disabled');

                    if (createBootstrapValidator('#<?php echo $module_name;?>-modal-form')) {
                        executeBootstrapFieldValidator('#<?php echo $module_name;?>-modal-form', 'fieldAmount');
                    }
                });

                transactionAmountInput.number(true, modalPopupDecimalValueFormatConsts.decimalPlaces,
                    modalPopupDecimalValueFormatConsts.decimalSeparator,
                    modalPopupDecimalValueFormatConsts.thousandSeparator);

                $('[data-toggle="<?php echo $module_name;?>-tooltip"]').tooltip({
                    html: true
                });
            });

            function transactionModal(post_action, reference_id, amount) {
                if ((typeof amount == 'undefined') || (amount == null))
                    amount = 0;

                var modalObj = $('#<?php echo $module_name;?>-modal');

                var modalForm = $('#<?php echo $module_name;?>-modal-form', modalObj);

                var modalTitle = modalObj.find('span.<?php echo $module_name;?>-modal-title'),
                    modalAmountInputContainer = modalObj.find('div.amount-input'),
                    captureTransactionInfoHolder = $('#<?php echo $module_name;?>_capture_trans_info', modalObj),
                    refundTransactionInfoHolder = $('#<?php echo $module_name;?>_refund_trans_info', modalObj),
                    cancelTransactionWarningHolder = $('#<?php echo $module_name;?>_void_trans_info', modalObj),
                    transactionAmountInput = $('#<?php echo $module_name;?>_transaction_amount', modalObj);

                updateTransModalControlState([
                        captureTransactionInfoHolder,
                        refundTransactionInfoHolder,
                        cancelTransactionWarningHolder,
                        modalAmountInputContainer
                    ],
                    false
                );

                switch(post_action) {
                    case '<?php echo $data->params['modal']['capture']['form']['action'];?>':
                        modalTitle.text('<?php echo $data->translations['modal']['capture']['title'];?>');
                        updateTransModalControlState([modalAmountInputContainer], true);
                    <?php if (!$data->params['modal']['capture']['allowed']) { ?>
                        updateTransModalControlState([captureTransactionInfoHolder], true);
                        transactionAmountInput.attr('readonly', 'readonly');
                    <?php } else { ?>
                        transactionAmountInput.removeAttr('readonly');
                    <?php } ?>
                        break;

                    case '<?php echo $data->params['modal']['refund']['form']['action'];?>':
                        modalTitle.text('<?php echo $data->translations['modal']['refund']['title'];?>');
                        updateTransModalControlState([modalAmountInputContainer], true);
                    <?php if (!$data->params['modal']['refund']['allowed']) { ?>
                        updateTransModalControlState([refundTransactionInfoHolder], true);
                        transactionAmountInput.attr('readonly', 'readonly');
                    <?php } else { ?>
                        transactionAmountInput.removeAttr('readonly');
                    <?php } ?>
                        break;

                    case '<?php echo $data->params['modal']['void']['form']['action'];?>':
                        modalTitle.text('<?php echo $data->translations['modal']['void']['title'];?>');
                    <?php if (!$data->params['modal']['void']['allowed']) { ?>
                        updateTransModalControlState([cancelTransactionWarningHolder], true);
                    <?php } ?>
                        break;

                    default:
                        return;
                }

                modalObj.find('input[name="reference_id"]').val(reference_id);

                modalForm.attr('data-post-action', post_action);

                transactionAmountInput.val(amount);

                modalObj.modal('show');

            }

            function updateTransModalControlState(controls, visibilityStatus) {
                $.each(controls, function(index, control){
                    if (!$.exists(control))
                        return; /* continue to the next item */

                    if (visibilityStatus)
                        control.fadeIn('fast');
                    else
                        control.fadeOut('fast');
                });
            }

            function formatTransactionAmount(amount) {
                if ((typeof amount == 'undefined') || (amount == null))
                    amount = 0;
                return $.number(amount, modalPopupDecimalValueFormatConsts.decimalPlaces,
                    modalPopupDecimalValueFormatConsts.decimalSeparator,
                    modalPopupDecimalValueFormatConsts.thousandSeparator);
            }

            function executeBootstrapFieldValidator(formId, validatorFieldName) {
                var submitForm = $(formId);
                submitForm.bootstrapValidator('validateField', validatorFieldName);
                submitForm.bootstrapValidator('updateStatus', validatorFieldName, 'NOT_VALIDATED');
            }

            function destroyBootstrapValidator(submitFormId) {
                $(submitFormId).bootstrapValidator('destroy');
            }
            function createBootstrapValidator(submitFormId) {
                var submitForm = $(submitFormId),
                    transactionAmount = formatTransactionAmount($('#<?php echo $module_name;?>_transaction_amount').val());
                destroyBootstrapValidator(submitFormId);

                var transactionAmountControlSelector = '#<?php echo $module_name;?>_transaction_amount';

                var shouldCreateValidator = $.exists(transactionAmountControlSelector);

                /* it is not needed to create attach the bootstapValidator,
                 when the field to validate is not visible (Void Transaction) */
                if (!shouldCreateValidator) {
                    return false;
                }

                submitForm.bootstrapValidator({
                        fields: {
                            fieldAmount: {
                                selector: transactionAmountControlSelector,
                                trigger: 'keyup',
                                validators: {
                                    notEmpty: {
                                        message: 'The transaction amount is a required field!'
                                    },
                                    stringLength: {
                                        max: 10
                                    },
                                    greaterThan: {
                                        value: 0,
                                        inclusive: false
                                    },
                                    lessThan: {
                                        value: transactionAmount,
                                        inclusive: true
                                    }
                                }
                            }
                        }
                    })
                    .on('error.field.bv', function(e, data) {
                        $('#<?php echo $module_name;?>-modal-submit').attr('disabled', 'disabled');
                    })
                    .on('success.field.bv', function(e) {
                        $('#<?php echo $module_name;?>-modal-submit').removeAttr('disabled');
                    })
                    .on('success.form.bv', function(e) {
                        e.preventDefault(); // Prevent the form from submitting
                        /* submits the transaction form (No validators have failed) */
                        submitForm.bootstrapValidator('defaultSubmit');
                    });

                return true;
            }

        </script>
        <?php
        $js = ob_get_contents();
        ob_end_clean();
        return $js;
    }

    /**
     * Build Complete Order Admin Transactions Panel Content
     * @param \stdClass $data
     * @return bool|string
     */
    public static function printOrderTransactions($data)
    {
        if (count($data->transactions) == 0) {
            return false;
        }

        $resourcesHTML = static::getOrderTransPanelResourcesHtml();
        $html = static::getAdminTransPanelHTML($data);
        $js = static::getAdminTransactionsPanelJS($data);

        $outputStartBlock = '<td><table class="noprint">'."\n";
        $outputStartBlock .= '<tr style="background-color : #bbbbbb; border-style : dotted;">'."\n";
        $outputEndBlock = '</tr>'."\n";
        $outputEndBlock .='</table></td>'."\n";

        return
            $outputStartBlock .
            $resourcesHTML .
            $html .
            $js .
            $outputEndBlock;
    }

    /**
     * Generates Admin Order Transactions Panel
     * @param int $order_id
     * @return null|string
     */
    public function displayTransactionsPanel($order_id)
    {
        global $order;

        $data = new stdClass;
        $data->paths = array(
            'images' => DIR_FS_ADMIN . 'images/emerchantpay/',
            'js' => 'includes/javascript/emerchantpay/js/',
            'css' => 'includes/javacript/emerchantpay/css/'
        );

        $currency = static::getCurrencyData(
            $order->info['currency']
        );

        if ($currency === false) {
            return null;
        }

        $data->params = array(
            'module_name' => 'emerchantpay',
            'currency' => $currency
        );

        $this->extendOrderTransPanelData($data);

        $query = tep_db_query('SELECT *
				FROM `' . $this->getTableNameTransactions() . '`
				WHERE `order_id` = ' . $order_id);
        $transactions = array();

        while ($transactionFields = tep_db_fetch_array($query)) {
            $transactions[] = $transactionFields;
        }

        foreach ($transactions as &$transaction) {
            $transaction['timestamp'] = date('H:i:s m/d/Y', strtotime($transaction['timestamp']));

            if (static::getCanCaptureTransaction($transaction, $this->getCheckoutSelectedTypes())) {
                $transaction['can_capture'] = true;
            } else {
                $transaction['can_capture'] = false;
            }

            if ($transaction['can_capture']) {
                $totalAuthorizedAmount = $this->getTransactionsSumAmount(
                    $transaction['order_id'],
                    $transaction['reference_id'],
                    array(
                        Types::AUTHORIZE,
                        Types::AUTHORIZE_3D,
                        Types::GOOGLE_PAY,
                        Types::PAY_PAL,
                        Types::APPLE_PAY
                    ),
                    \Genesis\Api\Constants\Transaction\States::APPROVED
                );
                $totalCapturedAmount = $this->getTransactionsSumAmount(
                    $transaction['order_id'],
                    $transaction['unique_id'],
                    Types::CAPTURE,
                    \Genesis\Api\Constants\Transaction\States::APPROVED
                );
                $transaction['available_amount'] = $totalAuthorizedAmount - $totalCapturedAmount;
            }

            if (static::getCanRefundTransaction($transaction, $this->getCheckoutSelectedTypes())) {
                $transaction['can_refund'] = true;
            } else {
                $transaction['can_refund'] = false;
            }

            if ($transaction['can_refund']) {
                $totalCapturedAmount = $transaction['amount'];
                $totalRefundedAmount = $this->getTransactionsSumAmount(
                    $transaction['order_id'],
                    $transaction['unique_id'],
                    Types::REFUND,
                    \Genesis\Api\Constants\Transaction\States::APPROVED
                );
                $transaction['available_amount'] = $totalCapturedAmount - $totalRefundedAmount;
            }

            if (static::getCanVoidTransaction($transaction)) {
                $transaction['can_void'] = true;
                $transaction['void_exists'] = $this->getTransactionsByTypeAndStatus(
                    $transaction['order_id'],
                    $transaction['unique_id'],
                    Types::VOID,
                    \Genesis\Api\Constants\Transaction\States::APPROVED
                ) !== false;
            } else {
                $transaction['can_void'] = false;
            }

            if (!isset($transaction['available_amount'])) {
                $transaction['available_amount'] = $transaction['amount'];
            }

            $transaction['amount'] = static::formatTransactionValue(
                $transaction['amount'],
                $currency
            );

            $transaction['available_amount'] = static::formatTransactionValue(
                $transaction['available_amount'],
                $currency
            );
        }

        // Sort the transactions list in the following order:
        //
        // 1. Sort by timestamp (date), i.e. most-recent transactions on top
        // 2. Sort by relations, i.e. every parent has the child nodes immediately after

        // Ascending Date/Timestamp sorting
        uasort($transactions, function ($a, $b) {
            // sort by timestamp (date) first
            if (@$a["timestamp"] == @$b["timestamp"]) {
                return 0;
            }

            return (@$a["timestamp"] > @$b["timestamp"]) ? 1 : -1;
        });

        // Create the parent/child relations from a flat array
        $array_asc = array();

        foreach ($transactions as $key => $val) {
            // create an array with ids as keys and children
            // with the assumption that parents are created earlier.
            // store the original key
            if (isset($array_asc[$val['unique_id']])) {
                $array_asc[$val['unique_id']]['org_key'] = $key;

                $array_asc[$val['unique_id']] = array_merge($val, $array_asc[$val['unique_id']]);
            } else {
                $array_asc[$val['unique_id']] = array_merge($val, array('org_key' => $key));
            }

            if ($val['reference_id']) {
                $array_asc[$val['reference_id']]['children'][] = $val['unique_id'];
            }
        }

        // Order the parent/child entries
        $transactions = array();

        foreach ($array_asc as $val) {
            if (isset($val['reference_id']) && $val['reference_id']) {
                continue;
            }

            $this->sortTransactionByRelation($transactions, $val, $array_asc);
        }

        $data->transactions = $transactions;

        return $this->printOrderTransactions($data);
    }

    /**
     * Updates Order Status and created Order Status History
     * from the Gateway Response
     * @param int $orderId
     * @return bool
     */
    protected function processUpdateOrder($orderId)
    {
        global $messageStack;

        if (!isset($this->responseObject) || !isset($this->responseObject->status)) {
            return false;
        }

        switch ($this->responseObject->status) {
            case \Genesis\Api\Constants\Transaction\States::APPROVED:
                $orderStatusId = $this->getSetting('PROCESSED_ORDER_STATUS_ID');
                $isPaymentSuccessful = true;
                break;
            case \Genesis\Api\Constants\Transaction\States::ERROR:
            case \Genesis\Api\Constants\Transaction\States::DECLINED:
                $orderStatusId = $this->getSetting("FAILED_ORDER_STATUS_ID");
                $isPaymentSuccessful = false;
                break;
            default:
                $orderStatusId = $this->getSetting("ORDER_STATUS_ID");
                $isPaymentSuccessful = false;
        }

        self::setOrderStatus(
            $orderId,
            $orderStatusId
        );

        self::performOrderStatusHistory(
            array(
                'type'            => 'Gateway Response',
                'orders_id'       => $orderId,
                'order_status_id' => $orderStatusId,
                'payment'         => array(
                    'unique_id' =>
                        isset($this->responseObject->unique_id)
                            ? $this->responseObject->unique_id
                            : "",
                    'status'    =>
                        $this->responseObject->status,
                    'message'   =>
                        isset($this->responseObject->message)
                            ? $this->responseObject->message
                            : ""
                )
            )
        );

        if (!$isPaymentSuccessful) {
            $messageStack->add_session(
                'checkout_payment',
                $this->getSetting("MESSAGE_PAYMENT_FAILED"),
                'error'
            );
            tep_redirect(
                tep_href_link(
                    FILENAME_CHECKOUT_PAYMENT,
                    '',
                    'SSL'
                )
            );
        }

        return true;
    }

    /**
     * Save Order Status History to the database
     * @param array $data
     */
    protected static function performOrderStatusHistory($data)
    {
        $sql_data_array = array(
            'orders_id'         => $data['orders_id'],
            'orders_status_id'  => $data['order_status_id'],
            'date_added'        => 'now()',
            'customer_notified' => '1',
            'comments'          =>
                sprintf(
                    "[{$data['type']}]" .  PHP_EOL .
                    "- Unique ID: %s" . PHP_EOL .
                    "- Status: %s".     PHP_EOL .
                    "- Message: %s",
                    $data['payment']['unique_id'],
                    $data['payment']['status'],
                    $data['payment']['message']
                ),
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
    }

    /**
     * Determines if the Payment Module should be available for the Order Quote
     * @return bool
     */
    protected function getIsAvailable()
    {
        return
            $this->getIsInstalled() &&
            $this->getIsEnabled() &&
            $this->getIsConfigured() &&
            (!$this->getModuleRequiresSSL() || $this->getIsSSLEnabled());
    }

    /**
     * Registers function for Module Admin Settings Page
     * @return void
     */
	protected function registerAdminScriptsAndFunctions()
    {
        $files = array(
            'functions_emerchantpay.php',
            'functions_emerchantpay_cfg.php'
        );

        foreach ($files as $file) {
            $path = defined('DIR_FS_ADMIN') ? DIR_FS_ADMIN : DIR_FS_CATALOG . '/admin/';
            $file = $path . "ext/modules/payment/emerchantpay/{$file}";

            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Displays Error Message on Module Setting Page
     * @param string $errorMessage
     * @return void
     */
    protected function displayAdminErrorAndDisableModule($errorMessage)
    {
        $this->title .= '<span class="error-emerchantpay"> (Error)</span>';
        $this->description =
            '<div class="secError">' .
            $errorMessage .
            '</div>' .
            $this->description;

        $this->enabled = false;
    }

    /**
     * Updates Module Status
     * @return void
     */
    function update_status()
    {
        $this->enabled =
            $this->getIsAvailable();

        if ( ($this->enabled == true) && ($this->getIntSetting('ZONE') > 0) ) {
            $check_flag = false;

            $check_query = tep_db_query("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . $this->getSetting('ZONE') . "' AND zone_country_id = '" . $this->order->billing['country']['id'] . "' ORDER BY zone_id");

            while ($check = tep_db_fetch_array($check_query)) {
                if (isset($check['zone_id']) && $check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                }
                elseif ($check['zone_id'] == $this->order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    /**
     * Include Javascript Validations on Checkout Payment Page
     * @return bool
     */
	function javascript_validation() {
        return false;
    }

    /**
     * Modifies Module Listing on the Checkout Page
     * @return array
     */
	function selection()
    {
        $selection = array(
            'id'    =>
                $this->code,
            'module'=>
                $this->public_title . $this->getSetting('TEXT_PUBLIC_CHECKOUT_CONTAINER')
        );

        return $selection;
    }

	function checkout_initialization_method()
    {
        return false;
    }

    /**
     * Confirmation Check mothod for Checkout Payment Page
     * @return bool
     */
	function pre_confirmation_check()
    {
        return false;
    }

    /**
     * Confirmation Check mothod for Checkout Confirmation Page
     * @return bool
     */
	function confirmation() {
        return false;
    }

    /**
     * Customizes Checkoput Payment Submit Button
     * @return bool
     */
	function process_button()
    {
        return false;
    }

    function output_error()
    {
        return false;
    }

    /**
     * Builds Checkout Process Error Message
     * @return array
     */
	function get_error()
    {
        global $messageStack;

        $errorMessage = $this->getSetting('ERROR_DESC');

        if (!is_null($messageStack) && isset($messageStack->messages)) {
            foreach ($messageStack->messages as $message) {
                $errorMessage = $message['class'];
            }
        }

        return array(
            'title' => $this->getSetting('ERROR_TITLE'),
            'error' => $errorMessage
        );
    }

    /**
     * Process Request to the Genesis Gateway
     * @return bool
     */
    public function before_process()
    {
        return $this->doBeforeProcessPayment();
    }

    /**
     * Handles the Response from the Genesis Gateway
     * @return bool
     */
    function after_process()
    {
        return $this->doAfterProcessPayment();
    }

    /**
     * Checks if Module is installed
     * @return int
     */
	function check() {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $this->getConfigPrefix() . 'STATUS' . "'");
            $this->_check = tep_db_num_rows($check_query);
        }

        return $this->_check;
    }

    /**
     * Builds a list of the available admin setting
     * @return array
     */
    protected function getConfigurationValues()
    {
        return array(
            array(
                "Enable Module",
                $this->getSettingKey('STATUS'),
                "true",
                "Do you want to process payments via emerchantpays Genesis Gateway?",
                "6",
                "1",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
            array(
                "Genesis API Username",
                $this->getSettingKey('USERNAME'),
                "",
                "Enter your Username, required for accessing the Genesis Gateway",
                "6",
                "20",
                "emp_zfg_draw_input({$this->requiredOptionsAttributes}, ",
                null
            ),
            array(
                "Genesis API Password",
                $this->getSettingKey('PASSWORD'),
                "",
                "Enter your Password, required for accessing the Genesis Gateway",
                "6",
                "30",
                "emp_zfg_draw_input({$this->requiredOptionsAttributes}, ",
                null
            ),
            array(
                "Live Mode",
                $this->getSettingKey('ENVIRONMENT'),
                "false",
                "If disabled, transactions are going through our Staging (Test) server, NO MONEY ARE BEING TRANSFERRED",
                "6",
                "50",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
            array(
                "Partial Capture",
                $this->getSettingKey('ALLOW_PARTIAL_CAPTURE'),
                "true",
                "Use this option to allow / deny Partial Capture Transactions",
                "6",
                "50",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
            array(
                "Partial Refund",
                $this->getSettingKey('ALLOW_PARTIAL_REFUND'),
                "true",
                "Use this option to allow / deny Partial Refund Transactions",
                "6",
                "50",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
            array(
                "Void Transaction",
                $this->getSettingKey('ALLOW_VOID_TRANSACTIONS'),
                "true",
                "Use this option to allow / deny Cancel Transactions",
                "6",
                "50",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
            array(
                "Sort order of display.",
                $this->getSettingKey('SORT_ORDER'),
                "0",
                "Sort order of display. Lowest is displayed first.",
                "6",
                "70",
                "emp_zfg_draw_number_input({$this->sortOrderAttributes}, ",
                null
            ),
            array(
                "Payment Zone",
                $this->getSettingKey('ZONE'),
                "0",
                "If a zone is selected, only enable this payment method for that zone.",
                "6",
                "75",
                "emp_cfg_pull_down_zone_classes(",
                "tep_get_zone_class_title"
            ),
            array(
                "Set Default Order Status",
                $this->getSettingKey('ORDER_STATUS_ID'),
                "1",
                "Set the default status of orders made with this payment module to this value",
                "6",
                "80",
                "emp_zfg_pull_down_order_statuses(",
                "tep_get_order_status_name"
            ),
            array(
                "Set Failed Order Status",
                $this->getSettingKey('FAILED_ORDER_STATUS_ID'),
                "1",
                "Set the status of failed orders made with this payment module to this value",
                "6",
                "90",
                "emp_zfg_pull_down_order_statuses(",
                "tep_get_order_status_name"
            ),
            array(
                "Set Processed Order Status",
                $this->getSettingKey('PROCESSED_ORDER_STATUS_ID'),
                "2",
                "Set the status of processed orders made with this payment module to this value",
                "6",
                "100",
                "emp_zfg_pull_down_order_statuses(",
                "tep_get_order_status_name"
            ),
            array(
                "Set Refunded Order Status",
                $this->getSettingKey('REFUNDED_ORDER_STATUS_ID'),
                "1",
                "Set the status of refunded orders made with this payment module",
                "6",
                "100",
                "emp_zfg_pull_down_order_statuses(",
                "tep_get_order_status_name"
            ),
            array(
                "Set Canceled Order Status",
                $this->getSettingKey('CANCELED_ORDER_STATUS_ID'),
                "1",
                "Set the status of canceled orders made with this payment module",
                "6",
                "100",
                "emp_zfg_pull_down_order_statuses(",
                "tep_get_order_status_name"
            )
        );
    }

    /**
     * Install Module
     * @return void
     */
	function install()
    {
        global $messageStack;

        // Delete any previous leftovers
        $this->remove();

        $isOrdersPHPFileSuccessfullyPatched = $this->doCheckAndPatchOrdersCoreTemplateFile(false);

        $this->doCreateMetaData();


        // Insert our custom statuses
        foreach ($this->statuses() as $status) {
            $this->updateStatuses($status);
        }

        $configurationItemsValues = $this->getConfigurationValues();

        foreach ($configurationItemsValues as $key => $configurationItemValues) {
            $this->doInsertConfigurationItem(
                $configurationItemValues
            );
        }

        if ($isOrdersPHPFileSuccessfullyPatched) {
            $messageStack->add_session("Module installed successfully", 'success');
        } else {
            $ordersPHPFile = DIR_FS_ADMIN . "orders.php";
            $messageStack->add_session(
                sprintf("Orders Template file could not be modified! " .
                            "Please, give write permission to file \"%s\" and reinstall plugin or contact support for more info!",
                        $ordersPHPFile
                ),
                'error'
            );
        }
    }

    /**
     * Check or Patch Admin Orders File (Allow displaying Order Transactions Panel)
     * @param bool $shouldOnlyCheckIfPatched
     * @return bool
     */
    protected function doCheckAndPatchOrdersCoreTemplateFile($shouldOnlyCheckIfPatched = false)
    {
        //disable function for Catalog (Only for Admin)
        if (!defined('DIR_FS_ADMIN')) {
            return true;
        }

        $orderTransactionsPanelAutoLoadSearchBlock =
            "require_once \$empOrderTransactionsPanelFile;";

        $orderTransactionsPanelAutoLoadIncludeBlock =
            "\$empOrderTransactionsPanelFile = DIR_FS_ADMIN . \"ext/modules/payment/emerchantpay/order_transactions_panel.php\";
  if (file_exists(\$empOrderTransactionsPanelFile)) {
      {$orderTransactionsPanelAutoLoadSearchBlock}
  }
  ";

        $templateBottomAutoLoad =
            "require(DIR_WS_INCLUDES . 'template_bottom.php');";

        $ordersPHPFile = DIR_FS_ADMIN . "orders.php";

        $fileContent = $this->getFileContent($ordersPHPFile);

        if ($this->getFileContainsText($fileContent, $orderTransactionsPanelAutoLoadSearchBlock)) {
            //orders.php already extended
            return true;
        }

        if ($shouldOnlyCheckIfPatched) {
            return false;
        }

        if ($this->getFileContainsText($fileContent, $templateBottomAutoLoad)) {
            $fileContent = str_replace(
                $templateBottomAutoLoad,
                $orderTransactionsPanelAutoLoadIncludeBlock . $templateBottomAutoLoad,
                $fileContent
            );

            return $this->writeContentToFile($ordersPHPFile, $fileContent);
        }

        return false;
    }

    /**
     * Determines if a file can be overriden by the current user
     * @param string $file
     * @return bool
     */
    protected function getIsFileWritable($file)
    {
        return
            file_exists($file) &&
            is_writable($file);
    }

    /**
     * Get File Content
     * @param string $filePath
     * @return null|string
     */
    protected function getFileContent($filePath)
    {
        if (function_exists('file_get_contents')) {
            return file_get_contents($filePath);
        }

        return null;
    }

    /**
     * Override the Content of a file
     * @param string $filePath
     * @param string $content
     * @return bool
     */
    protected function writeContentToFile($filePath, $content)
    {
        try {
            if (!$this->getIsFileWritable($filePath)) {
                return false;
            }
            $handle = fopen($filePath, 'w');
            try {
                fwrite($handle, $content);
                return true;
            } finally {
                fclose($handle);
            }
        } catch (Exception $e) {
            return false;
        }

    }

    /**
     * Determines if a text fragment exists in the content of a file
     * @param string $fileContent
     * @param string $searchText
     * @return bool
     */
    protected function getFileContainsText($fileContent, $searchText)
    {
        $pattern = preg_quote($searchText, '/');

        $pattern = "/^.*$pattern.*\$/m";

        return
            preg_match_all($pattern, $fileContent, $matches) &&
            (count($matches) > 0);
    }

    /**
     * Create needed Database tables & corrections
     * @return void
     */
    protected function doCreateMetaData()
    {
        tep_db_query('CREATE TABLE IF NOT EXISTS `' . $this->getTableNameTransactions() . '` (
                          `unique_id` varchar(255) NOT NULL,
                          `reference_id` varchar(255) NOT NULL,
                          `order_id` int(11) NOT NULL,
                          `type` char(32) NOT NULL,
                          `mode` char(255) NOT NULL,
                          `timestamp` datetime NOT NULL,
                          `status` char(32) NOT NULL,
                          `message` varchar(255) DEFAULT NULL,
                          `technical_message` varchar(255) DEFAULT NULL,
                          `terminal_token` varchar(255) DEFAULT NULL,
                          `amount` decimal(10,2) DEFAULT NULL,
                          `currency` char(3) DEFAULT NULL
                        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ');
    }

    /**
     * Remove needed Metadata changes, which were installed by this module
     * @return void
     */
    protected function doRemoveMetadata()
    {
        tep_db_query('DROP TABLE IF EXISTS `' . $this->getTableNameTransactions() . '`');
    }

    /**
     * Insert Module Configuration Setting to the Database
     * @param array $values
     * @return void
     */
    protected function doInsertConfigurationItem($values)
    {
        $sql = "insert into " .
                TABLE_CONFIGURATION .
                " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) " .
                " values (%s, now())";

        $sqlValues = "";
        $lastValueIndex = count($values) - 1;

        foreach ($values as $key => $value) {
            if (is_null($value)) {
                $sqlValues .= "null";
            } else {
                $sqlValues .= sprintf("'$value'", $value);
            }
            $sqlValues .= $key == $lastValueIndex ? "" : ",";
        }

        $sql = sprintf($sql, $sqlValues);

        tep_db_query($sql);
    }

	/**
     * Builds a n array for the available Setting options
     * @param array $options
     * @return string
     */
	protected function buildSettingsDropDownOptions($options)
    {
        $result = array();

        foreach ($options as $key => $displayName) {
            $result[] = array(
                'id'   => $key,
                'text' => $displayName
            );
        }

        return $result;
    }

    /**
     * Do on Remove Module
     * @return void
     */
	function remove()
    {
        $this->doRemoveMetadata();

        // include the list of project database tables (If there are not included yet)
        if (!defined('TABLE_CONFIGURATION') && defined('DIR_WS_INCLUDES'))
        {
            require(DIR_WS_INCLUDES . 'database_tables.php');
        }

        if (defined('TABLE_CONFIGURATION'))
            tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    /**
     * Creates any needed Statuses for this Payment Module
     * @param string $status_name
     * @return mixed
     */
	function updateStatuses($status_name)
    {
        $status_name = filter_var($status_name, FILTER_SANITIZE_MAGIC_QUOTES);

        $status_query = tep_db_query("
          select orders_status_id from " . TABLE_ORDERS_STATUS . "
          where
            orders_status_name = '$status_name' limit 1
        ");

        if (tep_db_num_rows($status_query) < 1) {
            $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
            $status = tep_db_fetch_array($status_query);

            $status_id = $status['status_id'] + 1;

            $languages = tep_get_languages();

            foreach ($languages as $lang) {
                tep_db_query("
                  insert into " . TABLE_ORDERS_STATUS . "
                    (orders_status_id, language_id, orders_status_name, public_flag)
                  values
                    ('$status_id', '" . intval($lang['id']) . "', '$status_name', '1')
                ");
            }
        } else {
            $check = tep_db_fetch_array($status_query);

            tep_db_query("
                update " . TABLE_ORDERS_STATUS . "
                set
                    orders_status_name = '$status_name'
                WHERE
                    orders_status_id = '" . intval($check['orders_status_id']) . "'
            ");

            $status_id = $check['orders_status_id'];
        }

        return $status_id;
    }

    /**
     * Get a list with all available Admin Settings for the Module
     * @return array
     */
	function keys() {
        $prefix = $this->getConfigPrefix();
        return array(
            $prefix . 'STATUS',
            $prefix . 'USERNAME',
            $prefix . 'PASSWORD',
            $prefix . 'ENVIRONMENT',
            $prefix . 'ALLOW_PARTIAL_CAPTURE',
            $prefix . 'ALLOW_PARTIAL_REFUND',
            $prefix . 'ALLOW_VOID_TRANSACTIONS',
            $prefix . 'SORT_ORDER',
            $prefix . 'ZONE',
            $prefix . 'ORDER_STATUS_ID',
            $prefix . 'FAILED_ORDER_STATUS_ID',
            $prefix . 'PROCESSED_ORDER_STATUS_ID',
            $prefix . 'REFUNDED_ORDER_STATUS_ID',
            $prefix . 'CANCELED_ORDER_STATUS_ID',
        );
    }

    /**
     * Get a list with the additional Order Statuses
     * @return array
     */
	function statuses() {
        return array(
            'Processed [emerchantpay]',
            'Failed [emerchantpay]',
            'Refunded [emerchantpay]',
            'Canceled [emerchantpay]'
        );
    }

    /**
     * Inserts a setting key after existing key
     * @param array $keys
     * @param string $existingSettingItem
     * @param string $newSettingItem
     * @param string $position (after or before)
     * @return bool
     */
    protected function appendSettingKey(
        &$keys,
        $existingSettingItem,
        $newSettingItem,
        $position = 'after'
    ) {
        if (empty($existingSettingItem)) {
            $keys[] = $this->getSettingKey($newSettingItem);
            return true;
        } else {
            $existingSettingItemArrayKey = array_search(
                $this->getSettingKey($existingSettingItem),
                $keys
            );

            if ($existingSettingItemArrayKey > -1) {
                static::array_insert(
                    $keys,
                    $existingSettingItemArrayKey + ($position == 'after' ? 1 : 0),
                    $this->getSettingKey($newSettingItem)
                );
                return true;
            }
            return false;
        }
    }

    /**
     * Inserts a setting key after existing key
     * @param array $keys
     * @param array $existingSettingItems
     * @param array $newSettingItems
     * @param array $positions
     * @return bool
     */
    protected function appendSettingKeys(
        &$keys,
        $existingSettingItems,
        $newSettingItems,
        $positions = array()
    ) {
        foreach ($existingSettingItems as $key => $existingSettingItem) {
            $this->appendSettingKey(
                $keys,
                $existingSettingItems[$key],
                $newSettingItems[$key],
                isset($positions) && isset($positions[$key]) ? $positions[$key] : 'after'
            );
        }
    }

    /**
     * @param array      $array
     * @param int|string $position
     * @param mixed      $insert
     */
    protected static function array_insert(&$array, $position, $insert)
    {
        if (is_int($position)) {
            array_splice($array, $position, 0, $insert);
        } else {
            $pos   = array_search($position, array_keys($array));
            $array = array_merge(
                array_slice($array, 0, $pos),
                $insert,
                array_slice($array, $pos)
            );
        }
    }

    /**
     * Build the Notification URL for the Genesis Gateway
     * @return string
     */
    protected function getNotificationUrl()
    {
        list($publisher, $model) = explode('_', $this->code);

        return
            tep_href_link("ext/modules/payment/emerchantpay/{$model}.php", "", "SSL");
    }

    /**
     * Builds the Return URL from the Genesis Gateway
     * @param string $action
     * @return string
     */
    protected function getReturnUrl($action)
    {
        return
            tep_href_link("ext/modules/payment/emerchantpay/redirect.php", "return={$action}", "SSL");
    }

    /**
     * Check - transaction is Asynchronous
     *
     * @param string $transactionType
     *
     * @return boolean
     */
    protected static function isAsyncTransaction($transactionType )
    {
        return in_array($transactionType, array(
            Types::AUTHORIZE_3D,
            Types::SALE_3D
        ));
    }

    /**
     * Get Server Remote Address (Used for sending Requests to Genesis)
     * @return string
     */
    protected static function getServerRemoteAddress()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * Generate a TransactionId for the Genesis Gateway
     * @param string $prefix
     * @return string
     */
    protected static function getGeneratedTransactionId($prefix = '')
    {
        return $prefix . substr(md5(uniqid() . microtime(true)), 0, 30);
    }

    /**
     * @param int $userId
     * @param int $length
     * @return string
     */
    public static function getCurrentUserIdHash($userId, $length = 20)
    {
        $userHash = $userId > 0 ? sha1($userId) : static::getGeneratedTransactionId();

        return substr($userHash, 0, $length);
    }

    /**
     * Gets state code (zone code) if available,
     * otherwise gets state name (zone name)
     *
     * @param array $address
     *
     * @return string
     */
    protected static function getStateCode($address)
    {
        $state = $address['state'];

        if (isset($address['country_id']) && tep_not_null($address['country_id'])) {
            if (isset($address['zone_id']) && tep_not_null($address['zone_id'])) {
            $state = tep_get_zone_code($address['country_id'], $address['zone_id'], $state);
            }
        }

        return $state;
    }

    /**
     * Retrieve reccuring transaction types
     *
     * @return array
     */
    protected static function getRecurringTransactionTypes()
    {
        return array (
            Types::INIT_RECURRING_SALE,
            Types::INIT_RECURRING_SALE_3D,
            Types::SDD_INIT_RECURRING_SALE
        );
    }

    /**
     * Return usage of transaction
     *
     * @return string
     */
    protected static function getUsage()
    {
        return self::TRANSACTION_USAGE . ' ' . STORE_NAME;
    }

    /**
     * Determine if Google Pay, PayPal or Apple Pay Method is chosen inside the Payment settings
     *
     * @param string $transactionType Google Pay, PayPal or Apple Pay Methods
     * @return bool
     */
    protected static function isTransactionWithCustomAttribute($transactionType)
    {
        $transaction_types = [
            Types::GOOGLE_PAY,
            Types::PAY_PAL,
            Types::APPLE_PAY
        ];

        return in_array($transactionType, $transaction_types);
    }

    /**
     * Check if canCapture, canRefund based on the selected custom attribute
     *
     * @param $action
     * @param $transactionType
     * @param $selectedTypes
     * @return bool
     */
    protected static function checkReferenceActionByCustomAttr($action, $transactionType, $selectedTypes)
    {
        if (!is_array($selectedTypes)) {
            return false;
        }

        switch ($transactionType) {
            case \Genesis\Api\Constants\Transaction\Types::GOOGLE_PAY:
                if (self::ACTION_CAPTURE === $action) {
                    return in_array(
                        self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                        $selectedTypes
                    );
                }

                if (self::ACTION_REFUND === $action) {
                    return in_array(
                        self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_SALE,
                        $selectedTypes
                    );
                }
                break;
            case Types::PAY_PAL:
                if (self::ACTION_CAPTURE == $action) {
                    return in_array(
                        self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_AUTHORIZE,
                        $selectedTypes
                    );
                }

                if (self::ACTION_REFUND === $action) {
                    $refundableTypes = [
                        self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_SALE,
                        self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_EXPRESS
                    ];

                    return (count(array_intersect($refundableTypes, $selectedTypes)) > 0);
                }
                break;
            case Types::APPLE_PAY:
                if (self::ACTION_CAPTURE === $action) {
                    return in_array(
                        self::APPLE_PAY_TRANSACTION_PREFIX . self::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                        $selectedTypes
                    );
                }

                if (self::ACTION_REFUND === $action) {
                    return in_array(
                        self::APPLE_PAY_TRANSACTION_PREFIX . self::APPLE_PAY_PAYMENT_TYPE_SALE,
                        $selectedTypes
                    );
                }
                break;
            default:
                return false;
        } // end Switch

        return false;
    }

    /**
     * Check if the Genesis Transaction state is APPROVED
     *
     * @param $transactionType
     * @return bool
     */
    protected static function hasApprovedState($transactionType)
    {
        if (empty($transactionType)) {
            return false;
        }

        $state = new \Genesis\Api\Constants\Transaction\States($transactionType);

        return $state->isApproved();
    }
}
