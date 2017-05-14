<?php

/**
 * Darwin Pricing integration for WooCommerce.
 */
class WC_Darwin_Pricing extends WC_Integration
{

    /**
     * @var int|false
     */
    protected $_clientId;

    /**
     * @var string|false
     */
    protected $_clientSecret, $_serverUrl;

    /**
     * Initialize the plugin.
     */
    public function __construct()
    {
        $this->id = 'darwin_pricing';
        $this->method_title = __('Darwin Pricing', 'woocommerce-darwin-pricing-integration');
        $this->method_description = __('Darwin Pricing is a dynamic pricing software that provides real-time market monitoring, pricing optimization and a geo-targeted coupon box to eCommerce websites.', 'woocommerce-darwin-pricing-integration');

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_integration_darwin_pricing', array($this, 'process_admin_options'));
        add_action('wp_head', array($this, 'loadWidget'));
        add_action('woocommerce_order_status_completed', array($this, 'trackOrder'));
    }

    /**
     * Initialize Setting Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'serverUrl' => array(
                'title' => __('API Server', 'woocommerce-darwin-pricing-integration'),
                'description' => __('The URL of the Darwin Pricing API server for your website', 'woocommerce-darwin-pricing-integration'),
                'type' => 'text',
                'default' => ''
            ),
            'clientId' => array(
                'title' => __('Client ID', 'woocommerce-darwin-pricing-integration'),
                'description' => __('The client ID for your website', 'woocommerce-darwin-pricing-integration'),
                'type' => 'text',
                'default' => ''
            ),
            'clientSecret' => array(
                'title' => __('Client Secret', 'woocommerce-darwin-pricing-integration'),
                'description' => __('The client secret for your website', 'woocommerce-darwin-pricing-integration'),
                'type' => 'text',
                'default' => ''
            ),
        );
    }

    /**
     * Load the widget.
     */
    public function loadWidget()
    {
        if (!$this->_isActive()) {
            return;
        }
        $widgetUrl = $this->_getApiUrl('/widget');
        print $this->_loadAsynchronousJavascript($widgetUrl);
    }

    /**
     * Track the order.
     *
     * @param int $orderId
     */
    public function trackOrder($orderId)
    {
        if (!$this->_isActive()) {
            return;
        }
        $wooCommerceOrder = new WC_Order($orderId);
        $order = WC_Darwin_Pricing_Order::fromWooCommerce($wooCommerceOrder);
        $url = $this->_getApiUrl('/webhook/order', $order->getCustomerIp());
        $this->_webhook($url, $order);
    }

    /**
     * @param callable $workload
     */
    protected function _executeOnShutdown(callable $workload)
    {
        register_shutdown_function(function () use ($workload) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $workload();
        });
    }

    /**
     * @param string $path
     * @param string|null $visitorIp
     * @return string
     */
    protected function _getApiUrl($path, $visitorIp = null)
    {
        $serverUrl = rtrim($this->_getServerUrl(), '/');
        $apiUrl = $serverUrl . $path;
        $parameterList = array('site-id' => $this->_getClientId());
        if (null !== $visitorIp) {
            $parameterList['hash'] = $this->_getClientSecret();
            $parameterList['visitor-ip'] = $visitorIp;
        }
        $apiUrl .= '?' . http_build_query($parameterList, '', '&');
        return $apiUrl;
    }

    /**
     * @return int|false
     */
    protected function _getClientId()
    {
        if (!isset($this->_clientId)) {
            $clientId = trim($this->get_option('clientId'));
            $this->_clientId = is_numeric($clientId) ? (int)$clientId : false;
        }
        return $this->_clientId;
    }

    /**
     * @return string|false
     */
    protected function _getClientSecret()
    {
        if (!isset($this->_clientSecret)) {
            $clientSecret = trim($this->get_option('clientSecret'));
            $this->_clientSecret = ('' !== $clientSecret) ? $clientSecret : false;
        }
        return $this->_clientSecret;
    }

    /**
     * @return string|false
     */
    protected function _getServerUrl()
    {
        if (!isset($this->_serverUrl)) {
            $serverUrl = trim($this->get_option('serverUrl'));
            $this->_serverUrl = ('' !== $serverUrl) ? $serverUrl : false;
        }
        return $this->_serverUrl;
    }

    /**
     * @return bool
     */
    protected function _isActive()
    {
        return (false !== $this->_getServerUrl()) && (false !== $this->_getClientId()) && (false !== $this->_getClientSecret());
    }

    /**
     * @param string $src
     * @return string
     */
    protected function _loadAsynchronousJavascript($src)
    {
        $src = json_encode($src);
        return "<script>(function(d,t,s,f){s=d.createElement(t);s.async=1;s.src={$src};f=d.getElementsByTagName(t)[0];f.parentNode.insertBefore(s,f)})(document,'script')</script>";
    }

    /**
     * @param string $url
     * @param string $body
     */
    protected function _webhook($url, $body)
    {
        $url = (string)$url;
        $body = (string)$body;
        $optionList = array(
            CURLOPT_POST => true,
            CURLOPT_URL => $url,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => 3000,
            CURLOPT_POSTFIELDS => $body,
        );
        $this->_executeOnShutdown(function () use ($optionList) {
            $ch = curl_init();
            curl_setopt_array($ch, $optionList);
            curl_exec($ch);
            curl_close($ch);
        });
    }
}

