<?php
/**
 * Plugin Name:     Nodeless For Woocommerce
 * Plugin URI:      https://wordpress.org/plugins/nodelessio-for-woocommerce/
 * Description:     Nodeless.io is a bitcoin payment service which allows you to receive payments in Bitcoin.
 * Author:          Nodeless
 * Author URI:      https://nodeless.io
 * Text Domain:     nodeless-for-woocommerce
 * Domain Path:     /languages
 * Version:         1.0.1
 * Requires PHP:    8.0
 * Tested up to:    6.1
 * Requires at least: 5.6
 * WC requires at least: 6.0.0
 * WC tested up to: 7.4
 */

use NodelessIO\WC\Admin\Notice;
use NodelessIO\WC\Gateway\DefaultGateway;
use NodelessIO\WC\Helper\SatsMode;
use NodelessIO\WC\Helper\ApiHelper;
use NodelessIO\WC\Helper\Logger;

defined( 'ABSPATH' ) || exit();

define( 'NODELESS_VERSION', '1.0.1' );
define( 'NODELESS_VERSION_KEY', 'nodeless_version' );
define( 'NODELESS_PLUGIN_FILE_PATH', plugin_dir_path( __FILE__ ) );
define( 'NODELESS_PLUGIN_URL', plugin_dir_url(__FILE__ ) );
define( 'NODELESS_PLUGIN_ID', 'nodeless-for-woocommerce' );

class NodelessIOWCPlugin {

	private static $instance;

	public function __construct() {
		$this->includes();

		add_action('woocommerce_thankyou_nodeless', [$this, 'orderStatusThankYouPage'], 10, 1);

		if (is_admin()) {
			// Register our custom global settings page.
			add_filter(
				'woocommerce_get_settings_pages',
				function ($settings) {
					$settings[] = new \NodelessIO\WC\Admin\GlobalSettings();
					return $settings;
				}
			);

			$this->dependenciesNotification();
			$this->notConfiguredNotification();
		}
	}

