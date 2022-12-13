<?php
/**
 * Copyright (C) 2022 emerchantpay Ltd.
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
 * @author      emerchantpay
 * @copyright   2022 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\PasswordChangeIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\RegistrationIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\ShippingAddressUsageIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\CardHolderAccount\UpdateIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\ReorderItemIndicators;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\Purchase\Categories;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\DeliveryTimeframes;
use Genesis\API\Constants\Transaction\Parameters\Threeds\V2\MerchantRisk\ShippingIndicators;

/**
 * Emerchantpay threeds helper class
 */
class emerchantpay_threeds
{

    /**
     * OsCommerce datetime format
     */
    const OSCOMMERCE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Indicator value constants
     */
    const CURRENT_TRANSACTION_INDICATOR       = 'current_transaction';
    const LESS_THAN_30_DAYS_INDICATOR         = 'less_than_30_days';
    const MORE_THAN_30_LESS_THAN_60_INDICATOR = 'more_30_less_60_days';
    const MORE_THAN_60_DAYS_INDICATOR         = 'more_than_60_days';

    /**
     * Activity periods
     */
    const ACTIVITY_24_HOURS = 'PT24H';
    const ACTIVITY_6_MONTHS = 'P6M';
    const ACTIVITY_1_YEAR   = 'P1Y';

    /**
     * @var array $complete_statuses Ids of statuses when order is completed successfully
     */
    private static $complete_statuses = [3];

    /**
     * Get type of the purchase
     *
     * @param bool $isVirtualCart
     *
     * @return string
     */
    public static function getThreedsPurchaseCategory($isVirtualCart)
    {
        return $isVirtualCart ?
            Categories::SERVICE :
            Categories::GOODS;
    }

    /**
     * Get delivery timeframe
     *
     * @param bool $isVirtualCart
     *
     * @return string
     */
    public static function getThreedsDeliveryTimeframe($isVirtualCart)
    {
        return $isVirtualCart ?
            DeliveryTimeframes::ELECTRONICS :
            DeliveryTimeframes::ANOTHER_DAY;
    }

    /**
     * Get shipping indicator
     *
     * @param object $data
     *
     * @return string
     */
    public static function getShippingIndicator($data)
    {
        if ($data->order->content_type == 'virtual') {
            return ShippingIndicators::DIGITAL_GOODS;
        }

        $indicator = ShippingIndicators::STORED_ADDRESS;

        if (self::areAddressesSame($data->order->billing, $data->order->delivery)) {
            $indicator = ShippingIndicators::SAME_AS_BILLING;
        }

        return $indicator;
    }

    /**
     * Get reorder items indicator
     *
     * @param int   $customer_id
     * @param array $products
     *
     * @return string
     */
    public static function getReorderItemsIndicator($customer_id, $products)
    {
        $product_ids_query_raw = sprintf(
            "SELECT DISTINCT (op.products_id) AS product_id
            FROM %s o
            JOIN %s op ON op.orders_id = o.orders_id 
            WHERE customers_id = %d
            ORDER BY products_id
            ",
            TABLE_ORDERS,
            TABLE_ORDERS_PRODUCTS,
            $customer_id
        );

        $product_ids_query     = tep_db_query($product_ids_query_raw);
        // We use the native method to extract product id from the complex id/option/value
        $order_product_ids     = array_map('tep_get_prid', array_column($products, 'id'));

        while ($product_id = tep_db_fetch_array($product_ids_query)) {
            if (in_array($product_id['product_id'], $order_product_ids)) {
                return ReorderItemIndicators::REORDERED;
            }
        }

        return ReorderItemIndicators::FIRST_TIME;
    }

    /**
     * Get customer's creation and last modified dates
     *
     * @param int $customer_id
     *
     * @return array|false
     */
    public static function getCustomerInfo($customer_id)
    {
        $info_query_raw = sprintf(
            "SELECT customers_info_date_account_created AS date_account_created,
            COALESCE (
		        customers_info_date_account_last_modified,
		        customers_info_date_account_created
	        ) AS date_account_last_modified
            FROM %s
            WHERE customers_info_id = %d
            ",
            TABLE_CUSTOMERS_INFO,
            $customer_id
        );
        $info_query     = tep_db_query($info_query_raw);

        return tep_db_fetch_array($info_query);
    }

