<?php
/**
 * eMerchantPay Checkout
 *
 * Contains eMerchantPay Checkout Payment Logic
 *
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @copyright   2015 eMerchantPay Ltd.
 * @version     $Id:$
 * @since       1.0.0
 */
require DIR_FS_CATALOG . '/includes/apps/emerchantpay/libs/genesis_php/vendor/autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConfig;

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
	 * Store the order GLOBAL variable as private
	 *
	 * @var array|null|order
	 */
	private $order;

	// class constructor
	function emerchantpay_checkout() {
		global $order;

		$this->code 		= 'emerchantpay_checkout';

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

	function selection() {
		return array(
			'id'        => $this->code,
			'module'    => $this->title
		);
	}

	function checkout_initialization_method() {
		/*
		global $cart;

		$image_button = tep_href_link('ext/modules/payment/emerchantpay/images/checkout.png', '', 'SSL');

		$string = '<a href="' . tep_href_link('ext/modules/payment/emerchantpay/checkout.php', '', 'SSL') . '"><img src="' . $image_button . '" border="0" alt="" title="' . tep_output_string_protected($this->getConst("MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TEXT_PUBLIC_TITLE")) . '" /></a>';

		return $string;
		*/
		return false;
	}

	function pre_confirmation_check() {
		return false;
	}

	function confirmation()
	{
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

	function get_error() {
		return array(
			'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ERROR_TITLE'),
			'error' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ERROR_DESC')
		);
	}

	function after_process() {
		global $insert_id, $cartID, $order, $currency;

		// Update the ORDER, for total
		$this->order = $order;

		$transaction_id = sprintf('%s-%s', $insert_id, md5($cartID . microtime(true)));

		$description = '';

		foreach ($order->products as $product) {
			$separator = ($product == end($order->products)) ? '' : ' | ';

			$description .= $product['qty'] . ' x ' . $product['name'] . $separator;
		}

		$currency = isset($currency) ? $currency : $this->order->info['currency'];

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
			$genesis = new Genesis('WPF\Create');

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
					->addTransactionType($this->getTransCode());

			$genesis->execute();

			$response = $genesis->response()->getResponseObject();

			// Redirect on Failure
			if ($genesis->response()->isSuccessful()) {
				tep_redirect(strval($response->redirect_url));
			}
			else {
				tep_redirect(
					tep_href_link(
						FILENAME_CHECKOUT_PAYMENT,
						'payment_error=' . $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ERROR_DESC'),
						'SSL'
					)
				);
			}
		}
		catch (Exception $e) {
			die($e->getMessage());
		}
	}

	function output_error() {
		return false;
	}

	function setCredentials() {
		GenesisConfig::setUsername(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME')
		);
		GenesisConfig::setPassword(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD')
		);
		GenesisConfig::setToken(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TOKEN')
		);

		switch($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT')){
			default:
			case 'Staging':
				GenesisConfig::setEnvironment('sandbox');
				break;
			case 'Production':
				GenesisConfig::setEnvironment('production');
				break;
		}
	}

	function getConst($var) {
		return defined($var) ? constant($var) : '';
	}

	function getTransCode() {
		$type = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TRANSACTION_TYPE');

		switch($type) {
			case 'Authorize':
				return 'authorize';
				break;
			case 'Authorize3D':
				return 'authorize3d';
				break;
			case 'Sale':
				return 'sale';
				break;
			case 'Sale3D':
				return 'sale3d';
				break;
		}

		return false;
	}

	function check() {
		if (!isset($this->_check)) {
			$check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}

		return $this->_check;
	}

	function install() {
		// Delete any previous leftovers
		$this->remove();

		// Insert our custom statuses
		foreach ($this->statuses() as $status) {
			$this->updateStatuses($status);
		}

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('EnableEnable eMerchantPay Checkout Module', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS', 'True', 'Do you want to accept DWS/ICPay payments?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Username', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME', 'Enter your Genesis Username here', 'The username of your Genesis account', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Password', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD', 'Enter your Genesis Password here', 'The password of your Genesis account', '6', '4', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT', 'True', 'If enabled your transaction are redirected to your Staging Server, NO MONEY ARE BEING TRANSFERED', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Type', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_TRANSACTION_TYPE', 'sale', 'What transaction type should we use upon purchase?.', '6', '0', 'tep_cfg_select_option(array(\'Sale\', \'Sale3D\'), ', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Default Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ORDER_STATUS_ID', '0', 'Set the default status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Failed Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID', '0', 'Set the status of failed orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Processed Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PROCESSED_ORDER_STATUS_ID', '0', 'Set the status of processed orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
	}

	function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

	function updateStatuses($status_name) {
		$status_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = '" . $status_name . "' limit 1");

		if (tep_db_num_rows($status_query) < 1) {
			$status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
			$status = tep_db_fetch_array($status_query);

			$status_id = $status['status_id'] + 1;

			$languages = tep_get_languages();

			foreach ($languages as $lang) {
				tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', '" . $status_name . "')");
			}

			$flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");
			if (tep_db_num_rows($flags_query) == 1) {
				tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
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
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_SORT_ORDER',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ZONE',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ORDER_STATUS_ID',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID',
			'MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PROCESSED_ORDER_STATUS_ID'
		);
	}

	function statuses() {
		return array(
			'Failed [eMerchantPay]',
			'Processed [eMerchantPay]'
		);
	}
}