emerchantpay Gateway Module for osCommerce
=============================

This is a Payment Module for osCommerce, that gives you the ability to process payments through emerchantpay's Payment Gateway - Genesis.

Requirements
------------

* osCommerce v2.x
* [GenesisPHP v2.0.2](https://github.com/GenesisGateway/genesis_php/releases/tag/2.0.2) - (Integrated in Module)

GenesisPHP Requirements
------------

* PHP version 5.5.9 or newer
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
* Select the ```emerchantpay Checkout``` module and click ```Install Module```
* Set your credentials click ```Save```

Supported Transactions & Payment Methods
---------------------
* ```emerchantpay Checkout``` Payment Method
    * __Apple Pay__ 
    * __Argencard__
    * __Aura__
    * __Authorize__
    * __Authorize (3D-Secure)__
    * __Baloto__
    * __Bancomer__
    * __Bancontact__
    * __Banco de Occidente__
    * __Banco do Brasil__
    * __BitPay__
    * __Boleto__
    * __Bradesco__
    * __Cabal__
    * __CashU__
    * __Cencosud__
    * __Davivienda__
    * __Efecty__
    * __Elo__
    * __eps__
    * __eZeeWallet__
    * __Fashioncheque__
    * __Google Pay__
    * __iDeal__
    * __iDebit__
    * __InstaDebit__
    * __InitRecurringSale__
    * __InitRecurringSale (3D-Secure)__
    * __Intersolve__
    * __Itau__
    * __Klarna__
    * __Multibanco__
    * __MyBank__
    * __Naranja__
    * __Nativa__
    * __Neosurf__
    * __Neteller__
    * __Online Banking__
      * __Interac Combined Pay-in (CPI)__ 
      * __Bancontact (BCT)__ 
      * __BLIK (BLK)__
      * __SPEI (SE)__
      * __LatiPay (PID)__
    * __OXXO__
    * __P24__
    * __Pago Facil__
    * __PayPal__
    * __PaySafeCard__
    * __PayU__
    * __Pix__
    * __POLi__
    * __Post Finance__
    * __PSE__
    * __RapiPago__
    * __Redpagos__
    * __SafetyPay__
    * __Sale__
    * __Sale (3D-Secure)__
    * __Santander__
    * __Sepa Direct Debit__
    * __SOFORT__
    * __Tarjeta Shopping__
    * __TCS__
    * __Trustly__
    * __TrustPay__
    * __UPI__
    * __WebMoney__
    * __WebPay__
    * __WeChat__


_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[support]: mailto:tech-support@emerchantpay.net
