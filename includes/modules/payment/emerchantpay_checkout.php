<?php
/**
 * emerchantpay Checkout
 *
 * Contains emerchantpay Checkout Payment Logic
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2018 emerchantpay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

/**
 * emerchantpay Checkout
 *
 * Main class, instantiated by emerchantpay providing
 * necessary methods to facilitate payments through
 * emerchantpay's Payment Gateway
 */

use Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes;
use Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes;
use Genesis\API\Constants\Transaction\Types;
use Genesis\API\Constants\Payment\Methods;

if (!class_exists('emerchantpay_method_base')) {
    require_once DIR_FS_CATALOG . 'ext/modules/payment/emerchantpay/method_base.php';
}

class emerchantpay_checkout extends emerchantpay_method_base
{
    /**
     * emerchantpay_checkout constructor.
     */
    public function __construct()
    {
        $this->code = static::EMERCHANTPAY_CHECKOUT_METHOD_CODE;
        parent::__construct();
    }

    /**
     * Get If Module Requires SSL or not
     * @return bool
     */
    protected function getModuleRequiresSSL()
    {
        return false;
    }

    /**
     * Get Transactions Table name for the Payment Module
     * @return string
     */
    protected function getTableNameTransactions()
    {
        return static::EMERCHANTPAY_CHECKOUT_TRANSACTIONS_TABLE_NAME;
    }

    /**
     * Determines if the Module Admin Settings are properly configured
     * @return bool
     */
    protected function getIsConfigured()
    {
        return
            parent::getIsConfigured() &&
            !empty($this->getSetting('TRANSACTION_TYPES'));
    }

    public function install()
    {
        parent::install();

        $this->createConsumersTable();
    }

