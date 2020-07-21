<?php
/*
 * Copyright (C) 2018 emerchantpay Ltd.
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
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

if (emp_get_is_admin_payment_page_overview()) {
    ?>
        <style type="text/css">
            span.error-emerchantpay {
                color: #ff0000;
                font-weight: bold;
            }

            span.warning-emerchantpay {
                background-color: #fcf8e3;
                border-color: #faebcc;
                color: #8a6d3b;
            }

            span.success-emerchantpay {
                background-color: #dff0d8;
                border-color: #d6e9c6;
                color: #3c763d;
            }
        </style>
    <?php
}

if (emp_get_is_payment_module_index_action()) {
    ?>
        <style type="text/css">
            span.emerchantpay-toggle  {
                display: inline-block;
            }

            span.emerchantpay-toggle.toggle-on {
                color: #088A08;
            }

            span.emerchantpay-toggle.toggle-off {
                color: #FA5858;
            }
        </style>
    <?php
}

if (emp_get_is_payment_module_edit_action()) {
    $jsPath = "includes/javascript/emerchantpay/";
    $cssPath = "includes/css/emerchantpay/";

    echo emp_add_external_resources(
        array(
            "jquery-1.12.3.min.js",
            "bootstrap.css",
            "jquery.number.min.js",
            "bootstrap-checkbox.min.js"
        )
    );
    ?>
    <script type="text/javascript">
        var $emp = $.noConflict();

        $emp(document).ready(function() {
            $emp('input.bootstrap-checkbox').checkboxpicker({
                html: true,
                offLabel: '<span class="glyphicon glyphicon-remove">',
                onLabel: '<span class="glyphicon glyphicon-ok">',
                style: 'btn-group-sm'
            });

            $emp('input.bootstrap-checkbox').change(function() {
                var isChecked = $emp(this).prop('checked');
                $emp(this).parent().find('input[type="hidden"]').val(isChecked);
            });

            $emp('input.form-number-input').number(true, 0, '', '');

            $emp('select[multiple]').change(function() {
                var $form = $emp(this).closest('form');
                if ($form.length < 1)
                    return;

                var hiddenControlName = $emp(this).attr('data-target');
                var $hiddenControl = $form.find('input:hidden[name="' + hiddenControlName +  '"]');

                if ($hiddenControl.length < 1)
                    return;

                var selectedOptions = $emp(this).find('option:selected');

                var selectedOptionValues = $emp.map(selectedOptions, function(option) {
                    return option.value;
                });

                $hiddenControl.val(
                    selectedOptionValues.join(',')
                );
            });
        });

    </script>

    <style type="text/css">

        .form-group {
            padding-top: 5pt;
            width: 95%;
            margin: 0 auto;
        }

        .form-group.toggle-container {
            text-align: right;
        }

        .form-control {
            height: 20pt;
            font-size: 8pt;
            width: 100%;
        }

        input.form-control {
            padding: 0 3pt;
        }

        select.form-control {
            padding: 2pt 5pt;
        }

        select.form-control[multiple="multiple"] {
            height: 120pt;
        }

        .btn-group a.btn {
            min-width: 30pt;
        }
    </style>

    <?php
}

/**
 * Get External Resources HTML
 * @param array $resourceNames
 * @return string
 */
function emp_add_external_resources($resourceNames)
{
    $html = "";
    foreach ($resourceNames as $key => $resourceName) {
        $html .= emp_add_external_resource($resourceName);
    }
    return $html;
}

/**
 * Get External Resource HTML By Resource Name
 * @param string $resourcePath
 * @return string
 */
function emp_add_external_resource($resourcePath)
{
    $isResourceJavaScript = emp_get_string_ends_with($resourcePath, '.js');

    $includePath =
        "includes/javascript/emerchantpay/" .
        ($isResourceJavaScript ? "js/" : "css/");

    if (emp_get_string_starts_with($resourcePath, 'jquery')) {
        $includePath .= "jQueryExtensions/";
    } elseif (emp_get_string_starts_with($resourcePath, 'bootstrap')) {
        $includePath .= "bootstrap/";
    } elseif (emp_get_string_starts_with($resourcePath, 'font-awesome')) {
        $includePath .= "font-awesome/";
    }

    if ($isResourceJavaScript) {
        return "<script src=\"" . $includePath . $resourcePath ."\"></script>";
    } else {
        return "<link href=\"" . $includePath . $resourcePath . "\" rel=\"stylesheet\" type=\"text/css\" />";
    }
}

/**
 * Check if Current Page is Nodule Esit Page
 * @return bool
 */
function emp_get_is_payment_module_edit_action()
{
    return
        emp_get_is_payment_module_index_action() &&
        isset($_GET['action']) &&
        (strtolower($_GET['action'] == 'edit'));
}

