<?php
/**
 * eMerchantPay Checkout
 *
 * Contains eMerchantPay Checkout Payment Logic
 *
 * @license     http://opensource.org/licenses/MIT The MIT License
 * @copyright   2016 eMerchantPay Ltd.
 * @version     $Id:$
 * @since       1.1.0
 */

/**
 * eMerchantPay Checkout
 *
 * Main class, instantiated by eMerchantPay providing
 * necessary methods to facilitate payments through
 * eMerchantPay's Payment Gateway
 */

if (!class_exists('emerchantpay_method_base')) {
	require_once DIR_FS_CATALOG . "ext/modules/payment/emerchantpay/method_base.php";
}

class emerchantpay_checkout extends emerchantpay_method_base
{
	/**
	 * emerchantpay_checkout constructor.
	 */
	public function __construct()
	{
		$this->code = static::EMERCHANTPAY_CHECKOUT_METHOD_CODE;
		parent::__construct();
	}

	/**
	 * Get If Module Requires SSL or not
	 * @return bool
	 */
	protected function getModuleRequiresSSL()
	{
		return false;
	}

	/**
	 * Get Transactions Table name for the Payment Module
	 * @return string
	 */
	protected function getTableNameTransactions()
	{
		return static::EMERCHANTPAY_CHECKOUT_TRANSACTIONS_TABLE_NAME;
	}

	/**
	 * Determines if the Module Admin Settings are properly configured
	 * @return bool
	 */
	protected function getIsConfigured()
	{
		return
			parent::getIsConfigured() &&
			!empty($this->getSetting('TRANSACTION_TYPES'));
	}

	/**
	 * Process Request to the Gateway
	 * @return bool
	 */
	protected function doBeforeProcessPayment()
	{
		global $order, $messageStack;

		$data = new stdClass();
		$data->transaction_id = $this->getGeneratedTransactionId();
		$data->description = '';

		foreach ($order->products as $product) {
			$separator = ($product == end($order->products)) ? '' : PHP_EOL;

			$data->description .= $product['qty'] . ' x ' . $product['name'] . $separator;
		}

		$data->currency = $order->info['currency'];

		$data->language_id = $this->getSetting('LANGUAGE');

		$data->urls = array(
			'notification'   =>
				$this->getNotificationUrl(),
			'return_success' =>
				$this->getReturnUrl(static::ACTION_SUCCESS),
			'return_failure' =>
				$this->getReturnUrl(static::ACTION_FAILURE),
			'return_cancel' =>
				$this->getReturnUrl(static::ACTION_CANCEL)
		);

		$data->order = $order;

		$errorMessage = null;

		try {
			$this->responseObject = $this->pay($data);

			return true;
		} catch (\Genesis\Exceptions\ErrorAPI $api) {
			$errorMessage = $api->getMessage();
			$this->responseObject = null;
		} catch (\Genesis\Exceptions\ErrorNetwork $e) {
			$errorMessage = $this->getSetting("MESSAGE_CHECK_CREDENTIALS") .
				PHP_EOL .
				$e->getMessage();
			$this->responseObject = null;
		} catch (\Exception $e) {
			$errorMessage = $e->getMessage();
			$this->responseObject = null;
		}

		if (empty($this->responseObject) && !empty($errorMessage)) {
			$messageStack->add_session($errorMessage, 'error');
			tep_redirect(
				tep_href_link(
					FILENAME_CHECKOUT_PAYMENT,
					'payment_error=' . get_class($this),
					'SSL'
				)
			);
		}

		return false;
	}

