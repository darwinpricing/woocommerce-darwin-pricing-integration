<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Darwin Pricing Integration
 *
 * Allows tracking code to be inserted into store pages.
 *
 * @class   WC_Darwin_Pricing
 * @extends WC_Integration
 */
class WC_Darwin_Pricing extends WC_Integration {

    /**
     * Init and hook in the integration.
     *
     * @return void
     */
    public function __construct() {
        $this->id = 'darwin_pricing';
        $this->method_title = __('Darwin Pricing', 'woocommerce-darwin-pricing-integration');
        $this->method_description = __('Darwin Pricing is a dynamic pricing software that provides real-time market monitoring, pricing optimization and a geo-targeted coupon box to eCommerce websites.', 'woocommerce-darwin-pricing-integration');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->dp_id = $this->get_option('dp_id');
        $this->dp_host = $this->get_option('dp_host');

        // Actions
        add_action('woocommerce_update_options_integration_darwin_pricing', array($this, 'process_admin_options'));

        // Tracking code
        add_action('wp_head', array($this, 'darwin_pricing_code_display'), 999999);
    }

    /**
     * Initialise Settings Form Fields
     *
     * @return void
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'dp_host' => array(
                'title' => __('API Server', 'woocommerce-darwin-pricing-integration'),
                'description' => __('Log into your Darwin Pricing account to find the URL of your API server, e.g. <code>https://api.darwinpricing.com</code>', 'woocommerce-darwin-pricing-integration'),
                'type' => 'text',
                'default' => 'https://api.darwinpricing.com'
            ),
            'dp_id' => array(
                'title' => __('Site ID', 'woocommerce-darwin-pricing-integration'),
                'description' => __('Log into your Darwin Pricing account to find the ID of your website, e.g. <code>123</code>', 'woocommerce-darwin-pricing-integration'),
                'type' => 'text',
                'default' => ''
            )
        );
    }

    /**
     * Display the tracking code
     *
     * @return string
     */
    public function darwin_pricing_code_display() {
        global $wp;
        $tracking = false;

        if ('' == $this->dp_id || '' == $this->dp_host) {
            return;
        }

        // Track orders
        if (is_order_received_page()) {
            $order_id = isset($wp->query_vars['order-received']) ? $wp->query_vars['order-received'] : 0;

            if (0 < $order_id && 1 != get_post_meta($order_id, '_dp_tracked', true)) {
                $tracking = true;
                echo $this->get_tracking_code($order_id);
            }
        }

        if (!$tracking) {
            echo $this->get_widget_code();
        }
    }

    /**
     * Darwin Pricing widget
     *
     * @return string
     */
    protected function get_widget_code() {
        $url = $this->dp_host . '/widget?site-id=' . $this->dp_id;
        return '<script src="' . esc_url($url) . '" type="text/javascript"></script>';
    }

    /**
     * Darwin Pricing tracking
     *
     * @param int $order_id
     *
     * @return string
     */
    protected function get_tracking_code($order_id) {
        $order = new WC_Order($order_id);
        $currency = $order->get_order_currency();
        $value = $order->get_total() - $order->get_total_tax();

        // Mark the order as tracked
        update_post_meta($order_id, '_dp_tracked', 1);

        $url = $this->dp_host . '/add-payment?site-id=' . $this->dp_id . '&profit=' . $currency . $value;
        return '<script src="' . esc_url($url) . '" type="text/javascript"></script>';
    }

}
