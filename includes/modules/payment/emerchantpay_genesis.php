<?php

require 'genesis_php/vendor/autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConfig;

class emerchantpay_genesis
{
	/**
	 * Is this module Enabled?
	 *
	 * @var bool|mixed
	 */
    public $enabled = false;

	/**
	 * Store the Genesis Response for after_proces()
	 *
	 * @var stdClass
	 */
	private $response;

	/**
	 * Store the order GLOBAL inside this class
	 *
	 * Why? - Just in case something goes wrong
	 *
	 * @var array|null|order
	 */
	private $order;

	// class constructor
	function emerchantpay_genesis() {
		global $order;

		$this->code 		= 'emerchantpay_genesis';

		$this->title 		= $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TEXT_TITLE');
		$this->public_title = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TEXT_PUBLIC_TITLE');
		$this->description 	= $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TEXT_DESCRIPTION');
		$this->sort_order 	= $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_SORT_ORDER');

		$this->enabled 		= filter_var($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_STATUS'), FILTER_VALIDATE_BOOLEAN);

		$this->order        = $order;

		if ( isset($this->order) && is_object($this->order) ) {
			$this->update_status();
		}

		if (intval($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_ORDER_STATUS_ID')) > 0) {
			$this->order_status = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_ORDER_STATUS_ID');
		}

		// Set the Gateway Credentials
		$this->setCredentials();

		// Store the Genesis response
		$this->response = new stdClass();
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

    function pre_confirmation_check() {
		return false;
    }

    function confirmation()
    {
	    $expire = array();

		for ($i=1; $i<13; $i++) {
			$expire['months'][] = array('id' => sprintf('%02d', $i), 'text' => strftime('%B',mktime(0,0,0,$i,1,2000)));
		}

		$today = getdate();

		for ($i=$today['year']; $i < $today['year']+10; $i++) {
			$expire['years'][]  = array('id' => strftime('%Y',mktime(0,0,0,1,1,$i)), 'text' => strftime('%Y',mktime(0,0,0,1,1,$i)));
		}

		$confirmation = array(
			'fields' => array(
				array(
					'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_CREDIT_CARD_OWNER'),
					'field' => tep_draw_input_field('cc_owner_genesis', $this->order->billing['firstname'] . ' ' . $this->order->billing['lastname'])
				),
				array(
					'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_CREDIT_CARD_NUMBER'),
					'field' => tep_draw_input_field('cc_number_genesis', '', 'maxlength="16"')
				),
				array(
					'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_CREDIT_CARD_EXPIRES'),
					'field' => tep_draw_pull_down_menu('cc_expires_month_genesis', $expire['months']) . '&nbsp;' . tep_draw_pull_down_menu('cc_expires_year_genesis', $expire['years'])
				),
				array(
					'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_CREDIT_CARD_CVV'),
					'field' => tep_draw_input_field('cc_cvv_genesis', '', 'size="4" maxlength="4"')
				),
			),
		);

		// Since we're redirecting the user, its a good idea to Warn
	    // him prior to the redirect to a 3rd party website
	    if (in_array($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TRANSACTION_TYPE'), array('Authorize3D', 'Sale3D'))) {
			$confirmation['fields'][] = array(
				'title' => 'Note:',
				'field' => 'Upon submission, you will be redirect to a partner of your Bank, in order to verify your purchase via 3D'
			);
	    }

      return $confirmation;
    }

	function process_button() {
		return false;
	}

