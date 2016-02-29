<?php
/**
 * eMerchantPay Redirect Handler
 *
 * Handler customer redirection in Asynchronous transactions
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2015 eMerchantPay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

ini_set('display_errors', 'Off');
error_reporting(0);

chdir('../../../../');

require('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
if (!tep_session_is_registered('customer_id')) {
	$navigation->set_snapshot(array('mode' => 'SSL', 'page' => FILENAME_CHECKOUT_PAYMENT));
	tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
}

$return = isset($_GET['return']) ? trim($_GET['return']) : null;

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

		tep_redirect(
			tep_href_link(
				FILENAME_CHECKOUT_PAYMENT,
				'payment_error=' . $payment,
				'SSL'
			)
		);
		break;
	case 'cancel':
		tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'SSL'));
		break;
}

exit(0);