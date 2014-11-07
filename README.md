Genesis client for osCommerce
=============================

This is a Payment Module for eMerchantPay that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* osCommerce 2.x
* GenesisPHP 1.0

GenesisPHP Requirements
------------

* PHP version >= 5.3 (however since 5.3 is EoL, we recommend at least PHP v5.4)
* PHP with libxml
* PHP ext: cURL (optionally you can use StreamContext)
* Composer

Installation
------------

* Copy the files to the root folder of your osCommerce installation
* Login inside the Admin Panel and Enable the "eMerchantPay (Credit Card)" module
* Set the correct credentials, select your prefered payment method and click "Save"

You're now ready to process payments through our gateway.
