<?php
/**
 * emerchantpay Direct
 *
 * Contains emerchantpay Direct Payment Logic
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2018 emerchantpay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

/**
 * emerchantpay Direct
 *
 * Main class, instantiated by emerchantpay providing
 * necessary methods to facilitate payments through
 * emerchantpay's Payment Gateway
 */

use Genesis\API\Constants\Transaction\Types;

if (!class_exists('emerchantpay_method_base')) {
    require_once DIR_FS_CATALOG . 'ext/modules/payment/emerchantpay/method_base.php';
}

/**
 * Class emerchantpay_direct
 */
class emerchantpay_direct extends emerchantpay_method_base
{
    /**
     * Credit Card Type
     * @var null|string
     */
    protected $cc_card_type = null;
    /**
     * Credit Card Number
     * @var null|string
     */
    protected $cc_card_number = null;
    /**
     * Credit Card Expiry Month
     * @var null|string
     */
    protected $cc_expiry_month = null;
    /**
     * Credit Card Expiry Year
     * @var null|string
     */
    protected $cc_expiry_year = null;

    /**
     * emerchantpay_direct constructor.
     */
    public function __construct()
    {
        $this->code = static::EMERCHANTPAY_DIRECT_METHOD_CODE;
        parent::__construct();
    }

    /**
     * Get If Module Requires SSL or not
     * @return bool
     */
    protected function getModuleRequiresSSL()
    {
        return true;
    }

    /**
     * Get Transactions Table name for the Payment Module
     * @return string
     */
    protected function getTableNameTransactions()
    {
        return static::EMERCHANTPAY_DIRECT_TRANSACTIONS_TABLE_NAME;
    }

    /**
     * Determines if the Module Admin Settings are properly configured
     * @return bool
     */
    protected function getIsConfigured()
    {
        return
            parent::getIsConfigured() &&
            !empty($this->getSetting('TOKEN')) &&
            !empty($this->getSetting('TRANSACTION_TYPE'));
    }

    /**
     * Confirmation Check mothod for Checkout Confirmation Page
     * @return bool
     */
    function confirmation()
    {
        global $order;

        $expires_month = array();
        $expires_year  = array();

        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array(
                'id'   => sprintf('%02d', $i),
                'text' => sprintf('%02d', $i)
            );
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
            $expires_year[] = array(
                'id'   => strftime('%y', mktime(0, 0, 0, 1, 1, $i)),
                'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
            );
        }

        $confirmation = array(
            'fields' => array(
                array(
                    'title' =>
                        $this->getSetting('TEXT_CREDIT_CARD_OWNER'),
                    'field' =>
                        tep_draw_input_field(
                            'cc_owner',
                            sprintf(
                                '%s %s',
                                $order->billing['firstname'],
                                $order->billing['lastname']
                            ),
                            'size="30" maxlength="255"'
                        )
                ),
                array(
                    'title' =>
                        $this->getSetting('TEXT_CREDIT_CARD_NUMBER'),
                    'field' =>
                        tep_draw_input_field(
                            'cc_number',
                            '',
                            'size="30" maxlength="16" minlength="16"'
                        )
                ),
                array(
                    'title' =>
                        $this->getSetting('TEXT_CREDIT_CARD_EXPIRES'),
                    'field' =>
                        tep_draw_pull_down_menu(
                            'cc_expiry_month',
                            $expires_month
                        ) .
                        '&nbsp;' .
                        tep_draw_pull_down_menu(
                            'cc_expiry_year',
                            $expires_year
                        )
                ),
                array(
                    'title' =>
                        $this->getSetting('TEXT_CVV'),
                    'field' =>
                        tep_draw_input_field(
                            'cc_cvv',
                            '',
                            'size="5" maxlength="4"'
                        )
                )
            )
        );