	/**
	 * Send transaction to Genesis
	 *
	 * @param stdClass $data
	 * @return stdClass
	 * @throws Exception
	 * @throws \Genesis\Exceptions\ErrorAPI
	 */
	protected function pay($data)
	{
		$genesis = new \Genesis\Genesis('WPF\Create');

		$genesis
			->request()
				->setTransactionId($data->transaction_id)
				->setUsage('osCommerce Electronic Transaction')
				->setDescription($data->description)
				->setNotificationUrl($data->urls['notification'])
				->setReturnSuccessUrl($data->urls['return_success'])
				->setReturnFailureUrl($data->urls['return_failure'])
				->setReturnCancelUrl($data->urls['return_cancel'])
				->setCurrency($data->currency)
				->setAmount($data->order->info['total'])
				->setCustomerEmail($data->order->customer['email_address'])
				->setCustomerPhone($data->order->customer['telephone'])
				->setBillingFirstName($data->order->billing['firstname'])
				->setBillingLastName($data->order->billing['lastname'])
				->setBillingAddress1($data->order->billing['street_address'])
				->setBillingZipCode($data->order->billing['postcode'])
				->setBillingCity($data->order->billing['city'])
				->setBillingState($this->getStateCode($data->order->billing))
				->setBillingCountry($data->order->billing['country']['iso_code_2'])
				->setShippingFirstName($data->order->delivery['firstname'])
				->setShippingLastName($data->order->delivery['lastname'])
				->setShippingAddress1($data->order->delivery['street_address'])
				->setShippingZipCode($data->order->delivery['postcode'])
				->setShippingCity($data->order->delivery['city'])
				->setShippingState($this->getStateCode($data->order->delivery))
				->setShippingCountry($data->order->delivery['country']['iso_code_2'])
				->setLanguage($data->language_id);

		foreach (static::getCheckoutTransactionTypes() as $transaction) {
			if (is_array($transaction)) {
				$genesis
					->request()
						->addTransactionType(
							$transaction['name'],
							$transaction['parameters']
						);
			} else {
				if (\Genesis\API\Constants\Transaction\Types::isPayByVoucher($transaction)) {
					$parameters = [
						'card_type' =>
							\Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes::VIRTUAL,
						'redeem_type' =>
							\Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes::INSTANT
					];
					if ($transaction == \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_YEEPAY) {
						$parameters['product_name'] 	= $data->description;
						$parameters['product_category'] = $data->description;
					}
					$genesis
						->request()
							->addTransactionType($transaction, $parameters);
				} else {
					$genesis
						->request()
							->addTransactionType($transaction);
				}
			}
		}

		$genesis->execute();

		return $genesis->response()->getResponseObject();
	}

	/**
	 * Generates Admin Order Transactions Panel
	 * @param int $order_id
	 * @return null|string
	 */
	public function displayTransactionsPanel($order_id)
	{
		if ($this->getIsAvailable()) {
			return parent::displayTransactionsPanel($order_id);
		} else {
			return false;
		}
	}

	/**
	 * Confirmation Check mothod for Checkout Confirmation Page
	 * @return bool
	 */
	function confirmation()
	{
        ?>
        <script type="text/javascript">
            $(document).ready(function () {
                $("form").on('submit', function() {
                    $('#tdb5').button("disable").prop('disabled', true);
                });
            });
        </script>

        <p style="text-align:center;">
            <?php echo $this->getSetting('TEXT_REDIRECT_WARNING'); ?>
        </p>
        <?php

		return false;
	}

	/**
	 * Get a list with the selected Transaction Type
	 * @return array
	 */
    function getCheckoutTransactionTypes()
    {
        $processed_list = array();

        $selected_types = array_map(
			'trim',
            explode(',', $this->getSetting('TRANSACTION_TYPES'))
        );

        $alias_map = array(
            \Genesis\API\Constants\Payment\Methods::EPS         =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY    =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::QIWI        =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY  =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TELEINGRESO =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY   =>
                \Genesis\API\Constants\Transaction\Types::PPRO,
        );

        foreach ($selected_types as $selected_type) {
            if (array_key_exists($selected_type, $alias_map)) {
                $transaction_type = $alias_map[$selected_type];

                $processed_list[$transaction_type]['name'] = $transaction_type;

                $processed_list[$transaction_type]['parameters'][] = array(
                    'payment_method' => $selected_type
                );
            } else {
                $processed_list[] = $selected_type;
            }
        }

        return $processed_list;
    }

