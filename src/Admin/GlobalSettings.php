<?php

declare( strict_types=1 );

namespace NodelessIO\WC\Admin;

use NodelessIO\Client\StoreClient;
use NodelessIO\WC\Helper\ApiHelper;
use NodelessIO\WC\Helper\Logger;
use NodelessIO\WC\Helper\OrderStates;

class GlobalSettings extends \WC_Settings_Page {

	public function __construct() {
		$this->id = 'nodeless_settings';
		$this->label = __( 'Nodeless.io Settings', 'nodelessio-for-woocommerce' );
		// Register custom field type order_states with OrderStatesField class.
		add_action( 'woocommerce_admin_field_nodeless_order_states', [
			( new OrderStates() ),
			'renderOrderStatesHtml'
		] );

		if ( is_admin() ) {
			// Register and include JS.
			wp_register_script( 'nodeless_global_settings', NODELESSIO_PLUGIN_URL . 'assets/js/apiKeyRedirect.js', [ 'jquery' ], NODELESSIO_VERSION );
			wp_enqueue_script( 'nodeless_global_settings' );
			wp_localize_script( 'nodeless_global_settings',
				'NodelessGlobalSettings',
				[
					'url' => admin_url( 'admin-ajax.php' ),
					'apiNonce' => wp_create_nonce( 'nodeless-api-url-nonce' ),
				] );
		}
		parent::__construct();
	}

	public function output(): void {
		$settings = $this->get_settings_for_default_section();
		\WC_Admin_Settings::output_fields( $settings );
	}

	public function get_settings_for_default_section(): array {
		return $this->getGlobalSettings();
	}

