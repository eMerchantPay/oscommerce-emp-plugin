<?php

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
	case 'success':
		// Finish what checkout_process started!
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
		tep_redirect(
			tep_href_link(
				FILENAME_CHECKOUT_PAYMENT,
				'error_message=' . urlencode(MODULE_PAYMENT_EMERCHANTPAY_GENESIS_ERROR_DESC),
				'SSL'
			)
		);
		break;
	case 'cancel':
		tep_redirect(tep_href_link(FILENAME_DEFAULT, '', 'SSL'));
		break;
}

exit(0);