	/**
	 * Builds a list of the available admin setting
	 * @return array
	 */
	protected function getConfigurationValues()
	{
		$configurationValues = array(
			array(
				"Checkout Title",
				$this->getSettingKey('CHECKOUT_PAGE_TITLE'),
				"Pay safely with eMerchantPay Checkout",
				"This name will be displayed on the checkout page",
				"6",
				"10",
				"emp_zfg_draw_input(null, ",
				null
			),
			array(
				"Transaction Types",
				$this->getSettingKey("TRANSACTION_TYPES"),
				\Genesis\API\Constants\Transaction\Types::SALE,
				"What transaction type should we use upon purchase?.",
				"6",
				"60",
				"emp_zfg_select_drop_down_multiple_from_object({$this->requiredOptionsAttributes}, \"{$this->code}\", \"getConfigTransactionTypesOptions\", ",
				null
			),
			array(
				"Checkout Page Language",
				$this->getSettingKey('LANGUAGE'),
				"en",
				"What language (localization) should we have on the Checkout?.",
				"6",
				"65",
				"emp_zfg_select_drop_down_single_from_object(\"{$this->code}\", \"getConfigLanguageOptions\",",
				null
			),

		);

		return array_merge(
			parent::getConfigurationValues(),
			$configurationValues
		);
	}

	/**
	 * Builds a list with the available Transaction Types & Payment Methods
	 * @return string
	 */
	public function getConfigTransactionTypesOptions()
	{
		$transactionTypes = array(
			\Genesis\API\Constants\Transaction\Types::ABNIDEAL            => "ABN iDEAL",
			\Genesis\API\Constants\Transaction\Types::AUTHORIZE           => "Authorize",
			\Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D        => "Authorize 3D",
			\Genesis\API\Constants\Transaction\Types::CASHU               => "CashU",
			\Genesis\API\Constants\Payment\Methods::EPS                   => "eps",
			\Genesis\API\Constants\Payment\Methods::GIRO_PAY              => "GiroPay",
			\Genesis\API\Constants\Transaction\Types::NETELLER            => "Neteller",
			\Genesis\API\Constants\Payment\Methods::QIWI                  => "Qiwi",
			\Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE   => "PayByVoucher (Sale)",
			\Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_YEEPAY => "PayByVoucher (oBeP)",
			\Genesis\API\Constants\Transaction\Types::PAYSAFECARD         => "PaySafeCard",
			\Genesis\API\Constants\Payment\Methods::PRZELEWY24            => "Przelewy24",
			\Genesis\API\Constants\Transaction\Types::POLI                => "POLi",
			\Genesis\API\Constants\Payment\Methods::SAFETY_PAY            => "SafetyPay",
			\Genesis\API\Constants\Transaction\Types::SALE                => "Sale",
			\Genesis\API\Constants\Transaction\Types::SALE_3D             => "Sale 3D",
			\Genesis\API\Constants\Transaction\Types::SOFORT              => "SOFORT",
			\Genesis\API\Constants\Payment\Methods::TELEINGRESO           => "teleingreso",
			\Genesis\API\Constants\Payment\Methods::TRUST_PAY             => "TrustPay",
			\Genesis\API\Constants\Transaction\Types::WEBMONEY            => "WebMoney"
		);

		return $this->buildSettingsDropDownOptions(
			$transactionTypes
		);
	}

	/**
	 * Builds a list with the available Checkout Languages
	 * @return string
	 */
	public function getConfigLanguageOptions()
	{
		$languages = array(
			'en' => 'English',
			'it' => 'Italian',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'ja' => 'Japanese',
			'zh' => 'Chinese',
			'ar' => 'Arabic',
			'pt' => 'Portuguese',
			'tr' => 'Turkish',
			'ru' => 'Russian',
			'hi' => 'Hindi',
			'bg' => 'Bulgarian'
		);

		return $this->buildSettingsDropDownOptions(
			$languages
		);
	}

	/**
	 * Get a list with all available Admin Settings for the Module
	 * @return array
	 */
	function keys() {
		$keys = parent::keys();

		$this->appendSettingKeys(
			$keys,
			array(
				'STATUS',
				'ENVIRONMENT',
				'TRANSACTION_TYPES'
			),
			array(
				'CHECKOUT_PAGE_TITLE',
				'TRANSACTION_TYPES',
				'LANGUAGE'
			)
		);

		return $keys;
	}
}

