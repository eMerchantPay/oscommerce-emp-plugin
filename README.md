Genesis client for osCommerce
=============================

This is a Payment Module for osCommerce, that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* osCommerce v2.x
* [GenesisPHP v1.4](https://github.com/GenesisGateway/genesis_php) - (Integrated in Module)

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

* Upload the contents of the folder (excluding ```README.md```) to the ```<root>``` folder of your osCommerce installation
* Login inside the ```Administration``` area
* Navigate to ```Modules``` -> ```Payment``` and click ```Install Module``` button
* Select the ```eMerchantPay Checkout``` module and click ```Install Module```
* Set your credentials click ```Save```

You're now ready to process payments through our gateway.
