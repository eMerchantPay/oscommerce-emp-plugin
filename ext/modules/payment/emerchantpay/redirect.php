<?php
/**
 * eMerchantPay Redirect Handler
 *
 * Handler customer redirection in Asynchronous transactions
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

require('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
if (!tep_session_is_registered('customer_id')) {
	$navigation->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_CHECKOUT_PAYMENT));
	tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$return = isset($_GET['return']) ? strval($_GET['return']) : null;

switch ($return) {
	default:
		break;
	// Finish what checkout_process started!
	case 'success':
		global $cart;

		$cart->reset(true);

		tep_session_unregister('sendto');
		tep_session_unregister('billto');
		tep_session_unregister('shipping');
		tep_session_unregister('payment');
		tep_session_unregister('comments');

		tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
		break;
	case 'failure':
		global $payment;

		require(DIR_WS_CLASSES . 'payment.php');

		$payment_class = new payment($payment);

		switch($payment) {
			case 'emerchantpay_checkout':
				$error = MODULE_PAYMENT_EMERCHANTPAY_CHECKOUT_ERROR_DESC;
				break;
			case 'emerchantpay_standard':
				$error = MODULE_PAYMENT_EMERCHANTPAY_STANDARD_ERROR_DESC;
				break;
			default:
				$error = 'Unable to process the transaction, please try again!';
				break;
		}

		tep_redirect(
			tep_href_link(
				FILENAME_CHECKOUT_PAYMENT,
				'error_message=' . urlencode($error),
				'SSL'
			)
		);
		break;
	case 'cancel':
		tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'SSL'));
		break;
}

exit(0);