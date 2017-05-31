<?php

/**
 * Plugin Name: WooCommerce Darwin Pricing Integration
 * Plugin URI: http://wordpress.org/plugins/woocommerce-darwin-pricing-integration/
 * Description: Adds a geo-targeted coupon box with exit intent technology into your WooCommerce store. Tracks your profit to optimize your geo-targeting in real-time.
 * Author: Darwin Pricing
 * Author URI: https://www.darwinpricing.com
 * Version: 1.3.1
 * License: GPLv2
 * Text Domain: woocommerce-darwin-pricing-integration
 * Domain Path: languages/
 */
if (!defined('WPINC')) {
    exit;
}

/**
 * Darwin Pricing Integration for WooCommerce.
 */
class WC_Darwin_Pricing_Integration
{

    /**
     * Plugin version.
     *
     * @var string
     */
    const VERSION = '1.3.1';

    /**
     * Instance of this class.
     *
     * @var WC_Darwin_Pricing_Integration
     */
    protected static $_instance = null;

    /**
     * Initialize the plugin.
     */
    private function __construct()
    {
        add_action('init', array($this, 'load_plugin_textdomain'));

        if (class_exists('WC_Integration')) {
            include_once plugin_dir_path(__FILE__) . 'includes/class-wc-darwin-pricing.php';
            add_filter('woocommerce_integrations', array($this, 'add_integration'));
        } else {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }

    /**
     * Return an instance of this class.
     *
     * @return WC_Darwin_Pricing_Integration
     */
    public static function get_instance()
    {
        if (null == self::$_instance) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * Load the plugin text domain.
     */
    public function load_plugin_textdomain()
    {
        $locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-darwin-pricing-integration');
        load_textdomain('woocommerce-darwin-pricing-integration', trailingslashit(WP_LANG_DIR) . 'woocommerce-darwin-pricing-integration/woocommerce-darwin-pricing-integration-' . $locale . '.mo');
        load_plugin_textdomain('woocommerce-darwin-pricing-integration', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Error message.
     */
    public function woocommerce_missing_notice()
    {
        echo '<div class="error"><p>' . sprintf(__('The Darwin Pricing integration requires %s.', 'woocommerce-darwin-pricing-integration'), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __('WooCommerce 3.0.0', 'woocommerce-darwin-pricing-integration') . '</a>') . '</p></div>';
    }

    /**
     * Add a new integration to WooCommerce.
     *
     * @param array $integrations WooCommerce integrations.
     *
     * @return array WooCommerce integrations including Darwin Pricing.
     */
    public function add_integration($integrations)
    {
        $integrations[] = 'WC_Darwin_Pricing';
        return $integrations;
    }

}

add_action('plugins_loaded', array('WC_Darwin_Pricing_Integration', 'get_instance'), 0);
