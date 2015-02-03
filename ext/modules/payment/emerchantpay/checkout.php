<?php
/**
 * eMerchantPay Checkout Notifications
 *
 * Server notifications handler
 *
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @copyright   2015 eMerchantPay Ltd.
 * @version     $Id:$
 * @since       1.0.0
 */

// "Shhh. Be vewy vewy quiet, I'm hunting wabbits"
ini_set('display_errors', 'Off');
error_reporting(0);

chdir('../../../../');

require 'includes/application_top.php';
require 'includes/apps/emerchantpay/libs/genesis_php/vendor/autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConfig;

if ( !defined('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS') || (MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS  != 'True') ) {
	exit(0);
}

function setCredentials() {
	GenesisConfig::setUsername(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME')
	);
	GenesisConfig::setPassword(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD')
	);

	switch(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT')){
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

if (isset($_POST['wpf_unique_id'])) {
	$notification = new \Genesis\API\Notification();

	$notification->parseNotification($_POST);

	setCredentials();

	if ($notification->isAuthentic()) {
		$genesis = new Genesis('WPF\Reconcile');

		$genesis
			->request()
				->setUniqueId($notification->getParsedNotification()->wpf_unique_id);

		$genesis->execute();

		$reconcile = $genesis->response()->getResponseObject();

		// Case 1: Customer completed transaction
		if (isset($reconcile->payment_transaction)) {
			$payment = $reconcile->payment_transaction;

			list($order_id, $order_hash) = explode('-', $payment->transaction_id);

			$orderQuery = tep_db_query("SELECT `orders_id`, `orders_status`, `currency`, `currency_value` FROM " . TABLE_ORDERS . " WHERE `orders_id` = '" . strval($order_id) . "'");

			if (!tep_db_num_rows($orderQuery)) {
				exit(0);
			}

			$order = tep_db_fetch_array($orderQuery);

			switch ($payment->status) {
				case 'approved':
					$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PROCESSED_ORDER_STATUS_ID'));
					break;
				case 'error':
				case 'declined':
					$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID'));
					break;
				default:
					$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ORDER_STATUS_ID'));
			}

			// Update Order Status
			tep_db_query("UPDATE " . TABLE_ORDERS . " SET `orders_status` = '" . $order_status_id . "', `last_modified` = NOW() WHERE `orders_id` = '" . strval($order['orders_id']) . "'");

			// Add Order Status History Entry
			$sql_data_array = array(
				'orders_id'         => $order['orders_id'],
				'orders_status_id'  => $order_status_id,
				'date_added'        => 'now()',
				'customer_notified' => '1',
				'comments'          =>
					sprintf(
						"[Payment Notification]" . PHP_EOL .
						"- Unique ID: %s" . PHP_EOL .
						"- Status: %s",
						$payment->unique_id,
						$payment->status
					),
			);

			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		}
		// Case 2: Customer hasn't completed any transaction
		else {
			list($order_id, $order_hash) = explode('-', $reconcile->transaction_id);

			$orderQuery = tep_db_query("SELECT `orders_id`, `orders_status`, `currency`, `currency_value` FROM " . TABLE_ORDERS . " WHERE `orders_id` = '" . strval($order_id) . "'");

			if (!tep_db_num_rows($orderQuery)) {
				exit(0);
			}

			$order = tep_db_fetch_array($orderQuery);

			$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID'));

			// Update Order Status
			tep_db_query("UPDATE " . TABLE_ORDERS . " SET `orders_status` = '" . $order_status_id . "', `last_modified` = NOW() WHERE `orders_id` = '" . strval($order['orders_id']) . "'");

			// Add Order Status History Entry
			$sql_data_array = array(
				'orders_id'         => $order['orders_id'],
				'orders_status_id'  => $order_status_id,
				'date_added'        => 'now()',
				'customer_notified' => '1',
				'comments'          =>
					sprintf(
						"[Payment Notification]" . PHP_EOL .
						"- Unique ID: %s" . PHP_EOL .
						"- Status: %s",
						$reconcile->unique_id,
						$reconcile->status
					),
			);

			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		}

		header('Content-type: application/xml');
		echo $notification->getEchoResponse();
	}
}

exit(0);