class WC_Darwin_Pricing_Order
{

    /** @var string|null */
    protected $_currency, $_customerId, $_customerIp, $_email, $_orderId, $_orderReference;

    /** @var float|null */
    protected $_shippingAmount, $_shippingVatRate, $_taxes, $_total;

    /** @var string[]|null */
    protected $_couponList;

    /** @var array|null */
    protected $_itemList;

    /**
     * @param string $couponCode The coupon code redeemed for this order
     */
    public function addCoupon($couponCode)
    {
        $couponCode = (string)$couponCode;
        $couponList = (array)$this->_getCouponList();
        $couponList[] = $couponCode;
        $this->_setCouponList($couponList);
    }

    /**
     * @param float $unitPrice The unit price of this item (including VAT when applicable)
     * @param int $quantity The number of items sold for this order
     * @param string|null $sku Your SKU for this item
     * @param string|null $productId The item's internal product ID in your eCommerce system
     * @param string|null $variantId The item's internal variant ID in your eCommerce system
     * @param float|null $unitCost Your average unit costs to purchase or produce this item
     * @param float|null $vatRate The Value Added Tax rate in percent for this item (when applicable)
     */
    public function addItem($unitPrice, $quantity, $sku = null, $productId = null, $variantId = null, $unitCost = null, $vatRate = null)
    {
        $unitPrice = (float)$unitPrice;
        $quantity = (int)$quantity;
        if (null !== $sku) {
            $sku = (string)$sku;
        }
        if (null !== $productId) {
            $productId = (string)$productId;
        }
        if (null !== $variantId) {
            $variantId = (string)$variantId;
        }
        if (null !== $unitCost) {
            $unitCost = (float)$unitCost;
        }
        if (null !== $vatRate) {
            $vatRate = (float)$vatRate;
        }
        $item = array(
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
        );
        if (null !== $sku) {
            $item['sku'] = $sku;
        }
        if (null !== $productId) {
            $item['product_id'] = $productId;
        }
        if (null !== $variantId) {
            $item['variant_id'] = $variantId;
        }
        if (null !== $unitCost) {
            $item['unit_cost'] = $unitCost;
        }
        if (null !== $vatRate) {
            $item['vat_rate'] = $vatRate;
        }
        $itemList = (array)$this->_getItemList();
        $itemList[] = $item;
        $this->_setItemList($itemList);
    }

    /**
     * @return string|null
     */
    public function getCurrency()
    {
        return $this->_currency;
    }

    /**
     * @param string|null $currency The currency code for this order (3 letters code according to ISO 4217)
     */
    public function setCurrency($currency)
    {
        if (null !== $currency) {
            $currency = (string)$currency;
        }
        $this->_currency = $currency;
    }

    /**
     * @return string|null
     */
    public function getCustomerId()
    {
        return $this->_customerId;
    }

    /**
     * @param string|null $customerId Your reference for this customer
     */
    public function setCustomerId($customerId)
    {
        if (null !== $customerId) {
            $customerId = (string)$customerId;
        }
        $this->_customerId = $customerId;
    }

    /**
     * @return string|null
     */
    public function getCustomerIp()
    {
        return $this->_customerIp;
    }

    /**
     * @param string|null $customerIp The IP address of this customer
     */
    public function setCustomerIp($customerIp)
    {
        if (null !== $customerIp) {
            $customerIp = (string)$customerIp;
        }
        $this->_customerIp = $customerIp;
    }

    /**
     * @return string|null
     */
    public function getEmail()
    {
        return $this->_email;
    }

    /**
     * @param string|null $email The e-mail address of this customer
     */
    public function setEmail($email)
    {
        if (null !== $email) {
            $email = (string)$email;
        }
        $this->_email = $email;
    }

    /**
     * @return string|null
     */
    public function getOrderId()
    {
        return $this->_orderId;
    }

    /**
     * @param string|null $orderId The internal ID of this order in your eCommerce system
     */
    public function setOrderId($orderId)
    {
        if (null !== $orderId) {
            $orderId = (string)$orderId;
        }
        $this->_orderId = $orderId;
    }

    /**
     * @return string|null
     */
    public function getOrderReference()
    {
        return $this->_orderReference;
    }

    /**
     * @param string|null $orderReference Your reference for this order
     */
    public function setOrderReference($orderReference)
    {
        if (null !== $orderReference) {
            $orderReference = (string)$orderReference;
        }
        $this->_orderReference = $orderReference;
    }