	/**
	 * Process the order
	 */
	function before_process()
	{
		global $order, $currency;

		// Update the ORDER, for total
		$this->order = $order;

		// Setters
		$transaction_id = md5($this->order->customer['email_address'] . $this->order->info['total'] . microtime(true));

		$currency 	= isset($currency) ? $currency : $this->order->info['currency'];

		$cc_owner 	= isset($_POST['cc_owner_genesis']) ? $_POST['cc_owner_genesis'] : $this->order->info['cc_owner'];
		$cc_num		= isset($_POST['cc_number_genesis']) ? $_POST['cc_number_genesis'] : $this->order->info['cc_number'];
		$cc_cvv		= $_POST['cc_cvv_genesis'];
		$cc_month	= $_POST['cc_expires_month_genesis'];
		$cc_year	= $_POST['cc_expires_year_genesis'];

		$ipAddress = tep_get_ip_address();

		if ( (ip2long($ipAddress) == -1) || !ip2long($ipAddress)) {
			$ipAddress = $_SERVER['REMOTE_ADDR'];
		}

		$urls = array(
			'notification'   =>
				tep_href_link('ext/modules/payment/emerchantpay/notifier.php', '', 'SSL'),
			'return_success' =>
				tep_href_link('ext/modules/payment/emerchantpay/redir.php', 'return=success', 'SSL'),
			'return_failure' =>
				tep_href_link('ext/modules/payment/emerchantpay/redir.php', 'return=failure', 'SSL'),
		);

		$genesis = null;

		try {
			switch($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TRANSACTION_TYPE')) {
				case 'Authorize':
					$genesis = new Genesis('Financial\Authorize');
					break;
				case 'Authorize3D':
					$genesis = new Genesis('Financial\Authorize3D');
					break;
				default:
				case 'Sale':
					$genesis = new Genesis('Financial\Sale');
					break;
				case 'Sale3D':
					$genesis = new Genesis('Financial\Sale3D');
					break;
			}

			$genesis
				->request()
			        ->setTransactionId($transaction_id)
			        ->setUsage('osCommerce Electronic Transaction')
			        ->setRemoteIp($ipAddress)
			        ->setCurrency($currency)
			        ->setAmount($this->order->info['total'])
			        ->setCardHolder($cc_owner)
			        ->setCardNumber($cc_num)
			        ->setCvv($cc_cvv)
			        ->setExpirationMonth($cc_month)
			        ->setExpirationYear($cc_year)
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
			        ->setShippingCountry($this->order->delivery['country']['iso_code_2']);

			if (in_array($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TRANSACTION_TYPE'), array('Authorize3D', 'Sale3D'))) {
				$genesis
					->request()
						->setNotificationUrl($urls['notification'])
						->setReturnSuccessUrl($urls['return_success'])
						->setReturnFailureUrl($urls['return_failure']);
						/*
							->setMpiCavv('CAVV')
							->setMpiEci('ECI')
							->setMpiXid('XID');
						*/
			}

			$genesis->execute();

			$response = $genesis->response()->getResponseObject();

			// Store trx information for later
			$this->response = $response;

			// Redirect on Failure
			if (isset($response->status) && !in_array($response->status, array('approved', 'pending', 'pending_async'))) {
				tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code, 'SSL'));
			}

			// We have to workaround the fact, that we can't get the
			// order_id inside before_process
			$this->order->info['cc_type']     = $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TEXT_PUBLIC_TITLE');
			$this->order->info['cc_number']   = $transaction_id;
		}
		catch (Exception $e) {
			die($e->getMessage());
		}
	}

	function get_error() {
		return array(
			'title' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ERROR_TITLE'),
            'error' => $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ERROR_DESC') . ' ' . $_SESSION['error']
        );
	}

	function after_process() {
		global $insert_id;

		// Redirect if this is 3D transaction
		if ( in_array( $this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TRANSACTION_TYPE'), array( 'Authorize3D', 'Sale3D' ) ) ) {
			if ( isset( $this->response->redirect_url ) ) {
				tep_redirect( $this->response->redirect_url );
			}
		}

		// Add Order note with details from the transaction
		if (isset($this->response->unique_id)) {
			$unique_id = $this->response->unique_id;
			$auth_code = $this->response->authorization_code;
			$resp_code = $this->response->response_code;

			// Add Transaction Details History Entry
			$sql_data_array = array(
				'orders_id'         => intval($insert_id),
				'orders_status_id'  => '0',
				'date_added'        => 'now()',
				'customer_notified' => '0',
				'comments'          =>
					sprintf("Payment transaction through Genesis is completed!\nTransaction ID: %s\nAuthorization Code: %s\nResponse Code: %s", $unique_id, $auth_code, $resp_code),
			);

			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		}
	}

	function setCredentials() {
		GenesisConfig::setUsername(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_USERNAME')
		);
		GenesisConfig::setPassword(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_PASSWORD')
		);
		GenesisConfig::setToken(
			$this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TOKEN')
		);

		switch($this->getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ENVIRONMENT')){
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

	function output_error() {
		return false;
	}

	function check() {
		if (!isset($this->_check)) {
			$check_query = tep_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_STATUS'");
			$this->_check = tep_db_num_rows($check_query);
		}

		return $this->_check;
	}

	function install() {
		$this->remove();

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('EnableEnable EMP Module', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_STATUS', 'True', 'Do you want to accept DWS/ICPay payments?', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Username', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_USERNAME', 'Enter your Genesis Username here', 'The username of your Genesis account', '6', '4', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Password', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_PASSWORD', 'Enter your Genesis Password here', 'The password of your Genesis account', '6', '4', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Genesis API Token', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TOKEN', 'Enter your Genesis Token here', 'The token of your Genesis account', '6', '4', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ENVIRONMENT', 'True', 'If enabled your transaction are redirected to your Staging Server, NO MONEY ARE BEING TRANSFERED', '6', '3', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");

		//tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Select Product Type', 'MODULE_PAYMENT_EMERCHANTPAY_PRODUCT_TYPE', 'True', 'Select one of the product types', '6', '3', 'tep_cfg_select_option(array(\'Digital\', \'Physical\'), ', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Type', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TRANSACTION_TYPE', 'sale', 'What transaction type should we use upon purchase?.', '6', '0', 'tep_cfg_select_option(array(\'Authorize\', \'Sale\', \'Sale3D\'), ', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");

		tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
	}

	function remove() {
		tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
	}

    function keys() {
		return array(
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_STATUS',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_USERNAME',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_PASSWORD',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TOKEN',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ENVIRONMENT',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TRANSACTION_TYPE',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_SORT_ORDER',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ZONE',
			'MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ORDER_STATUS_ID'
		);
 	}
}