	public function includes(): void {
		$autoloader = NODELESS_PLUGIN_FILE_PATH . 'vendor/autoload.php';
		if (file_exists($autoloader)) {
			/** @noinspection PhpIncludeInspection */
			require_once $autoloader;
		}

		// Make sure WP internal functions are available.
		if ( ! function_exists('is_plugin_active') ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Setup other dependencies.
		// Make SATS as currency available.
		if (get_option('nodeless_sats_mode') === 'yes') {
			SatsMode::instance();
		}
	}

	public static function initPaymentGateways($gateways): array {
		// We always load the default gateway that covers all payment methods available on NodelessIO.
		$gateways[] = DefaultGateway::class;

		return $gateways;
	}

    public static function enqueueScripts(): void {
        // Load CSS.
        wp_register_style('nodeless_payment', plugins_url('assets/css/nodeless-style.css',__FILE__ ));
        wp_enqueue_style('nodeless_payment');
    }

	/**
	 * Displays notice (and link to config page) on admin dashboard if the plugin is not configured yet.
	 */
	public function notConfiguredNotification(): void {
		if (!ApiHelper::getConfig()) {
			$message = sprintf(
				esc_html__(
					'Plugin not configured yet, please %1$sconfigure the plugin here%2$s',
					'nodeless-for-woocommerce'
				),
				'<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=nodeless_settings')) . '">',
				'</a>'
			);

			Notice::addNotice('error', $message);
		}
	}

	/**
	 * Checks and displays notice on admin dashboard if PHP version is too low or WooCommerce not installed.
	 */
	public function dependenciesNotification() {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			$versionMessage = sprintf( __( 'Your PHP version is %s but Nodeless.io Payment plugin requires version 8.0+.', 'nodeless-for-woocommerce' ), PHP_VERSION );
			Notice::addNotice('error', $versionMessage);
		}

		// Check if WooCommerce is installed.
		if ( ! is_plugin_active('woocommerce/woocommerce.php') ) {
			$wcMessage = __('WooCommerce seems to be not installed. Make sure you do before you activate NodelessIO Payment Gateway.', 'nodeless-for-woocommerce');
			Notice::addNotice('error', $wcMessage);
		}

		// Check if PHP cURL is available.
		if ( ! function_exists('curl_init') ) {
			$curlMessage = __('The PHP cURL extension is not installed. Make sure it is available otherwise this plugin will not work.', 'nodeless-for-woocommerce');
			Notice::addNotice('error', $curlMessage);
		}
	}

    /**
     * Override order thank you page.
     */
	public function orderStatusThankYouPage($order_id)
	{
		if (!$order = wc_get_order($order_id)) {
			return;
		}

		$title = _x('Payment Status', 'nodeless-for-woocommerce');

		$orderData = $order->get_data();
		$status = $orderData['status'];

		switch ($status)
		{
			case 'on-hold':
				$statusDesc = _x('Waiting for payment settlement', 'nodeless-for-woocommerce');
				break;
			case 'processing':
				$statusDesc = _x('Payment successful, order processing.', 'nodeless-for-woocommerce');
				break;
			case 'completed':
				$statusDesc = _x('Order completed', 'nodeless-for-woocommerce');
				break;
			case 'failed':
				$statusDesc = _x('Payment failed', 'nodeless-for-woocommerce');
				break;
			default:
				$statusDesc = _x(ucfirst($status), 'nodeless-for-woocommerce');
				break;
		}

		echo "
		<section class='woocommerce-order-payment-status'>
		    <h2 class='woocommerce-order-payment-status-title'>{$title}</h2>
		    <p><strong>{$statusDesc}</strong></p>
		</section>
		";
	}

	/**
	 * Gets the main plugin loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 */
	public static function instance(): \NodelessIOWCPlugin {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

// Start everything up.
function init_nodeless() {
	\NodelessIOWCPlugin::instance();
}

/**
 * Bootstrap stuff on init.
 */
add_action('init', function() {
	// Adding textdomain and translation support.
	load_plugin_textdomain('nodeless-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
	// Flush rewrite rules only once after activation.
	if ( ! get_option('nodeless_permalinks_flushed') ) {
		flush_rewrite_rules(false);
		update_option('nodeless_permalinks_flushed', 1);
	}
});

// Action links on plugin overview.
add_filter( 'plugin_action_links_nodeless-for-woocommerce/nodeless-for-woocommerce.php', function ( $links ) {

	// Settings link.
	$settings_url = esc_url( add_query_arg(
		[
		'page' => 'wc-settings',
		'tab' => 'nodeless_settings'
		],
		get_admin_url() . 'admin.php'
	) );

	$settings_link = "<a href='$settings_url'>" . __( 'Settings', 'nodeless-for-woocommerce' ) . '</a>';

	$logs_link = "<a target='_blank' href='" . Logger::getLogFileUrl() . "'>" . __('Debug log', 'nodeless-for-woocommerce') . "</a>";

	//$docs_link = "<a target='_blank' href='". esc_url('https://docs.nodeless.io/WooCommerce/') . "'>" . __('Docs', 'nodeless-for-woocommerce') . "</a>";

	$support_link = "<a target='_blank' href='". esc_url('https://chat.nodeless.io/') . "'>" . __('Support Chat', 'nodeless-for-woocommerce') . "</a>";

	array_unshift(
		$links,
		$settings_link,
		$logs_link,
		//$docs_link,
		$support_link
	);

	return $links;
} );

// Installation routine.
register_activation_hook( __FILE__, function() {
	update_option('nodeless_permalinks_flushed', 0);
	update_option( NODELESS_VERSION_KEY, NODELESS_VERSION );
});

// Initialize payment gateways and plugin.
add_filter( 'woocommerce_payment_gateways', [ 'NodelessIOWCPlugin', 'initPaymentGateways' ] );
add_action( 'plugins_loaded', 'init_nodeless', 0 );
add_action( 'wp_enqueue_scripts', [ 'NodelessIOWCPlugin', 'enqueueScripts' ]  );