    /**
     * @return float|null
     */
    public function getShippingAmount()
    {
        return $this->_shippingAmount;
    }

    /**
     * @param float|null $shippingAmount The shipping costs billed to your customer (including VAT when applicable)
     */
    public function setShippingAmount($shippingAmount)
    {
        if (null !== $shippingAmount) {
            $shippingAmount = (float)$shippingAmount;
        }
        $this->_shippingAmount = $shippingAmount;
    }

    /**
     * @return float|null
     */
    public function getShippingVatRate()
    {
        return $this->_shippingVatRate;
    }

    /**
     * @param float|null $shippingVatRate The Value Added Tax rate in percent for the shipping costs (when applicable)
     */
    public function setShippingVatRate($shippingVatRate)
    {
        if (null !== $shippingVatRate) {
            $shippingVatRate = (float)$shippingVatRate;
        }
        $this->_shippingVatRate = $shippingVatRate;
    }

    /**
     * @return float|null
     */
    public function getTaxes()
    {
        return $this->_taxes;
    }

    /**
     * @param float|null $taxes The amount of sales tax (not VAT) for this order
     */
    public function setTaxes($taxes)
    {
        if (null !== $taxes) {
            $taxes = (float)$taxes;
        }
        $this->_taxes = $taxes;
    }

    /**
     * @return float|null
     */
    public function getTotal()
    {
        return $this->_total;
    }

    /**
     * @param float|null $total The total amount billed to your customer (including taxes)
     */
    public function setTotal($total)
    {
        if (null !== $total) {
            $total = (float)$total;
        }
        $this->_total = $total;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = array(
            'coupon_list' => $this->_getCouponList(),
            'currency' => $this->getCurrency(),
            'customer_id' => $this->getCustomerId(),
            'customer_ip' => $this->getCustomerIp(),
            'email' => $this->getEmail(),
            'item_list' => $this->_getItemList(),
            'order_id' => $this->getOrderId(),
            'order_reference' => $this->getOrderReference(),
            'shipping_amount' => $this->getShippingAmount(),
            'shipping_vat_rate' => $this->getShippingVatRate(),
            'taxes' => $this->getTaxes(),
            'total' => $this->getTotal(),
        );
        return array_filter($data, array($this, '_isNotNull'));
    }

    /**
     * @return string
     */
    public function toJson()
    {
        $data = $this->toArray();
        if (empty($data)) {
            $data = new stdClass();
        }
        return json_encode($data);
    }

    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @return string[]|null
     */
    protected function _getCouponList()
    {
        return $this->_couponList;
    }

    /**
     * @param string[]|null $couponList
     */
    protected function _setCouponList(array $couponList = null)
    {
        $this->_couponList = $couponList;
    }

    /**
     * @return array|null
     */
    protected function _getItemList()
    {
        return $this->_itemList;
    }

    /**
     * @param array|null $itemList
     */
    protected function _setItemList(array $itemList = null)
    {
        $this->_itemList = $itemList;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function _isNotNull($value)
    {
        return null !== $value;
    }

    /**
     * @param WC_Order $wooCommerceOrder
     * @return WC_Darwin_Pricing_Order
     */
    public static function fromWooCommerce(WC_Order $wooCommerceOrder)
    {
        $order = new WC_Darwin_Pricing_Order();
        $order->setOrderId($wooCommerceOrder->get_id());
        $order->setCustomerIp($wooCommerceOrder->get_customer_ip_address());
        $customerId = $wooCommerceOrder->get_customer_id();
        if ($customerId) {
            $order->setCustomerId($customerId);
        }
        $order->setEmail($wooCommerceOrder->get_billing_email());
        $order->setCurrency($wooCommerceOrder->get_currency());
        /** @var WC_Order_Item_Product $lineItem */
        foreach ($wooCommerceOrder->get_items() as $lineItem) {
            $quantity = $lineItem->get_quantity();
            if (!$quantity) {
                continue;
            }
            $unitPrice = (float)$lineItem->get_subtotal() / $quantity;
            $sku = $lineItem->get_product()->get_sku();
            $productId = $lineItem->get_product_id();
            $variantId = $lineItem->get_variation_id();
            if (!$variantId) {
                $variantId = null;
            }
            $order->addItem($unitPrice, $quantity, $sku, $productId, $variantId);
        }
        $shippingAmount = 0.;
        /** @var WC_Order_Item_Shipping $shippingLine */
        foreach ($wooCommerceOrder->get_shipping_methods() as $shippingLine) {
            $shippingAmount += (float)$shippingLine->get_total();
        }
        if ($shippingAmount) {
            $order->setShippingAmount($shippingAmount);
        }
        foreach ($wooCommerceOrder->get_used_coupons() as $couponCode) {
            $order->addCoupon($couponCode);
        }
        $order->setTotal($wooCommerceOrder->get_total());
        $order->setTaxes($wooCommerceOrder->get_total_tax());
        return $order;
    }
}