/**
 * Check if Current Page is Module Preview Page
 * @return bool
 */
function emp_get_is_admin_payment_page_overview()
{
    return
        isset($_GET['set']) &&
        (strtolower($_GET['set']) == 'payment');
}

/**
 * Check if Current Page is Module Preview Page
 * @return bool
 */
function emp_get_is_payment_module_index_action()
{
    return
        emp_get_is_admin_payment_page_overview() &&
        isset($_GET['module']) &&
        (
            (strtolower($_GET['module']) == 'emerchantpay_checkout') ||
            (strtolower($_GET['module']) == 'emerchantpay_direct')
        );
}

/**
 * Gets html attributes by array
 * @param array $attributes
 * @return string
 */
function emp_convert_attributes_array_to_html($attributes)
{
    if (is_array($attributes)) {
        $html = '';

        foreach ($attributes as $key => $value) {
            $html .= sprintf(" %s=\"%s\"", $key, $value);
        }
        return $html;
    }
    return $attributes;
}

/**
 * Get Place Holder for Setting InputBox
 * @param string $key
 * @return null|string
 */
function emp_get_module_setting_placeholder($key)
{
    if (emp_get_string_ends_with($key, "PAGE_TITLE")) {
        return "This name will be displayed on the checkout page";
    } elseif (emp_get_string_ends_with($key, "USERNAME")) {
        return "Enter your Genesis Username here";
    } elseif (emp_get_string_ends_with($key, "PASSWORD")) {
        return "Enter your Genesis Password here";
    } elseif (emp_get_string_ends_with($key, "TOKEN")) {
        return "Enter your Genesis Token here";
    }

    return null;
}

/**
 * Check if string starts with a specific value
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function emp_get_string_starts_with($haystack, $needle)
{
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
}

/**
 * Check if string ends with a specific value
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function emp_get_string_ends_with($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

/**
 * @param \stdClass $data
 * @param bool $is_order
 * @return null | \Genesis\API\Request\Financial\Alternatives\Klarna\Items
 */
function get_klarna_custom_param_items($data, $is_order = false)
{
    if (!isset($data->order)) {
        return new \Genesis\API\Request\Financial\Alternatives\Klarna\Items($data->currency);
    }

    try {
        /**
         * @property order $order
         */
        $order = $data->order;

        $items = new \Genesis\API\Request\Financial\Alternatives\Klarna\Items($order->info['currency']);
        foreach ($order->products as $product) {
            $productType = get_product_type($product, $is_order) == 'virtual' ?
                \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_DIGITAL :
                \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_PHYSICAL;

            $klarnaItem = new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                $product['name'],
                $productType,
                $product['qty'],
                $product['final_price']
            );
            $items->addItem($klarnaItem);
        }

        $taxes = floatval($order->info['tax']);
        if ($taxes) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    'Taxes',
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_SURCHARGE,
                    1,
                    $taxes
                )
            );
        }

        $shipping = floatval($order->info['shipping_cost']);
        if ($shipping) {
            $items->addItem(
                new \Genesis\API\Request\Financial\Alternatives\Klarna\Item(
                    $order->info['shipping_method'],
                    \Genesis\API\Request\Financial\Alternatives\Klarna\Item::ITEM_TYPE_SHIPPING_FEE,
                    1,
                    $shipping
                )
            );
        }

        return $items;
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Check Cart product
 *
 * @param integer $product_id
 * @param $option_id
 * @return bool
 */
function is_virtual_product($product_id, $option_id)
{
    if (!is_numeric($product_id) || !is_numeric($option_id)) {
        return false;
    }

    $virtual_check_query = tep_db_query(
        "SELECT count(*) as total " .
        "FROM " .
        TABLE_PRODUCTS_ATTRIBUTES . " pa, " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad ".
        "WHERE " .
        "pa.products_id = '" . (int)$product_id . "' AND " .
        "pa.options_values_id = '" . (int)$option_id . "' AND " .
        "pa.products_attributes_id = pad.products_attributes_id"
    );
    $virtual_check = tep_db_fetch_array($virtual_check_query);

    if ($virtual_check['total'] > 0) {
        return true;
    }

    return false;
}

/**
 * Check Order product
 *
 * @param $order_product_id
 * @return bool
 */
function is_virtual_order_product($order_product_id)
{
    if (!is_numeric($order_product_id)) {
        return false;
    }

    $virtual_order_check_query = tep_db_query(
        "SELECT count(*) as total " .
        "FROM " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " as opd, ". TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " as pa " .
        "WHERE " .
        "pa.orders_products_attributes_id = '" . (int)$order_product_id . "' ".
        "AND opd.orders_products_id = pa.orders_products_id"
    );
    $virtual_order_check_query = tep_db_fetch_array($virtual_order_check_query);

    if ($virtual_order_check_query['total'] > 0) {
        return true;
    }

    return false;
}

