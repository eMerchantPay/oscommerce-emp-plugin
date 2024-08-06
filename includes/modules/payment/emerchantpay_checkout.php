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

use Genesis\Api\Constants\Transaction\Parameters\PayByVouchers\CardTypes;
use Genesis\Api\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes;
use Genesis\Api\Constants\Transaction\Parameters\Threeds\V2\Control\ChallengeIndicators;
use Genesis\Api\Constants\Transaction\Parameters\ScaExemptions;
use Genesis\Api\Constants\Transaction\Types;
use Genesis\Api\Constants\Banks;
use Genesis\Utils\Common as CommonUtils;

if (!class_exists('emerchantpay_method_base')) {
    require_once DIR_FS_CATALOG . 'ext/modules/payment/emerchantpay/method_base.php';
}

if (!class_exists('emerchantpay_threeds')) {
    require_once DIR_FS_CATALOG . 'ext/modules/payment/emerchantpay/emerchantpay_threeds.php';
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
            $response = $this->pay($data);
            $this->responseObject = $response->getResponseObject();

            if (!$response->isSuccessful()) {
                $errorMessage = isset($this->responseObject->message)
                    ? $this->responseObject->message
                    : '';

                throw new \Exception($errorMessage);
            }

            if (isset($this->responseObject->consumer_id)) {
                $this->saveConsumerId($this->responseObject->consumer_id);
            }

            return true;
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
     * @return \Genesis\Api\Response
     * @throws Exception
     */
    protected function pay($data)
    {
        $genesis = new \Genesis\Genesis('Wpf\Create');

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

        if ($this->getBoolSetting('THREEDS_ALLOWED')) {
            $this->setThreedsData($genesis->request(), $data);
        }

        $this->setScaExemptionData($genesis->request(), $data);

        $genesis->execute();

        return $genesis->response();
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
        $state = new \Genesis\Api\Constants\Transaction\States($response->status);

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
            Types::IDEBIT_PAYIN        => array(
                'customer_account_id' => $userIdHash
            ),
            Types::INSTA_DEBIT_PAYIN   => array(
                'customer_account_id' => $userIdHash
            ),
            Types::TRUSTLY_SALE        => array(
                'user_id' => $userIdHash
            ),
            Types::KLARNA_AUTHORIZE    => emp_get_klarna_custom_param_items($data)->toArray(),
            Types::PAYSAFECARD         => array(
                'customer_id' => $userIdHash
            )
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

        $selected_types = static::orderCardTransactionTypes(
            array_map(
                'trim',
                explode(',', $this->getSetting('TRANSACTION_TYPES'))
            )
        );

        $selected_bank_codes = array_map(
            'trim',
            explode(',', $this->getSetting('BANK_CODES'))
        );

        $alias_map = [
            self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_AUTHORIZE =>
                Types::GOOGLE_PAY,
            self::GOOGLE_PAY_TRANSACTION_PREFIX . self::GOOGLE_PAY_PAYMENT_TYPE_SALE      =>
                Types::GOOGLE_PAY,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_AUTHORIZE         =>
                Types::PAY_PAL,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_SALE              =>
                Types::PAY_PAL,
            self::PAYPAL_TRANSACTION_PREFIX . self::PAYPAL_PAYMENT_TYPE_EXPRESS           =>
                Types::PAY_PAL,
            self::APPLE_PAY_TRANSACTION_PREFIX . self::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE   =>
                Types::APPLE_PAY,
            self::APPLE_PAY_TRANSACTION_PREFIX . self::APPLE_PAY_PAYMENT_TYPE_SALE        =>
                Types::APPLE_PAY,
        ];

        foreach ($selected_types as $selected_type) {
            if ($selected_type == Types::ONLINE_BANKING_PAYIN && CommonUtils::isValidArray($selected_bank_codes)) {
                $processed_list[$selected_type]['name']                     = $selected_type;
                $processed_list[$selected_type]['parameters']['bank_codes'] = array_map(
                    function ($value) {
                        return ['bank_code' => $value];
                    },
                    $selected_bank_codes
                );

                continue;
            }

            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $key = $this->getCustomParameterKey($transaction_type);

                $processed_list[$transaction_type]['parameters'][] = array(
                    $key => str_replace(
                        [
                            self::GOOGLE_PAY_TRANSACTION_PREFIX,
                            self::PAYPAL_TRANSACTION_PREFIX,
                            self::APPLE_PAY_TRANSACTION_PREFIX
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
                'What transaction type should we use upon purchase?',
                '6',
                '60',
                "emp_zfg_select_drop_down_multiple_from_object({$this->requiredOptionsAttributes}, \"{$this->code}\", \"getConfigTransactionTypesOptions\", ",
                null
            ),
            array(
                'Bank code(s) for Online banking',
                $this->getSettingKey('BANK_CODES'),
                '',
                'If Online banking is chosen as transaction type, here you can select Bank code(s).',
                '6',
                '62',
                "emp_zfg_select_drop_down_multiple_from_object(null, \"{$this->code}\", \"getConfigBankCodes\", ",
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
            array(
                "Enable 3DSv2",
                $this->getSettingKey('THREEDS_ALLOWED'),
                "true",
                "Enable 3DSv2 optional parameters",
                "6",
                "50",
                "emp_zfg_draw_toggle(",
                "emp_zfg_get_toggle_value"
            ),
            array(
                '3DSv2 Challenge',
                $this->getSettingKey('THREEDS_CHALLENGE_INDICATOR'),
                ChallengeIndicators::NO_PREFERENCE,
                'The value has weight and might impact the decision whether a challenge will be required' .
                ' for the transaction or not.',
                '6',
                '65',
                "emp_zfg_select_drop_down_single_from_object(\"{$this->code}\", \"getConfigChallengeIndicators\",",
                null
            ),
            array(
                'SCA Exemption',
                $this->getSettingKey('SCA_EXEMPTION'),
                ScaExemptions::EXEMPTION_LOW_RISK,
                'Exemption for the Strong Customer Authentication.',
                '6',
                '65',
                "emp_zfg_select_drop_down_single_from_object(\"{$this->code}\", \"getConfigScaExemption\",",
                null
            ),
            array(
                'Exemption Amount',
                $this->getSettingKey('SCA_EXEMPTION_AMOUNT'),
                '100',
                'Exemption Amount determinate if the SCA Exemption should be included in the request to the Gateway.',
                '6',
                '10',
                'emp_zfg_draw_input(null, ',
                null
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

        $transactionTypes = \Genesis\Api\Constants\Transaction\Types::getWPFTransactionTypes();
        $excludedTypes    = self::getRecurringTransactionTypes();

        // Exclude PPRO transaction. This is not standalone transaction type
        array_push($excludedTypes, \Genesis\Api\Constants\Transaction\Types::PPRO);

        // Exclude GooglePay transaction. In this way Google Pay Payment types will be introduced
        array_push($excludedTypes, \Genesis\Api\Constants\Transaction\Types::GOOGLE_PAY);

        // Exclude PayPal transaction.
        array_push($excludedTypes, \Genesis\Api\Constants\Transaction\Types::PAY_PAL);

        // Exclude Apple Pay transaction.
        array_push($excludedTypes, \Genesis\Api\Constants\Transaction\Types::APPLE_PAY);

        // Exclude Transaction Types
        $transactionTypes = array_diff($transactionTypes, $excludedTypes);

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

        $applePayTypes = array_map(
            function ($type) {
                return self::APPLE_PAY_TRANSACTION_PREFIX . $type;
            },
            [
                self::APPLE_PAY_PAYMENT_TYPE_AUTHORIZE,
                self::APPLE_PAY_PAYMENT_TYPE_SALE
            ]
        );

        $transactionTypes = array_merge(
            $transactionTypes,
            $googlePayTypes,
            $payPalTypes,
            $applePayTypes
        );
        asort($transactionTypes);

        foreach ($transactionTypes as $type) {
            $name = \Genesis\Api\Constants\Transaction\Types::isValidTransactionType($type) ?
                \Genesis\Api\Constants\Transaction\Names::getName($type) : strtoupper($type);

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

        $languages = \Genesis\Api\Constants\i18n::getAll();

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
     *
     * @return array
     */
    function keys()
    {
        $keys = parent::keys();

        $this->appendSettingKeys(
            $keys,
            array(
                'ENVIRONMENT',
                'CHECKOUT_PAGE_TITLE',
                'TRANSACTION_TYPES',
                'BANK_CODES',
                'LANGUAGE',
                'WPF_TOKENIZATION',
                'THREEDS_ALLOWED',
                'WPF_TOKENIZATION',
                'SCA_EXEMPTION',
            ),
            array(
                'CHECKOUT_PAGE_TITLE',
                'TRANSACTION_TYPES',
                'BANK_CODES',
                'LANGUAGE',
                'WPF_TOKENIZATION',
                'THREEDS_ALLOWED',
                'THREEDS_CHALLENGE_INDICATOR',
                'SCA_EXEMPTION',
                'SCA_EXEMPTION_AMOUNT',
            )
        );

        return $keys;
    }

    /**
     * Returns list of available Bank codes for Online banking
     *
     * @return array
     */
    public function getAvailableBankCodes()
    {
        return [
            Banks::CPI => 'Interac Combined Pay-in',
            Banks::BCT => 'Bancontact',
            Banks::BLK => 'BLIK',
            Banks::SE  => 'SPEI',
            Banks::PID => 'LatiPay'
        ];
    }

    /**
     * Returns array of available Bank codes, formatted for the settings form
     *
     * @return array
     */
    public function getConfigBankCodes()
    {
        return $this->buildSettingsDropDownOptions(
            $this->getAvailableBankCodes()
        );
    }

    /**
     * Builds a list of available challenge options
     *
     * @return array
     */
    public function getConfigChallengeIndicators()
    {
        $challengeIndicators = [
            ChallengeIndicators::NO_PREFERENCE          => 'No preference',
            ChallengeIndicators::NO_CHALLENGE_REQUESTED => 'No challenge requested',
            ChallengeIndicators::PREFERENCE             => 'Preference',
            ChallengeIndicators::MANDATE                => 'Mandate'
        ];

        return $this->buildSettingsDropDownOptions(
            $challengeIndicators
        );
    }

    /**
     * Builds a list of available SCA Exemptions
     *
     * @return array
     */
    public function getConfigScaExemption()
    {
        $sca_excemptions = [
            ScaExemptions::EXEMPTION_LOW_RISK  => 'Low risk',
            ScaExemptions::EXEMPTION_LOW_VALUE => 'Low value',
        ];

        return $this->buildSettingsDropDownOptions(
            $sca_excemptions
        );
    }

    /**
     * @param $transaction_type
     * @return string
     */
    private function getCustomParameterKey($transaction_type)
    {
        switch ($transaction_type) {
            case \Genesis\Api\Constants\Transaction\Types::PAY_PAL:
                $result = 'payment_type';
                break;
            case \Genesis\Api\Constants\Transaction\Types::GOOGLE_PAY:
            case \Genesis\Api\Constants\Transaction\Types::APPLE_PAY:
                $result = 'payment_subtype';
                break;
            default:
                $result = 'unknown';
        }

        return $result;
    }

    /**
     * Set 3DSv2 optional parameters
     *
     * @param object $request
     * @param object $data
     *
     * @return void
     */
    private function setThreedsData($request, $data)
    {
        /** @var \Genesis\Api\Request\Wpf\Create $request */
        global $customer_id;

        $customer_info                    = emerchantpay_threeds::getCustomerInfo($customer_id);
        $customer_orders                  = emerchantpay_threeds::getCustomerOrders($customer_id);
        $orders_for_a_period              = emerchantpay_threeds::findNumberOfOrdersForaPeriod($customer_orders);
        $isVirtualCart                    = $data->order->content_type == 'virtual';

        $request
            ->setThreedsV2ControlChallengeIndicator($this->getSetting('THREEDS_CHALLENGE_INDICATOR'))
            ->setThreedsV2PurchaseCategory(emerchantpay_threeds::getThreedsPurchaseCategory($isVirtualCart))
            ->setThreedsV2MerchantRiskDeliveryTimeframe(
                emerchantpay_threeds::getThreedsDeliveryTimeframe($isVirtualCart)
            )
            ->setThreedsV2MerchantRiskShippingIndicator(emerchantpay_threeds::getShippingIndicator($data))
            ->setThreedsV2MerchantRiskReorderItemsIndicator(
                emerchantpay_threeds::getReorderItemsIndicator($customer_id, $data->order->products)
            )
            ->setThreedsV2CardHolderAccountCreationDate($customer_info['date_account_created'])
            ->setThreedsV2CardHolderAccountPasswordChangeDate($customer_info['date_account_last_modified'])
            ->setThreedsV2CardHolderAccountPasswordChangeIndicator(
                emerchantpay_threeds::getPasswordChangeIndicator($customer_info['date_account_last_modified'])
            )
            ->setThreedsV2CardHolderAccountLastChangeDate($customer_info['date_account_last_modified'])
            ->setThreedsV2CardHolderAccountUpdateIndicator(emerchantpay_threeds::getUpdateIndicator($customer_info))
            ->setThreedsV2CardHolderAccountRegistrationDate(
                emerchantpay_threeds::findFirstCustomerOrderDate($customer_orders)
            )
            ->setThreedsV2CardHolderAccountRegistrationIndicator(
                emerchantpay_threeds::getRegistrationIndicator($customer_orders)
            )
            ->setThreedsV2CardHolderAccountTransactionsActivityLast24Hours($orders_for_a_period['last_24h'])
            ->setThreedsV2CardHolderAccountTransactionsActivityPreviousYear($orders_for_a_period['last_year'])
            ->setThreedsV2CardHolderAccountPurchasesCountLast6Months($orders_for_a_period['last_6m'])
        ;

        if (!$isVirtualCart) {
            $shipping_address_date_first_used = emerchantpay_threeds::findShippingAddressDateFirstUsed(
                $data->order->delivery,
                $customer_orders
            );

            $request
                ->setThreedsV2CardHolderAccountShippingAddressDateFirstUsed($shipping_address_date_first_used)
                ->setThreedsV2CardHolderAccountShippingAddressUsageIndicator(
                    emerchantpay_threeds::getShippingAddressUsageIndicator($shipping_address_date_first_used)
                );
        }
    }

    /**
     * Set SCA Exemption parameter
     *
     * @param $request
     *
     * @return void
     */
    private function setScaExemptionData($request)
    {
        /** @var \Genesis\Api\Request\Wpf\Create $request */

        $wpf_amount = (float)$request->getAmount();
        if ($wpf_amount <= $this->getSetting('SCA_EXEMPTION_AMOUNT')) {
            $request->setScaExemption($this->getSetting('SCA_EXEMPTION'));
        }
    }


    /**
     * Order transaction types with Card Transaction types in front
     *
     * @param array $selectedTypes Selected transaction types
     *
     * @return array
     */
    private static function orderCardTransactionTypes($selectedTypes)
    {
        $order = \Genesis\Api\Constants\Transaction\Types::getCardTransactionTypes();

        asort($selectedTypes);

        $sortedArray = array_intersect($selectedTypes, $order);

        return array_merge(
            $sortedArray,
            array_diff($selectedTypes, $sortedArray)
        );
    }
}
