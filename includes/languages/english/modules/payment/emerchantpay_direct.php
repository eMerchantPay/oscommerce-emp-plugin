<?php
/**
 * emerchantpay Direct English Language file
 *
 * Contains English translation for strings used in the
 * emerchantpay Direct module
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2018 emerchantpay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_TITLE', 'emerchantpay Direct');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_PUBLIC_CHECKOUT_CONTAINER', '<img style="border: 0px none; display: block" src="images/emerchantpay/logos/emerchantpay_direct.png" /><span style="display: block; font-weight: bold; margin-left: 50pt;">emerchantpay offers a secure way to pay for your order, using Credit/Debit/Prepaid Card</span>');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_PUBLIC_TITLE', 'emerchantpay Direct');

define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_DESCRIPTION', '<a href="https://www.emerchantpay.com" target="_blank" style="width: 50%; display: block; margin: 0px auto;"><img style="border: 0px none; margin: 0px; width: 100%;" src="images/emerchantpay/logos/emerchantpay.png"/></a> <br> Direct API - allow customers to enter their CreditCard information on your website. Note: You need PCI-DSS certificate in order to enable this payment method. <br/> <br/> <img style="border: 0px none; margin: 0 auto; display: block" src="images/emerchantpay/logos/emerchantpay_direct.png" /> <br/> <a href="https://www.emerchantpay.com" target="_blank" style="text-decoration:underline;font-weight:bold; display: block; text-align: center;">Visit emerchantpay\'s Website</a> ');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_REDIRECT_WARNING', 'Notice: You will be redirect to emerchantpay\'s Secure Checkout Page, in order to complete your payment!');
// Error
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_ERROR_TITLE', 'Order processing error!');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_ERROR_DESC', 'An error was reported while processing your order, please try again!');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_ERROR_SSL', 'This Payment Method requires SSL to be enabled on your Web site in order be available!');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_ERROR_CHECK_CREDENTIALS', 'Please, make sure you\'ve properly entered your module credentials.');

define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_CREDIT_CARD_OWNER', 'Card Owner:');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_CREDIT_CARD_NUMBER', 'Card Number:');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_CREDIT_CARD_EXPIRES', 'Card Expiry Date:');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_TEXT_CVV', 'Card Code Number (CCV):');


//messages
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_PAYMENT_SUCCESSFUL', 'Payment successful');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_PAYMENT_CANCELED', 'You have successfully cancelled your order.');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_PAYMENT_FAILED', 'Please, check your input and try again.');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_CAPTURE_PARTIAL_DENIED', 'Partial Capture is currently disabled!');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_REFUND_PARTIAL_DENIED', 'Partial Refund is currently disabled!');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_VOID_DENIED', 'Cancel Transaction are currently disabled. You can enable this option in the Module Settings.');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_ENTER_ALL_REQUIRED_DATA', 'Please, make sure you\'ve entered all of the required data correctly, e.g. Email, Phone, Billing/Shipping Address.');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_MESSAGE_CHECK_CREDENTIALS', 'Please, make sure you\'ve properly entered your module credentials.');

//entries
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_TITLE', 'emerchantpay Transactions');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_CAPTURE_TRAN_TITLE', 'Capture Transaction');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_REFUND_TRAN_TITLE', 'Refund Transaction');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_VOID_TRAN_TITLE', 'Void Transaction');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_BUTTON_CANCEL', 'Close');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_BUTTON_CAPTURE', 'Capture');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_BUTTON_REFUND', 'Refund');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_BUTTON_VOID', 'Void');

define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_ID', 'Id');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_TYPE', 'Type');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_TIMESTAMP', 'Date');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_AMOUNT', 'Amount');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_STATUS', 'Status');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_MESSAGE', 'Message');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_MODE', 'Mode');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_ACTION_CAPTURE', 'Capture');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_ACTION_REFUND', 'Refund');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_HEADER_ACTION_VOID', 'Void');

define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_MODAL_AMOUNT_LABEL_CAPTURE', 'Capture amount');
define('MODULE_PAYMENT_EMERCHANTPAY_DIRECT_LABEL_ORDER_TRANS_MODAL_AMOUNT_LABEL_REFUND', 'Refund amount');