/**
 * Determinate the product type
 *
 * @param $product
 * @param bool $is_order
 * @return string physical | virtual
 */
function get_product_type($product, $is_order = false)
{
    if (!array_key_exists('attributes', $product)) {
        return 'physical';
    }

    foreach ($product['attributes'] as $attribute) {
        $type = $is_order ?
            is_virtual_order_product((int)$attribute['attribute_id']) :
            is_virtual_product((int)$product['id'], (int)$attribute['value_id']);

        if (isset($productTypeBool) && $productTypeBool != $type) {
            return 'physical';
        }

        $productTypeBool = $type;
    }

    if ($productTypeBool === true) {
        return 'virtual';
    }

    return 'physical';
}

/**
 * Retrieve OrderProducts with their IDs and Attribute OptionIDs
 *
 * @param $order_id
 * @return array
 */
function get_orders_products($order_id)
{
    $products = array();

    $index = 0;
    $orders_products_query = tep_db_query(
        "SELECT " .
        "orders_products_id, " .
        "products_name, " .
        "products_model, " .
        "products_price, " .
        "products_tax, " .
        "products_quantity, " .
        "final_price " .
        "FROM " . TABLE_ORDERS_PRODUCTS . " " .
        "WHERE orders_id = '" . (int)$order_id . "'"
    );
    while ($orders_products = tep_db_fetch_array($orders_products_query)) {
        $products[$index] = array('qty' => $orders_products['products_quantity'],
            'id'    => $orders_products['orders_products_id'],
            'name' => $orders_products['products_name'],
            'model' => $orders_products['products_model'],
            'tax' => $orders_products['products_tax'],
            'price' => $orders_products['products_price'],
            'final_price' => $orders_products['final_price']);

        $subindex = 0;
        $attributes_query = tep_db_query(
            "SELECT " .
            "orders_products_attributes_id, " .
            "products_options, " .
            "products_options_values, " .
            "options_values_price, " .
            "price_prefix " .
            "FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . " " .
            "WHERE " .
            "orders_id = '" . (int)$order_id . "' AND " .
            "orders_products_id = '" . (int)$orders_products['orders_products_id'] . "'"
        );
        if (tep_db_num_rows($attributes_query)) {
            while ($attributes = tep_db_fetch_array($attributes_query)) {
                $products[$index]['attributes'][$subindex] = array(
                    'attribute_id' => $attributes['orders_products_attributes_id'],
                    'option' => $attributes['products_options'],
                    'value' => $attributes['products_options_values'],
                    'prefix' => $attributes['price_prefix'],
                    'price' => $attributes['options_values_price']);

                $subindex++;
            }
        }
        $index++;
    }

    return $products;
}

/**
 * Extract tax and shipping cost for specific Order
 *
 * @param int $order_id
 * @return array
 */
function get_orders_totals_values($order_id)
{
    $order_totals = array();

    $totals_query = tep_db_query(
        "SELECT " .
        "title, " .
        "value, " .
        "class " .
        "FROM " . TABLE_ORDERS_TOTAL . " " .
        "WHERE orders_id = '" . (int)$order_id . "' order by sort_order"
    );
    while ($totals = tep_db_fetch_array($totals_query)) {
        $order_totals[] = array(
            'title' => $totals['title'],
            'value'  => $totals['value'],
            'class' => $totals['class']
        );
    }

    return $order_totals;
}

/**
 * Reconstruct Cart info from Order
 *
 * @param int $order_id
 * @return stdClass
 */
function get_klarna_data($order_id)
{
    $klarnaData                  = new \stdClass();
    $klarnaData->order           = new order($order_id);
    $klarnaData->order->products = get_orders_products($order_id);

    $totals = get_orders_totals_values($order_id);

    $taxes    = get_klarna_data_from_totals($totals, 'ot_tax');
    $shipping = get_klarna_data_from_totals($totals, 'ot_shipping');

    $klarnaData->order->info['tax']             = $taxes['value'];
    $klarnaData->order->info['shipping_cost']   = $shipping['value'];
    $klarnaData->order->info['shipping_method'] = $shipping['title'];

    return $klarnaData;
}

/**
 * @param array $totals
 * @param string $recordType
 * @return array
 */
function get_klarna_data_from_totals($totals, $recordType)
{
    foreach ($totals as $total) {
        if ($total['class'] === $recordType) {
            return array (
                'title' => $total['title'],
                'value' => $total['value']
            );
        }
    }
}