	public function getGlobalSettings(): array {
		Logger::debug( 'Entering Global Settings form.' );

		return [
			'title' => [
				'title' => esc_html_x(
					'Nodeless.io Payments Settings',
					'global_settings',
					'nodelessio-for-woocommerce'
				),
				'type' => 'title',
				'desc' => sprintf( _x( 'This plugin version is %s and your PHP version is %s. Check out our <a href="https://docs.nodeless.io/WooCommerce/" target="_blank">installation instructions</a>. If you need assistance, please come on our <a href="https://chat.nodeless.io" target="_blank">chat</a>. Thank you for using Nodeless.io!', 'global_settings', 'nodelessio-for-woocommerce' ), NODELESSIO_VERSION, PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ),
				'id' => 'nodeless'
			],
			'url' => [
				'title' => esc_html_x(
					'URL',
					'global_settings',
					'nodelessio-for-woocommerce'
				),
				'type' => 'text',
				'desc' => esc_html_x( 'INTERNAL NOTE: URL/host to Nodeless.io instance. Will be replaced by testnet and mainnet switch.', 'global_settings', 'nodelessio-for-woocommerce' ),
				'placeholder' => esc_attr_x( 'e.g. https://nodeless.io', 'global_settings', 'nodelessio-for-woocommerce' ),
				'desc_tip' => false,
				'id' => 'nodeless_url'
			],
			'api_key' => [
				'title' => esc_html_x( 'API Key', 'global_settings', 'nodelessio-for-woocommerce' ),
				'type' => 'text',
				'desc' => _x( 'Your Nodeless.io API Key. If you do not have any yet <a href="#" class="nodeless-api-key-link" target="_blank">click here to generate API keys.</a>', 'global_settings', 'nodelessio-for-woocommerce' ),
				'default' => '',
				'id' => 'nodeless_api_key'
			],
			'store_id' => [
				'title' => esc_html_x( 'Store ID', 'global_settings', 'nodelessio-for-woocommerce' ),
				'type' => 'text',
				'desc_tip' => _x( 'Your Nodeless.io Store ID. You can find it on the store settings page on Nodeless.io.', 'global_settings', 'nodelessio-for-woocommerce' ),
				'default' => '',
				'id' => 'nodeless_store_id'
			],
			'webhook_url' => [
				'title' => esc_html_x( 'Webhook URL', 'global_settings', 'nodelessio-for-woocommerce' ),
				'type' => 'text',
				'desc_tip' => _x( 'Your webhook URL to be used in the weboook creation on store settings page on Nodeless.io.', 'global_settings', 'nodelessio-for-woocommerce' ),
				'default' => WC()->api_request_url( 'nodeless' ),
				'id' => 'nodeless_webhook_url',
				'custom_attributes' => [
					'readonly' => 'readonly'
				],

			],
			'webhook_secret' => [
				'title' => esc_html_x( 'Webhook secret', 'global_settings', 'nodelessio-for-woocommerce' ),
				'type' => 'text',
				'desc_tip' => _x( 'Your Nodeless.io Webhook Secret. Copy it when you create your webhook on Nodeless.io.', 'global_settings', 'nodelessio-for-woocommerce' ),
				'default' => '',
				'id' => 'nodeless_webhook_secret'
			],
			'order_states' => [
				'type' => 'nodeless_order_states',
				'id' => 'nodeless_order_states'
			],
			'customer_data' => [
				'title' => __( 'Send customer data to Nodeless.io', 'nodelessio-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'no',
				'desc' => _x( 'If you want customer name, address, etc. sent to Nodeless.io, enable this option. By default for privacy and GDPR reasons this is disabled.', 'global_settings', 'nodelessio-for-woocommerce' ),
				'id' => 'nodeless_send_customer_data'
			],
			'sats_mode' => [
				'title' => __( 'Sats-Mode', 'nodelessio-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'no',
				'desc' => _x( 'Makes Satoshis/Sats available as currency "SATS" (can be found in WooCommerce->Settings->General) and handles conversion to BTC before creating the invoice on Nodeless.io.', 'global_settings', 'nodelessio-for-woocommerce' ),
				'id' => 'nodeless_sats_mode'
			],
			'debug' => [
				'title' => __( 'Debug Log', 'nodelessio-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'no',
				'desc' => sprintf( _x( 'Enable logging <a href="%s" class="button">View Logs</a>', 'global_settings', 'nodelessio-for-woocommerce' ), Logger::getLogFileUrl() ),
				'id' => 'nodeless_debug'
			],
			'sectionend' => [
				'type' => 'sectionend',
				'id' => 'nodeless',
			],
		];
	}

	/**
	 * On saving the settings form make sure to check if the API key works and register a webhook if needed.
	 */
	public function save() {
		// If we have url, storeID and apiKey we want to check if the api key works and register a webhook.
		Logger::debug( 'Saving GlobalSettings.' );
		if ( $this->hasNeededApiCredentials() ) {

			$apiUrl = esc_url_raw( $_POST['nodeless_url'] );
			$apiKey = sanitize_text_field( $_POST['nodeless_api_key'] );
			$storeId = sanitize_text_field( $_POST['nodeless_store_id'] );

			// Check if the provided API key works.
			try {
				if ( Apihelper::checkApiConnection( $apiUrl, $apiKey, $storeId ) ) {
					Notice::addNotice( 'success', __( 'Successfully verified API key on nodeless.io', 'nodelessio-for-woocommerce' ) );
				} else {
					throw new \Exception( __( 'Could not verify permission for the API key and this store. Make sure both are correct.', 'nodelessio-for-woocommerce' ) );
				}
			} catch ( \Throwable $e ) {
				$messageException = sprintf(
					__( 'Error fetching data for this API key from server. Please check if the key is valid. Error: %s', 'nodelessio-for-woocommerce' ),
					$e->getMessage()
				);
				Notice::addNotice( 'error', $messageException );
				Logger::debug( $messageException, true );
			}

		} else {
			$messageNotConnecting = 'Did not try to connect to Nodeless.io API because one of the required information was missing: URL, key or storeID';
			Notice::addNotice( 'warning', $messageNotConnecting );
			Logger::debug( $messageNotConnecting );
		}

		parent::save();
	}

	private function hasNeededApiCredentials(): bool {
		if (
			! empty( $_POST['nodeless_url'] ) &&
			! empty( $_POST['nodeless_api_key'] ) &&
			! empty( $_POST['nodeless_store_id'] )
		) {
			return true;
		}

		return false;
	}
}
