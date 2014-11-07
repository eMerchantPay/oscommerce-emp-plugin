<?php
disableErrorOutput();

chdir('../../../../');

require 'includes/application_top.php';
require 'includes/modules/payment/genesis_php/vendor/autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConfig;

// Exit if the Payment Module is not enabled
if ( !defined('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_STATUS') || (MODULE_PAYMENT_EMERCHANTPAY_GENESIS_STATUS  != 'True') ) {
	exit(0);
}

// Notifications
if (isset($_POST['unique_id'])) {

	$notification = new \Genesis\API\Notification();

	$notification->parseNotification($_POST);

	eMerchantPay_Genesis_setCredentials();

	if ($notification->isAuthentic()) {
		$genesis = new Genesis('Reconcile\Transaction');

		$genesis
			->request()
				->setUniqueId($notification->getParsedNotification()->unique_id);

		$genesis->execute();

		$reconcile = $genesis->response()->getResponseObject();

		if (isset($reconcile->transaction_id)) {
			$orderQuery = tep_db_query("SELECT `orders_id`, `orders_status`, `currency`, `currency_value` FROM " . TABLE_ORDERS . " WHERE `cc_number` = '" . strval($reconcile->transaction_id) . "'");

			if (!tep_db_num_rows($orderQuery)) {
				exit;
			}

			$order = tep_db_fetch_array($orderQuery);

			$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ORDER_STATUS_ID'));

			if ($order['orders_status'] != $order_status_id) {
				// Update Order Status
				tep_db_query("UPDATE " . TABLE_ORDERS . " SET `orders_status` = '" . $order_status_id . "', `last_modified` = NOW(), `cc_type` = '', `cc_number` = '' WHERE `orders_id` = '" . intval($order['orders_id']) . "'");

				// Add Order Status History Entry
				$sql_data_array = array(
					'orders_id'         => $order['orders_id'],
					'orders_status_id'  => '0', //$order_status_id,
					'date_added'        => 'now()',
					'customer_notified' => '0',
					'comments'          =>
						sprintf(
							"3D Payment transaction has been processed!\nTransaction ID: %s\nStatus: %s\nResponse Code: %s",
							$reconcile->unique_id,
							$reconcile->status,
							$reconcile->response_code
						),
				);

				tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

				header('Content-type: application/xml');
				echo $notification->getEchoResponse();
			}
		}
	}

	exit(0);
}

function eMerchantPay_Genesis_setCredentials() {
	GenesisConfig::setUsername(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_USERNAME')
	);
	GenesisConfig::setPassword(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_PASSWORD')
	);
	GenesisConfig::setToken(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_TOKEN')
	);

	switch(getConst('MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ENVIRONMENT')){
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

function disableErrorOutput() {
	// "Shhh. Be vewy vewy quiet, I'm hunting wabbits"
	ini_set('display_errors', 'Off');
	error_reporting(0);
}