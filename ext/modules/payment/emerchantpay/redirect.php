<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      eMerchantPay
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
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