<?php
/**
 * eMerchantPay Checkout
 *
 * Contains eMerchantPay Checkout Payment Logic
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2015 eMerchantPay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

/**
 * eMerchantPay Checkout
 *
 * Main class, instantiated by osCommerce providing
 * necessary methods to facilitate payments through
 * eMerchantPay's Payment Gateway
 */
class emerchantpay_checkout
{
    /**
     * Is this module Enabled?
     *
     * @var bool|mixed
     */
    public $enabled = false;

    /**
     * Payment method code
     *
     * @var string
     */
    public $code            = null;

    /**
     * Payment method title
     *
     * @var string
     */
    public $title           = null;

    /**
     * Payment method (customer) title
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
    private $order;

    function __construct()
    {
        require DIR_FS_CATALOG . '/includes/apps/emerchantpay/libs/genesis/vendor/autoload.php';

        $this->init();
    }

	// class constructor
	function init() {
		global $order;

		$this->signature 	= "emerchantpay|emerchantpay_checkout|1.2.4";
		$this->code 		= "emerchantpay_checkout";
		$this->api_version 	= "1.4";

		$this->title 		= $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TEXT_TITLE');
		$this->public_title = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TEXT_PUBLIC_TITLE');
		$this->description 	= $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TEXT_DESCRIPTION');
		$this->sort_order 	= $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_SORT_ORDER');

		$this->enabled 		= filter_var($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS'), FILTER_VALIDATE_BOOLEAN);

		$this->order        = $order;

		if ( isset($this->order) && is_object($this->order) ) {
			$this->update_status();
		}

		if (intval($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_ORDER_STATUS_ID')) > 0) {
			$this->order_status = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_ORDER_STATUS_ID');
		}

		// Set the Gateway Credentials
		$this->setCredentials();
	}

	// class methods
	function update_status()
	{
		if ( ($this->enabled == true) && (intval($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_ZONE')) > 0) ) {
			$check_flag = false;

			$check_query = tep_db_query("SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES . " WHERE geo_zone_id = '" . $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_ZONE') . "' AND zone_country_id = '" . $this->order->billing['country']['id'] . "' ORDER BY zone_id");

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

	function javascript_validation() {
		return false;
	}

	function selection()
    {
		return array(
			'id'    => $this->code,
			'module'=> $this->title
		);
	}

	function checkout_initialization_method()
    {
        return false;
	}

	function pre_confirmation_check()
    {
		return false;
	}

	function confirmation()
	{
        ?>
        <script type="text/javascript">
            $(document).ready(function () {
                $("form").on('submit', function() {
                    $('#tdb5').button("disable").prop('disabled', true);
                });
            });
        </script>

        <p style="text-align:center;">
            <?php echo $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TEXT_REDIRECT_WARNING'); ?>
        </p>
        <?php

		return false;
	}

	function process_button()
	{
		return false;
	}

	function before_process()
	{
		return false;
	}

    function output_error()
    {
        return false;
    }

	function get_error()
    {
		return array(
			'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ERROR_TITLE'),
			'error' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ERROR_DESC')
		);
	}

	function after_process()
    {
		global $insert_id, $cartID, $order, $currency;

		// Update the ORDER, for total
		$this->order = $order;

		$transaction_id = sprintf('%s-%s', $insert_id, md5($cartID . microtime(true)));

		$description = '';

		foreach ($order->products as $product) {
			$separator = ($product == end($order->products)) ? '' : PHP_EOL;

			$description .= $product['qty'] . ' x ' . $product['name'] . $separator;
		}

		$currency = isset($currency) ? $currency : $this->order->info['currency'];

        $language_id = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_LANGUAGE')
            ? $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_LANGUAGE')
            : 'en';

		$urls = array(
			'notification'   =>
				tep_href_link('ext/modules/payment/emerchantpay/checkout.php', '', 'SSL'),
			'return_success' =>
				tep_href_link('ext/modules/payment/emerchantpay/redirect.php', 'return=success', 'SSL'),
			'return_failure' =>
				tep_href_link('ext/modules/payment/emerchantpay/redirect.php', 'return=failure', 'SSL'),
			'return_cancel' =>
				tep_href_link('ext/modules/payment/emerchantpay/redirect.php', 'return=cancel', 'SSL'),
		);

		try {
			$genesis = new \Genesis\Genesis('WPF\Create');

			$genesis
				->request()
					->setTransactionId($transaction_id)
					->setUsage('osCommerce Electronic Transaction')
					->setDescription($description)
					->setNotificationUrl($urls['notification'])
					->setReturnSuccessUrl($urls['return_success'])
					->setReturnFailureUrl($urls['return_failure'])
					->setReturnCancelUrl($urls['return_cancel'])
					->setCurrency($currency)
					->setAmount($this->order->info['total'])
					->setCustomerEmail($this->order->customer['email_address'])
					->setCustomerPhone($this->order->customer['telephone'])
					->setBillingFirstName($this->order->billing['firstname'])
					->setBillingLastName($this->order->billing['lastname'])
					->setBillingAddress1($this->order->billing['street_address'])
					->setBillingZipCode($this->order->billing['postcode'])
					->setBillingCity($this->order->billing['city'])
					->setBillingCountry($this->order->billing['country']['iso_code_2'])
					->setShippingFirstName($this->order->delivery['firstname'])
					->setShippingLastName($this->order->delivery['lastname'])
					->setShippingAddress1($this->order->delivery['street_address'])
					->setShippingZipCode($this->order->delivery['postcode'])
					->setShippingCity($this->order->delivery['city'])
					->setShippingCountry($this->order->delivery['country']['iso_code_2'])
                    ->setLanguage($language_id);

            foreach ($this->get_checkout_transaction_types() as $type) {
                if (is_array($type)) {
                    $genesis
                        ->request()
                            ->addTransactionType($type['name'], $type['parameters']);
                } else {
                    $genesis
                        ->request()
                            ->addTransactionType($type);
                }
            }

			$genesis->execute();

            tep_redirect($genesis->response()->getResponseObject()->redirect_url);
		}
		catch (\Exception $e) {
            tep_redirect(
                tep_href_link(
                    FILENAME_CHECKOUT_PAYMENT , 'payment_error=' . get_class($this),
                    'SSL'
                )
            );
		}
	}

	function setCredentials()
    {
        \Genesis\Config::setEndpoint(
            \Genesis\API\Constants\Endpoints::EMERCHANTPAY
        );

		\Genesis\Config::setUsername(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME')
		);
		\Genesis\Config::setPassword(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD')
		);

		switch($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT')){
			default:
			case 'Staging':
				\Genesis\Config::setEnvironment(
					\Genesis\API\Constants\Environments::STAGING
				);
				break;
			case 'Production':
				\Genesis\Config::setEnvironment(
					\Genesis\API\Constants\Environments::PRODUCTION
				);
				break;
		}
	}

	function getConst($var)
    {
		return defined($var) ? constant($var) : '';
	}

    function get_checkout_transaction_types()
    {
        $processed_list = array();

        $selected_types = array_filter(
            explode(',', $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TRANSACTION_TYPE'))
        );

        $alias_map = array(
            \Genesis\API\Constants\Payment\Methods::EPS         =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY    =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::QIWI        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TELEINGRESO =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY   =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
        );

        foreach ($selected_types as $selected_type) {
            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $processed_list[$transaction_type]['parameters'][] = array(
                    'payment_method' => $selected_type
                );
            } else {
                $processed_list[] = $selected_type;
            }
        }

        return $processed_list;
    }

	function check() {
		if (!isset($this->_check)) {
			$check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}

		return $this->_check;
	}

	function install()
    {
		// Delete any previous leftovers
		$this->remove();

		// Insert our custom statuses
		foreach ($this->statuses() as $status) {
			$this->updateStatuses($status);
		}

        $transaction_types = array(
            \Genesis\API\Constants\Transaction\Types::ABNIDEAL,
            \Genesis\API\Constants\Transaction\Types::CASHU,
            \Genesis\API\Constants\Payment\Methods::EPS,
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY,
            \Genesis\API\Constants\Transaction\Types::NETELLER,
            \Genesis\API\Constants\Payment\Methods::QIWI,
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE,
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_YEEPAY,
            \Genesis\API\Constants\Transaction\Types::PAYSAFECARD,
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24,
            \Genesis\API\Constants\Transaction\Types::POLI,
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY,
            \Genesis\API\Constants\Transaction\Types::SALE,
            \Genesis\API\Constants\Transaction\Types::SALE_3D,
            \Genesis\API\Constants\Transaction\Types::SOFORT,
            \Genesis\API\Constants\Payment\Methods::TELEINGRESO,
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY,
            \Genesis\API\Constants\Transaction\Types::WEBMONEY
        );

        $languages = array(
            'en', 'it', 'es', 'fr', 'de', 'ja', 'zh', 'ar', 'pt', 'tr', 'ru', 'hi', 'bg'
        );

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('EnableEnable eMerchantPay Checkout Module', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS', 'True', 'Do you want to process payments via eMerchantPays Genesis Gateway?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Username', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME', 'Enter your Genesis Username here', '', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Password', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD', 'Enter your Genesis Password here', '', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT', 'True', 'If enabled, transactions are going through our Staging (Test) server, NO MONEY ARE BEING TRANSFERRED', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Type', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TRANSACTION_TYPE', 'sale', 'What transaction type should we use upon purchase?.', '6', '0', 'tep_cfg_select_option(array(\'" . implode("\',\'", $transaction_types) . "\'),', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Checkout Page Language', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_LANGUAGE', 'en', 'What language (localization) should we have on the Checkout?.', '6', '0', 'tep_cfg_select_option(array(\'" . implode("\',\'", $languages) . "\'),', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Default Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ORDER_STATUS_ID', '1', 'Set the default status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Failed Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID', '1', 'Set the status of failed orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Processed Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PROCESSED_ORDER_STATUS_ID', '2', 'Set the status of processed orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
	}

	function remove()
    {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function updateStatuses($status_name)
    {
		$status_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = '" . $status_name . "' limit 1");

		if (tep_db_num_rows($status_query) < 1) {
			$status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
			$status = tep_db_fetch_array($status_query);

			$status_id = $status['status_id'] + 1;

			$languages = tep_get_languages();

			foreach ($languages as $lang) {
				tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name, public_flag) values ('" . $status_id . "', '" . $lang['id'] . "', '" . $status_name . "', '1')");
			}
		} else {
			$check = tep_db_fetch_array($status_query);

			$status_id = $check['orders_status_id'];
		}

		return $status_id;
	}

	function keys() {
		return array(
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TRANSACTION_TYPE',
            'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_LANGUAGE',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_SORT_ORDER',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ZONE',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ORDER_STATUS_ID',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PROCESSED_ORDER_STATUS_ID'
		);
	}

	function statuses() {
		return array(
			'Payment failed',
			'Payment complete'
		);
	}
}