        return $confirmation;
    }

    /**
     * Evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
     *
     */
    public function validateCreditCardInfo($requestData)
    {
        if (!class_exists('cc_validation')) {
            include(DIR_WS_CLASSES . 'cc_validation.php');
        }

        $cc_validation = new cc_validation();
        $cc_validation->validate(
            $requestData['cc_number'],
            $requestData['cc_expiry_month'],
            $requestData['cc_expiry_year']
        );

        $this->cc_card_type    = $cc_validation->cc_type;
        $this->cc_card_number  = $cc_validation->cc_number;
        $this->cc_expiry_month = $cc_validation->cc_expiry_month;
        $this->cc_expiry_year  = $cc_validation->cc_expiry_year;

        return true;
    }

    /**
     * Process Request to the Gateway
     * @return bool
     */
    protected function doBeforeProcessPayment()
    {
        global $HTTP_POST_VARS, $customer_id, $order, $messageStack, $sendto, $currency, $response;

        $this->validateCreditCardInfo($HTTP_POST_VARS);

        $data                   = new stdClass();
        $data->transaction_id   = $this->getGeneratedTransactionId();
        $data->transaction_type = $this->getSetting('TRANSACTION_TYPE');
        $data->description      = '';

        $order->info['cc_type']    = $this->cc_card_type;
        $order->info['cc_owner']   = $HTTP_POST_VARS['cc_owner'];
        $order->info['cc_number']  = $this->cc_card_number;
        $order->info['cc_expires'] = $this->cc_expiry_year;
        $order->info['cc_cvv']     = '***';

        $data->card_info = array(
            'cc_owner'        => $HTTP_POST_VARS['cc_owner'],
            'cc_number'       => $this->cc_card_number,
            'cc_expiry_month' => $this->cc_expiry_month,
            'cc_expiry_year'  => $this->cc_expiry_year,
            'cc_cvv'          => $HTTP_POST_VARS['cc_cvv']
        );

        foreach ($order->products as $product) {
            $separator = ($product == end($order->products)) ? '' : PHP_EOL;

            $data->description .= $product['qty'] . ' x ' . $product['name'] . $separator;
        }

        $data->currency = $order->info['currency'];

        if (self::isAsyncTransaction($data->transaction_type)) {
            $data->urls = array(
                'notification'   =>
                    $this->getNotificationUrl(),
                'return_success' =>
                    $this->getReturnUrl(self::ACTION_SUCCESS),
                'return_failure' =>
                    $this->getReturnUrl(self::ACTION_FAILURE)
            );
        }

        $data->order = $order;

        $errorMessage = null;

        try {
            $this->responseObject = $this->pay($data);
        } catch (\Genesis\Exceptions\ErrorAPI $api) {
            $errorMessage         = $api->getMessage();
            $this->responseObject = null;
        } catch (\Genesis\Exceptions\ErrorNetwork $e) {
            $errorMessage         = MODULE_PAYMENT_EMERCHANTPAY_DIRECT_ERROR_CHECK_CREDENTIALS .
                                    PHP_EOL .
                                    $e->getMessage();
            $this->responseObject = null;
        } catch (\Exception $e) {
            $errorMessage         = $e->getMessage();
            $this->responseObject = null;
        }

        if (empty($this->responseObject)) {
            if (!empty($errorMessage)) {
                $messageStack->add_session($errorMessage, 'error');
            }
            tep_redirect(
                tep_href_link(
                    FILENAME_CHECKOUT_PAYMENT,
                    'payment_error=' . get_class($this),
                    'SSL'
                )
            );
        } elseif ($this->responseObject->status == 'declined') {
            $messageStack->add_session($this->responseObject->message, 'error');
            tep_redirect(
                tep_href_link(
                    FILENAME_CHECKOUT_PAYMENT,
                    'payment_error=' . get_class($this),
                    'SSL'
                )
            );
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
        $params = array(
            'transaction_id'      => $data->transaction_id,
            'remote_ip'           => $this->getServerRemoteAddress(),
            'usage'               => 'osCommerce Electronic Transaction',
            'currency'            => $data->currency,
            'amount'              => $data->order->info['total'],
            'card_holder'         => $data->card_info['cc_owner'],
            'card_number'         => $data->card_info['cc_number'],
            'expiration_year'     => $data->card_info['cc_expiry_year'],
            'expiration_month'    => $data->card_info['cc_expiry_month'],
            'cvv'                 => $data->card_info['cc_cvv'],
            'customer_email'      => $data->order->customer['email_address'],
            'customer_phone'      => $data->order->customer['telephone'],
            'billing_first_name'  => $data->order->billing['firstname'],
            'billing_last_name'   => $data->order->billing['lastname'],
            'billing_address1'    => $data->order->billing['street_address'],
            'billing_zip_code'    => $data->order->billing['postcode'],
            'billing_city'        => $data->order->billing['city'],
            'billing_state'       => $this->getStateCode($data->order->billing),
            'billing_country'     => $data->order->billing['country']['iso_code_2'],
            'shipping_first_name' => $data->order->delivery['firstname'],
            'shipping_last_name'  => $data->order->delivery['lastname'],
            'shipping_address1'   => $data->order->delivery['street_address'],
            'shipping_zip_code'   => $data->order->delivery['postcode'],
            'shipping_city'       => $data->order->delivery['city'],
            'shipping_state'      => $this->getStateCode($data->order->delivery),
            'shipping_country'    => $data->order->delivery['country']['iso_code_2'],
        );
        if (isset($data->urls)) {
            $params['notification_url']   = $data->urls['notification'];
            $params['return_success_url'] = $data->urls['return_success'];
            $params['return_failure_url'] = $data->urls['return_failure'];
        }

        $genesis = \Genesis\Genesis::financialFactory($data->transaction_type, $params);
        $genesis->execute();

        return $genesis->response()->getResponseObject();
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
        }

        return false;
    }

    /**
     * Set the needed Configuration for the Genesisi Gateway Client
     * @return void
     * @throws \Genesis\Exceptions\InvalidArgument
     */
    function setCredentials()
    {
        parent::setCredentials();

        \Genesis\Config::setToken(
            $this->getSetting('TOKEN')
        );
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
                'Pay safely with emerchantpay Direct',
                'This name will be displayed on the checkout page',
                '6',
                '10',
                'emp_zfg_draw_input(null, ',
                null
            ),
            array(
                'Genesis API Token',
                $this->getSettingKey('TOKEN'),
                '',
                'Enter your Token, required for accessing the Genesis Gateway',
                '6',
                '40',
                "emp_zfg_draw_input({$this->requiredOptionsAttributes}, ",
                null
            ),
            array(
                'Transaction Type',
                $this->getSettingKey('TRANSACTION_TYPE'),
                Types::SALE,
                'What transaction type should we use upon purchase?.',
                '6',
                '60',
                "emp_zfg_select_drop_down_single_from_object(\"{$this->code}\",\"getConfigTransactionTypesOptions\", ",
                null
            )
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
        $transactionTypes = array(
            Types::AUTHORIZE    => 'Authorize',
            Types::AUTHORIZE_3D => 'Authorize3D',
            Types::SALE         => 'Sale',
            Types::SALE_3D      => 'Sale 3D'
        );

        return $this->buildSettingsDropDownOptions(
            $transactionTypes
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
                'PASSWORD',
                'ENVIRONMENT'
            ),
            array(
                'CHECKOUT_PAGE_TITLE',
                'TOKEN',
                'TRANSACTION_TYPE'
            )
        );

        return $keys;
    }
}