    /**
     * Get account update indicator
     *
     * @param array $customer_info
     *
     * @return string
     */
    public static function getUpdateIndicator($customer_info)
    {
        $indicatorClass = UpdateIndicators::class;
        $dateToCheck    = $customer_info['date_account_last_modified'];

        return self::getIndicatorValue($dateToCheck, $indicatorClass);
    }

    /**
     * Get list of all customer's orders
     *
     * @param int $customer_id
     *
     * @return array
     */
    public static function getCustomerOrders($customer_id)
    {
        $customer_orders_query_raw = sprintf(
            "SELECT * FROM %s
            WHERE customers_id = %d
            ORDER BY date_purchased
        ",
            TABLE_ORDERS,
            $customer_id
        );
        $customer_orders_query     = tep_db_query($customer_orders_query_raw);

        $orders = array();
        while ($order = tep_db_fetch_array($customer_orders_query)) {
            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * Get customer's registration indicator
     *
     * @param array $customer_orders
     *
     * @return string
     */
    public static function getRegistrationIndicator($customer_orders)
    {
        $indicatorClass = RegistrationIndicators::class;
        $customerFirstOrderDate = self::findFirstCustomerOrderDate($customer_orders);

        return self::getIndicatorValue($customerFirstOrderDate, $indicatorClass);
    }

    /**
     * Find first customer's order date
     *
     * @param array $customer_orders
     *
     * @return string
     */
    public static function findFirstCustomerOrderDate($customer_orders)
    {
        $order_date = (new \DateTime())->format(self::OSCOMMERCE_DATETIME_FORMAT);

        if (is_array($customer_orders) and count($customer_orders) > 0) {
            $order_date = $customer_orders[0]['date_purchased'];
        }

        return $order_date;
    }

    /**
     * Get date when sipping address is used for the first time
     *
     * @param array $order_info
     * @param array $customer_orders
     *
     * @return string
     */
    public static function findShippingAddressDateFirstUsed($order_info, $customer_orders)
    {
        $cart_shipping_address = [
            "$order_info[firstname] $order_info[lastname]",
            $order_info['street_address'],
            $order_info['suburb'],
            $order_info['delivery_city'],
            $order_info['delivery_postcode'],
            $order_info['country']['title'],
        ];

        if (is_array($customer_orders) && count($customer_orders) > 0) {
            foreach ($customer_orders as $customer_order) {
                $order_shipping_address = [
                    $customer_order['delivery_name'],
                    $customer_order['delivery_street_address'],
                    $customer_order['delivery_suburb'],
                    $customer_order['delivery_city'],
                    $customer_order['delivery_postcode'],
                    $customer_order['delivery_country'],
                ];

                if (count(array_diff($cart_shipping_address, $order_shipping_address)) === 0) {
                    return $customer_order['date_purchased'];
                }
            }
        }

        return (new DateTime())->format(self::OSCOMMERCE_DATETIME_FORMAT);
    }

    /**
     * Get shipping address usage indicator
     *
     * @param string $date
     *
     * @return string
     */
    public static function getShippingAddressUsageIndicator($date)
    {
        return self::getIndicatorValue($date, ShippingAddressUsageIndicators::class);
    }

    /**
     * Find number of customer's orders for a period
     *
     * @param array $customer_orders
     *
     * @return array
     */
    public static function findNumberOfOrdersForaPeriod($customer_orders)
    {
        $number_of_orders_last_24h  = 0;
        $number_of_orders_last_6m   = 0;
        $number_of_orders_last_year = 0;

        if (is_array($customer_orders) && count($customer_orders) > 0) {
            $customer_orders     = array_reverse($customer_orders);
            $start_date_last_24h = (new DateTime())->sub(new DateInterval(self::ACTIVITY_24_HOURS));
            $start_date_last_6m  = (new DateTime())->sub(new DateInterval(self::ACTIVITY_6_MONTHS));

            $previous_year        = (new DateTime())->sub(new DateInterval(self::ACTIVITY_1_YEAR))
                ->format('Y');
            $start_date_last_year = (new DateTime())
                ->setDate($previous_year, 1, 1)
                ->setTime(0, 0, 0);
            $end_date_last_year = (new DateTime())
                ->setDate($previous_year, 12, 31)
                ->setTime(23, 59, 59);

            foreach ($customer_orders as $customer_order) {
                $order_date = DateTime::createFromFormat(
                    self::OSCOMMERCE_DATETIME_FORMAT,
                    $customer_order['date_purchased']
                );

                // We don't need orders older than a year
                if ($order_date < $start_date_last_year) {
                    break;
                }

                // Get order details only if the order was placed within the last 6 months
                if ($order_date >= $start_date_last_6m) {
                    // Check if the order status is complete or shipped
                    $number_of_orders_last_6m +=
                        (in_array($customer_order['orders_status'], self::$complete_statuses)) ? 1 : 0;
                }

                $number_of_orders_last_24h += ($order_date >= $start_date_last_24h) ? 1 : 0;
                $number_of_orders_last_year += ($order_date <= $end_date_last_year) ? 1 : 0;
            }
        }

        return [
            'last_24h'  => $number_of_orders_last_24h,
            'last_6m'   => $number_of_orders_last_6m,
            'last_year' => $number_of_orders_last_year
        ];
    }

    /**
     * Get Password change indicator
     *
     * @param string $date
     *
     * @return string
     */
    public static function getPasswordChangeIndicator($date)
    {
        return self::getIndicatorValue($date, PasswordChangeIndicators::class);
    }

    /**
     * Check if the addresses are same
     *
     * @param array $invoiceAddress
     * @param array $shippingAddress
     *
     * @return bool
     */
    private static function areAddressesSame($invoiceAddress, $shippingAddress)
    {
        $invoice = [
            $invoiceAddress['firstname'],
            $invoiceAddress['lastname'],
            $invoiceAddress['street_address'],
            $invoiceAddress['suburb'],
            $invoiceAddress['postcode'],
            $invoiceAddress['city'],
            $invoiceAddress['country']['id'],
        ];

        $shipping = [
            $shippingAddress['firstname'],
            $shippingAddress['lastname'],
            $shippingAddress['street_address'],
            $shippingAddress['suburb'],
            $shippingAddress['postcode'],
            $shippingAddress['city'],
            $shippingAddress['country']['id'],
        ];

        return count(array_diff($invoice, $shipping)) === 0;
    }

    /**
     * Get indicator value according the given period of time
     *
     * @param string $date
     * @param string $indicatorClass
     *
     * @return string
     */
    private static function getIndicatorValue($date, $indicatorClass)
    {
        switch (self::getDateIndicator($date)) {
            case static::LESS_THAN_30_DAYS_INDICATOR:
                return $indicatorClass::LESS_THAN_30DAYS;
            case static::MORE_THAN_30_LESS_THAN_60_INDICATOR:
                return $indicatorClass::FROM_30_TO_60_DAYS;
            case static::MORE_THAN_60_DAYS_INDICATOR:
                return $indicatorClass::MORE_THAN_60DAYS;
            default:
                if ($indicatorClass === PasswordChangeIndicators::class) {
                    return $indicatorClass::DURING_TRANSACTION;
                }

                return $indicatorClass::CURRENT_TRANSACTION;
        }
    }

    /**
     * Check if date is less than 30, between 30 and 60 or more than 60 days
     *
     * @param string $date
     *
     * @return string
     */
    private static function getDateIndicator($date)
    {
        $now = new DateTime();
        $checkDate = \DateTime::createFromFormat(self::OSCOMMERCE_DATETIME_FORMAT, $date);
        $days = $checkDate->diff($now)->days;

        if ($days < 1) {
            return self::CURRENT_TRANSACTION_INDICATOR;
        }
        if ($days <= 30) {
            return self::LESS_THAN_30_DAYS_INDICATOR;
        }
        if ($days < 60) {
            return self::MORE_THAN_30_LESS_THAN_60_INDICATOR;
        }

        return self::MORE_THAN_60_DAYS_INDICATOR;
    }
}
