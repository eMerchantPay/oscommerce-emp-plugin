<?php
/**
 * eMerchantPay Checkout Notifications
 *
 * Server notifications handler
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2015 eMerchantPay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

ini_set('display_errors', 'Off');
error_reporting(0);

chdir('../../../../');

require 'includes/application_top.php';
require 'includes/apps/emerchantpay/libs/genesis/vendor/autoload.php';

if ( !defined('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS') || (MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_STATUS  != 'True') ) {
	exit(0);
}

function setCredentials()
{
    \Genesis\Config::setEndpoint(
        \Genesis\API\Constants\Endpoints::EMERCHANTPAY
    );

	\Genesis\Config::setUsername(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_USERNAME')
	);
	\Genesis\Config::setPassword(
		getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PASSWORD')
	);

    switch(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ENVIRONMENT')){
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

function getConst($var) {
	return defined($var) ? constant($var) : '';
}

if (isset($_POST['wpf_unique_id'])) {

    setCredentials();

	$notification = new \Genesis\API\Notification($_POST);

	if ($notification->isAuthentic()) {
		$notification->initReconciliation();

		$reconcile = $notification->getReconciliationObject();

		if (isset($reconcile->payment_transaction)) {
			$payment = $reconcile->payment_transaction;

			list($order_id, $order_hash) = explode('-', $payment->transaction_id);

			$orderQuery = tep_db_query("SELECT `orders_id`, `orders_status`, `currency`, `currency_value` FROM " . TABLE_ORDERS . " WHERE `orders_id` = '" . abs(intval($order_id)) . "'");

			if (!tep_db_num_rows($orderQuery)) {
				exit(0);
			}

			$order = tep_db_fetch_array($orderQuery);

			switch ($payment->status) {
				case \Genesis\API\Constants\Transaction\States::APPROVED:
					$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_PROCESSED_ORDER_STATUS_ID'));
					break;
				case \Genesis\API\Constants\Transaction\States::ERROR:
				case \Genesis\API\Constants\Transaction\States::DECLINED:
					$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID'));
					break;
				default:
					$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ORDER_STATUS_ID'));
			}

			// Update Order Status
			tep_db_query("UPDATE " . TABLE_ORDERS . " SET `orders_status` = '" . abs(intval($order_status_id)) . "', `last_modified` = NOW() WHERE `orders_id` = '" . abs(intval($order['orders_id'])) . "'");

			// Add Order Status History Entry
			$sql_data_array = array(
				'orders_id'         => $order['orders_id'],
				'orders_status_id'  => $order_status_id,
				'date_added'        => 'now()',
				'customer_notified' => '1',
				'comments'          =>
					sprintf(
						"[Notification]" .  PHP_EOL .
						"- Unique ID: %s" . PHP_EOL .
						"- Status: %s".     PHP_EOL .
                        "- Message: %s",
                        $payment->unique_id,
                        $payment->status,
                        $payment->message
					),
			);

			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		} else {
			list($order_id, $order_hash) = explode('-', $reconcile->transaction_id);

			$orderQuery = tep_db_query("SELECT `orders_id`, `orders_status`, `currency`, `currency_value` FROM " . TABLE_ORDERS . " WHERE `orders_id` = '" . abs(intval($order_id)) . "'");

			if (!tep_db_num_rows($orderQuery)) {
				exit(0);
			}

			$order = tep_db_fetch_array($orderQuery);

			$order_status_id = intval(getConst('MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_FAILED_ORDER_STATUS_ID'));

			// Update Order Status
			tep_db_query("UPDATE " . TABLE_ORDERS . " SET `orders_status` = '" . abs(intval($order_status_id)) . "', `last_modified` = NOW() WHERE `orders_id` = '" . abs(intval($order['orders_id'])) . "'");

			// Add Order Status History Entry
			$sql_data_array = array(
				'orders_id'         => $order['orders_id'],
				'orders_status_id'  => $order_status_id,
				'date_added'        => 'now()',
				'customer_notified' => '1',
				'comments'          =>
					sprintf(
						"[Notification]" .  PHP_EOL .
						"- Unique ID: %s" . PHP_EOL .
						"- Status: %s".     PHP_EOL .
                        "- Message: %s",
						$reconcile->unique_id,
						$reconcile->status,
                        $reconcile->message
					),
			);

			tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		}

        $notification->renderResponse();
	}
}

exit(0);
