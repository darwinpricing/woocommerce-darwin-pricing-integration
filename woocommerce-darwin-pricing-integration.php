<?php
/**
 * Plugin Name: WooCommerce Darwin Pricing Integration
 * Plugin URI: http://wordpress.org/plugins/woocommerce-darwin-pricing-integration/
 * Description: Allows Darwin Pricing to add a geo-targeted coupon box and tracking code into WooCommerce store pages.
 * Author: Darwin Pricing
 * Author URI: http://darwinpricing.com
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: woocommerce-darwin-pricing-integration
 * Domain Path: languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Darwin_Pricing_Integration' ) ) :

/**
 * WooCommerce Darwin Pricing Integration main class.
 */
class WC_Darwin_Pricing_Integration {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks whith WooCommerce is installed.
		if ( class_exists( 'WC_Integration' ) && defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.1-beta-1', '>=' ) ) {
			include_once 'includes/class-wc-darwin-pricing.php';

			// Register the integration.
			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woocommerce-darwin-pricing-integration' );

		load_textdomain( 'woocommerce-darwin-pricing-integration', trailingslashit( WP_LANG_DIR ) . 'woocommerce-darwin-pricing-integration/woocommerce-darwin-pricing-integration-' . $locale . '.mo' );
		load_plugin_textdomain( 'woocommerce-darwin-pricing-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * WooCommerce fallback notice.
	 *
	 * @return string
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' . sprintf( __( 'WooCommerce Darwin Pricing depends on the last version of %s to work!', 'woocommerce-darwin-pricing-integration' ), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __( 'WooCommerce', 'woocommerce-darwin-pricing-integration' ) . '</a>' ) . '</p></div>';
	}

	/**
	 * Add a new integration to WooCommerce.
	 *
	 * @param  array $integrations WooCommerce integrations.
	 *
	 * @return array               Google Analytics integration.
	 */
	public function add_integration( $integrations ) {
		$integrations[] = 'WC_Darwin_Pricing';

		return $integrations;
	}
}

add_action( 'plugins_loaded', array( 'WC_Darwin_Pricing_Integration', 'get_instance' ), 0 );

endif;