    protected function createConsumersTable()
    {
        tep_db_query('
            CREATE TABLE `' . static::EMERCHANTPAY_CHECKOUT_CONSUMERS_TABLE_NAME . '` (
			  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `customer_id` int(10) unsigned NOT NULL,
			  `customer_email` varchar(255) NOT NULL,
			  `consumer_id` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`id`),
			  UNIQUE KEY `customer_email` (`customer_email`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT=\'Tokenization consumers in Genesis\';
        ');
    }

    public function remove()
    {
        parent::remove();

        $this->removeConsumersTable();
    }

    protected function removeConsumersTable()
    {
        tep_db_query('DROP TABLE IF EXISTS `' . static::EMERCHANTPAY_CHECKOUT_CONSUMERS_TABLE_NAME . '`');
    }

    /**
     * Process Request to the Gateway
     * @return bool
     */
    protected function doBeforeProcessPayment()
    {
        global $order;

        $data                 = new stdClass();
        $data->transaction_id = $this->getGeneratedTransactionId(self::PLATFORM_TRANSACTION_PREFIX);
        $data->description    = '';

        foreach ($order->products as $product) {
            $separator = ($product == end($order->products)) ? '' : PHP_EOL;

            $data->description .= $product['qty'] . ' x ' . $product['name'] . $separator;
        }

        $data->currency = $order->info['currency'];

        $data->language_id = $this->getSetting('LANGUAGE');

        $data->urls = array(
            'notification'   =>
                $this->getNotificationUrl(),
            'return_success' =>
                $this->getReturnUrl(static::ACTION_SUCCESS),
            'return_failure' =>
                $this->getReturnUrl(static::ACTION_FAILURE),
            'return_cancel'  =>
                $this->getReturnUrl(static::ACTION_CANCEL)
        );

        $data->order = $order;

        $errorMessage = null;

        try {
            $this->responseObject = $this->pay($data);

            if (isset($this->responseObject->consumer_id)) {
                $this->saveConsumerId($this->responseObject->consumer_id);
            }

            return true;
        } catch (\Genesis\Exceptions\ErrorAPI $api) {
            $errorMessage         = $api->getMessage();
            $this->responseObject = null;
        } catch (\Genesis\Exceptions\ErrorNetwork $e) {
            $errorMessage         = $this->getSetting('MESSAGE_CHECK_CREDENTIALS') .
                                    PHP_EOL .
                                    $e->getMessage();
            $this->responseObject = null;
        } catch (\Exception $e) {
            $errorMessage         = $e->getMessage();
            $this->responseObject = null;
        }

        if (empty($this->responseObject) && !empty($errorMessage)) {
            $this->redirectToShowError($errorMessage);
        }

        return false;
    }

    /**
     * Send transaction to Genesis
     *
     * @param stdClass $data
     *
     * @return stdClass
     * @throws Exception
     * @throws \Genesis\Exceptions\ErrorAPI
     */
    protected function pay($data)
    {
        $genesis = new \Genesis\Genesis('WPF\Create');

        $genesis
            ->request()
                ->setTransactionId($data->transaction_id)
                ->setUsage(self::getUsage())
                ->setDescription($data->description)
                ->setNotificationUrl($data->urls['notification'])
                ->setReturnSuccessUrl($data->urls['return_success'])
                ->setReturnPendingUrl($data->urls['return_success'])
                ->setReturnFailureUrl($data->urls['return_failure'])
                ->setReturnCancelUrl($data->urls['return_cancel'])
                ->setCurrency($data->currency)
                ->setAmount($data->order->info['total'])
                ->setCustomerEmail($data->order->customer['email_address'])
                ->setCustomerPhone($data->order->customer['telephone'])
                ->setBillingFirstName($data->order->billing['firstname'])
                ->setBillingLastName($data->order->billing['lastname'])
                ->setBillingAddress1($data->order->billing['street_address'])
                ->setBillingZipCode($data->order->billing['postcode'])
                ->setBillingCity($data->order->billing['city'])
                ->setBillingState($this->getStateCode($data->order->billing))
                ->setBillingCountry($data->order->billing['country']['iso_code_2'])
                ->setShippingFirstName($data->order->delivery['firstname'])
                ->setShippingLastName($data->order->delivery['lastname'])
                ->setShippingAddress1($data->order->delivery['street_address'])
                ->setShippingZipCode($data->order->delivery['postcode'])
                ->setShippingCity($data->order->delivery['city'])
                ->setShippingState($this->getStateCode($data->order->delivery))
                ->setShippingCountry($data->order->delivery['country']['iso_code_2'])
                ->setLanguage($data->language_id);

        $this->setTransactionTypes($genesis->request(), $data);
        $this->setTokenizationData($genesis->request());

        $genesis->execute();

        return $genesis->response()->getResponseObject();
    }

    /**
     * @param $request
     *
     * @throws Exception
     */
    protected function setTokenizationData($request)
    {
        global $customer_id;

        $consumer = $this->getConsumerFromDb();

        if ($consumer !== false && $consumer['customer_id'] != $customer_id) {
            return $this->redirectToShowTokenizationError();
        }

        if ($consumer === false) {
            $consumer_id = $this->getConsumerIdFromGenesisGateway();

            if ($consumer_id !== 0) {
                $this->saveConsumerId($consumer_id);
            }
        } else {
            $consumer_id = $consumer['consumer_id'];
        }

        if (!empty($consumer_id)) {
            $request->setConsumerId($consumer_id);
        }

        if ($this->getBoolSetting('WPF_TOKENIZATION')) {
            $request->setRememberCard(true);
        }
    }

    /**
     * Redirects to cancel the current operation and show tokenization error
     */
    protected function redirectToShowTokenizationError()
    {
        $this->redirectToShowError('Cannot process your request, please contact the administrator.');
    }

    /**
     * Redirects to cancel the current operation and show error
     *
     * @param string $message
     */
    protected function redirectToShowError($message)
    {
        global $messageStack;

        $messageStack->add_session($message, 'error');
        tep_redirect(
            tep_href_link(
                FILENAME_CHECKOUT_PAYMENT,
                'payment_error=' . get_class($this),
                'SSL'
            )
        );
    }

    /**
     * @return array|bool
     */
    protected function getConsumerFromDb()
    {
        global $order;

        $consumer_query = tep_db_query('
          SELECT
            *
          FROM
            `' . static::EMERCHANTPAY_CHECKOUT_CONSUMERS_TABLE_NAME . "`
          WHERE
            `customer_email` = '" . filter_var($order->customer['email_address'], FILTER_SANITIZE_MAGIC_QUOTES) . "'
        ");
        $consumer = tep_db_fetch_array($consumer_query);

        return !empty($consumer) ? $consumer : false;
    }

    /**
     * @return int
     */
    protected function getConsumerIdFromGenesisGateway()
    {
        global $order;

        try {
            $genesis = new \Genesis\Genesis('NonFinancial\Consumers\Retrieve');
            $genesis->request()->setEmail($order->customer['email_address']);

            $genesis->execute();

            $response = $genesis->response()->getResponseObject();

            if ($this->isErrorResponse($response)) {
                return 0;
            }

            return intval($response->consumer_id);
        } catch (\Exception $exception) {
            return 0;
        }
    }

    /**
     * @param $response
     *
     * @return bool
     */
    protected function isErrorResponse($response)
    {
        $state = new \Genesis\API\Constants\Transaction\States($response->status);

        return $state->isError();
    }

    /**
     * @param $consumer_id
     *
     * @return bool
     */
    protected function saveConsumerId($consumer_id)
    {
        global $customer_id, $order;

        if (empty($order->customer['email_address']) || empty($consumer_id)) {
            return false;
        }

        $consumer = $this->getConsumerFromDb();

        if ($consumer !== false) {
            return false;
        }

        tep_db_query("
            INSERT INTO `" . static::EMERCHANTPAY_CHECKOUT_CONSUMERS_TABLE_NAME . "` (
                `customer_id`,
                `customer_email`,
                `consumer_id`
            )
            VALUES (
                " . intval($customer_id) . ",
                '" . filter_var($order->customer['email_address'], FILTER_SANITIZE_MAGIC_QUOTES) . "',
                " . intval($consumer_id) . "
            )
        ");

        return true;
    }

    private function setTransactionTypes($request, $data)
    {
        $userIdHash          = static::getCurrentUserIdHash($data->order->customer['format_id']);
        $defaultCustomParams = array(
            Types::PAYBYVOUCHER_SALE   => array(
                'card_type'   => CardTypes::VIRTUAL,
                'redeem_type' => RedeemTypes::INSTANT
            ),
            Types::PAYBYVOUCHER_YEEPAY => array(
                'card_type'        => CardTypes::VIRTUAL,
                'redeem_type'      => RedeemTypes::INSTANT,
                'product_name'     => $data->description,
                'product_category' => $data->description
            ),
            Types::CITADEL_PAYIN       => array(
                'merchant_customer_id' => $userIdHash
            ),
            Types::IDEBIT_PAYIN        => array(
                'customer_account_id' => $userIdHash
            ),
            Types::INSTA_DEBIT_PAYIN   => array(
                'customer_account_id' => $userIdHash
            ),
            Types::TRUSTLY_SALE        => array(
                'user_id' => $userIdHash
            ),
            Types::KLARNA_AUTHORIZE    => get_klarna_custom_param_items($data)->toArray()
        );

        $transactionTypes = static::getCheckoutTransactionTypes();

        foreach ($transactionTypes as $type) {
            if (is_array($type)) {
                $request->addTransactionType($type['name'], $type['parameters']);
                continue;
            }

            $transactionCustomParams = isset($defaultCustomParams[$type]) ? $defaultCustomParams[$type] : [];

            $request->addTransactionType($type, $transactionCustomParams);
        }
    }

    /**
     * Generates Admin Order Transactions Panel
     *
     * @param int $order_id
     *
     * @return null|string
     */
    public function displayTransactionsPanel($order_id)
    {
        if ($this->getIsAvailable()) {
            return parent::displayTransactionsPanel($order_id);
        } else {
            return false;
        }
    }

    /**
     * Confirmation Check mothod for Checkout Confirmation Page
     * @return bool
     */
    function confirmation()
    {
        ?>
        <script type="text/javascript">
            $(document).ready(function () {
                $("form").on('submit', function () {
                    $('#tdb5').button("disable").prop('disabled', true);
                });
            });
        </script>

        <p style="text-align:center;">
            <?php echo $this->getSetting('TEXT_REDIRECT_WARNING'); ?>
        </p>
        <?php

        return false;
    }

    /**
     * Get a list with the selected Transaction Type
     * @return array
     */
    function getCheckoutTransactionTypes()
    {
        $processed_list = array();
        $alias_map      = array();

        $selected_types = array_map(
            'trim',
            explode(',', $this->getSetting('TRANSACTION_TYPES'))
        );
        $methods = \Genesis\API\Constants\Payment\Methods::getMethods();

        foreach ($methods as $method) {
            $alias_map[$method . self::PPRO_TRANSACTION_SUFFIX] = \Genesis\API\Constants\Transaction\Types::PPRO;
        }

        $alias_map = array_merge($alias_map, [
            self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE =>
                Types::GOOGLE_PAY,
            self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_SALE      =>
                Types::GOOGLE_PAY,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_AUTHORIZE         =>
                Types::PAY_PAL,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_SALE              =>
                Types::PAY_PAL,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_EXPRESS           =>
                Types::PAY_PAL
        ]);

        foreach ($selected_types as $selected_type) {
            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $key = $this->getCustomParameterKey($transaction_type);

                $processed_list[$transaction_type]['parameters'][] = array(
                    $key => str_replace(
                        [
                            self::PPRO_TRANSACTION_SUFFIX,
                            self::GOOGLE_PAY_TRANSACTION_PREFIX,
                            self::PAYPAL_TRANSACTION_PREFIX
                        ],
                        '',
                        $selected_type
                    )
                );
            } else {
                $processed_list[] = $selected_type;
            }
        }

        return $processed_list;
    }

    /**
     * Builds a list of the available admin setting
     * @return array
     */
    protected function getConfigurationValues()
    {
        $configurationValues = array(
            array(
                'Checkout Title',
                $this->getSettingKey('CHECKOUT_PAGE_TITLE'),
                'Pay safely with emerchantpay Checkout',
                'This name will be displayed on the checkout page',
                '6',
                '10',
                'emp_zfg_draw_input(null, ',
                null
            ),
            array(
                'Transaction Types',
                $this->getSettingKey('TRANSACTION_TYPES'),
                Types::SALE,
                'What transaction type should we use upon purchase?.',
                '6',
                '60',
                "emp_zfg_select_drop_down_multiple_from_object({$this->requiredOptionsAttributes}, \"{$this->code}\", \"getConfigTransactionTypesOptions\", ",
                null
            ),
            array(
                'Checkout Page Language',
                $this->getSettingKey('LANGUAGE'),
                'en',
                'What language (localization) should we have on the Checkout?.',
                '6',
                '65',
                "emp_zfg_select_drop_down_single_from_object(\"{$this->code}\", \"getConfigLanguageOptions\",",
                null
            ),
            array(
                "WPF Tokenization",
                $this->getSettingKey('WPF_TOKENIZATION'),
                "false",
                "Enable WPF Tokenization",
                "6",
                "50",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
        );

        return array_merge(
            parent::getConfigurationValues(),
            $configurationValues
        );
    }

    /**
     * Builds a list with the available Transaction Types & Payment Methods
     * @return string
     */
    public function getConfigTransactionTypesOptions()
    {
        $data = array();

        $transactionTypes = \Genesis\API\Constants\Transaction\Types::getWPFTransactionTypes();
        $excludedTypes    = self::getRecurringTransactionTypes();

        // Exclude PPRO transaction. This is not standalone transaction type
        array_push($excludedTypes, \Genesis\API\Constants\Transaction\Types::PPRO);

        // Exclude GooglePay transaction. In this way Google Pay Payment types will be introduced
        array_push($excludedTypes, \Genesis\API\Constants\Transaction\Types::GOOGLE_PAY);

        // Exclude PayPal transaction.
        array_push($excludedTypes, \Genesis\API\Constants\Transaction\Types::PAY_PAL);

        // Exclude Transaction Types
        $transactionTypes = array_diff($transactionTypes, $excludedTypes);

        //Add PPRO types
        $pproTypes = array_map(
            function ($type) {
                return $type . self::PPRO_TRANSACTION_SUFFIX;
            },
            \Genesis\API\Constants\Payment\Methods::getMethods()
        );

        $googlePayTypes = array_map(
            function ($type) {
                return self::GOOGLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                self::GOOGLE_PAY_PAYMENT_TYPE_SALE
            ]
        );

        $payPalTypes = array_map(
            function ($type) {
                return self::PAYPAL_TRANSACTION_PREFIX . $type;
            },
            [
                self::PAYPAL_PAYMENT_TYPE_AUTHORIZE,
                self::PAYPAL_PAYMENT_TYPE_SALE,
                self::PAYPAL_PAYMENT_TYPE_EXPRESS
            ]
        );

        $transactionTypes = array_merge(
            $transactionTypes,
            $pproTypes,
            $googlePayTypes,
            $payPalTypes
        );
        asort($transactionTypes);

        foreach ($transactionTypes as $type) {
            $name = \Genesis\API\Constants\Transaction\Types::isValidTransactionType($type) ?
                \Genesis\API\Constants\Transaction\Names::getName($type) : strtoupper($type);

            $data[$type] = $name;
        }

        return $this->buildSettingsDropDownOptions(
            $data
        );
    }

    /**
     * Builds a list with the available Checkout Languages
     * @return string
     */
    public function getConfigLanguageOptions()
    {
        $data = array();

        $languages = \Genesis\API\Constants\i18n::getAll();

        foreach ($languages as $language) {
            $languageTranslation = 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_' . strtoupper($language);
            $data[$language] = defined($languageTranslation) ?
                constant($languageTranslation) : strtoupper($language);
        }

        return $this->buildSettingsDropDownOptions(
            $data
        );
    }

    /**
     * Get a list with all available Admin Settings for the Module
     * @return array
     */
    function keys()
    {
        $keys = parent::keys();

        $this->appendSettingKeys(
            $keys,
            array(
                'STATUS',
                'ENVIRONMENT',
                'TRANSACTION_TYPES',
                'LANGUAGE'
            ),
            array(
                'CHECKOUT_PAGE_TITLE',
                'TRANSACTION_TYPES',
                'LANGUAGE',
                'WPF_TOKENIZATION'
            )
        );

        return $keys;
    }

    /**
     * @param $transaction_type
     * @return string
     */
    private function getCustomParameterKey($transaction_type)
    {
        switch ($transaction_type) {
            case \Genesis\API\Constants\Transaction\Types::PPRO:
                $result = 'payment_method';
                break;
            case \Genesis\API\Constants\Transaction\Types::PAY_PAL:
                $result = 'payment_type';
                break;
            case \Genesis\API\Constants\Transaction\Types::GOOGLE_PAY:
                $result = 'payment_subtype';
                break;
            default:
                $result = 'unknown';
        }

        return $result;
    }
}
