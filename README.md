Genesis client for osCommerce
=============================

This is a Payment Module for osCommerce, that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* osCommerce v2.x
* [GenesisPHP v1.4.3](https://github.com/GenesisGateway/genesis_php) - (Integrated in Module)
* PCI-certified server in order to use ```eMerchantPay Direct```

GenesisPHP Requirements
------------

* PHP version 5.3.2 or newer
* PHP Extensions:
    * [BCMath](https://php.net/bcmath)
    * [CURL](https://php.net/curl) (required, only if you use the curl network interface)
    * [Filter](https://php.net/filter)
    * [Hash](https://php.net/hash)
    * [XMLReader](https://php.net/xmlreader)
    * [XMLWriter](https://php.net/xmlwriter)

Installation
------------

* Upload the contents of the folder (excluding ```README.md``` and ```admin```) to the ```<root>``` folder of your osCommerce installation
* Upload the contents of folder ```admin``` to your ```<admin>``` folder of your osCommerce installation
* Login inside the ```Administration``` area
* Make sure the Web User have write permissions to the following file ```<admin>/orders.php```, because it will be extended to allow managing order transactions
* Navigate to ```Modules``` -> ```Payment``` and click ```Install Module``` button
* Select the ```eMerchantPay Checkout``` or ```eMerchantPay Direct``` module and click ```Install Module```
* Set your credentials click ```Save```

Supported Transactions & Payment Methods
---------------------
* ```eMerchantPay Direct``` Payment Method
	* __Authorize__
	* __Authorize (3D-Secure)__
	* __Sale__
	* __Sale (3D-Secure)__

* ```eMerchantPay Checkout``` Payment Method
    * __ABN iDEAL__
    * __Authorize__
    * __Authorize (3D-Secure)__
    * __CashU__
    * __eps__
    * __GiroPay__
    * __Neteller__
    * __Qiwi__
    * __PayByVoucher (Sale)__
    * __PayByVoucher (oBeP)__
    * __PaySafeCard__
    * __Przelewy24__
    * __POLi__
    * __SafetyPay__
    * __Sale__
    * __Sale (3D-Secure)__
    * __SOFORT__
    * __TeleIngreso__
    * __TrustPay__
    * __WebMoney__ 

_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[support]: mailto:tech-support@emerchantpay.net
