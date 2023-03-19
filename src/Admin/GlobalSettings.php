<?php

declare( strict_types=1 );

namespace NodelessIO\WC\Admin;

use NodelessIO\WC\Helper\ApiHelper;
use NodelessIO\WC\Helper\ApiWebhook;
use NodelessIO\WC\Helper\Logger;
use NodelessIO\WC\Helper\OrderStates;

class GlobalSettings extends \WC_Settings_Page {

	public function __construct() {
		$this->id = 'nodeless_settings';
		$this->label = __( 'Nodeless.io Settings', 'nodeless-for-woocommerce' );
		// Register custom field type order_states with OrderStatesField class.
		add_action( 'woocommerce_admin_field_nodeless_order_states', [
			( new OrderStates() ),
			'renderOrderStatesHtml'
		] );

		if ( is_admin() ) {
			// Register and include JS.
			wp_register_script( 'nodeless_global_settings', NODELESS_PLUGIN_URL . 'assets/js/apiKeyRedirect.js', [ 'jquery' ], NODELESS_VERSION );
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
					'nodeless-for-woocommerce'
				),
				'type' => 'title',
				'desc' => sprintf( _x( 'This plugin version is %s and your PHP version is %s. Check out our <a href="https://wordpress.org/plugins/nodeless-for-woocommerce/#installation" target="_blank">installation instructions</a>. If you need assistance, please visit our <a href="https://support.nodeless.io" target="_blank">helpdesk</a>. Thank you for using Nodeless!', 'global_settings', 'nodeless-for-woocommerce' ), NODELESS_VERSION, PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ),
				'id' => 'nodeless'
			],
			'mode' => [
				'title' => esc_html_x(
					'Mode',
					'global_settings',
					'nodeless-for-woocommerce'
				),
				'type' => 'select',
				'desc' => esc_html_x( 'Select production or testnet mode.', 'global_settings', 'nodeless-for-woocommerce' ),
                'options'  => [
                    'production'    => esc_html_x('Production', 'global_settings', 'nodeless-for-woocommerce'),
                    'testnet' => esc_html_x('Testnet (for testing)', 'global_settings', 'nodeless-for-woocommerce'),
                ],
                'default'  => 'production',
				'desc_tip' => false,
				'id' => 'nodeless_mode'
			],
			'api_key' => [
				'title' => esc_html_x( 'API Key', 'global_settings', 'nodeless-for-woocommerce' ),
				'type' => 'text',
				'desc' => _x( 'Your Nodeless.io API Key. If you do not have any yet <a href="#" class="nodeless-api-key-link" target="_blank">click here to generate API keys.</a>', 'global_settings', 'nodeless-for-woocommerce' ),
				'default' => '',
				'id' => 'nodeless_api_key'
			],
			'store_id' => [
				'title' => esc_html_x( 'Store ID', 'global_settings', 'nodeless-for-woocommerce' ),
				'type' => 'text',
				'desc_tip' => _x( 'Your Nodeless.io Store ID. You can find it on the store settings page on Nodeless.io.', 'global_settings', 'nodeless-for-woocommerce' ),
				'default' => '',
				'id' => 'nodeless_store_id'
			],
			'order_states' => [
				'type' => 'nodeless_order_states',
				'id' => 'nodeless_order_states'
			],
			'customer_data' => [
				'title' => __( 'Send customer data to Nodeless.io', 'nodeless-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'no',
				'desc' => _x( 'If you want customer name, address, etc. sent to Nodeless.io, enable this option. By default for privacy and GDPR reasons this is disabled.', 'global_settings', 'nodeless-for-woocommerce' ),
				'id' => 'nodeless_send_customer_data'
			],
			'sats_mode' => [
				'title' => __( 'Sats-Mode', 'nodeless-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'no',
				'desc' => _x( 'Makes Satoshis/Sats available as currency "SATS" (can be found in WooCommerce->Settings->General) and handles conversion to BTC before creating the invoice on Nodeless.io.', 'global_settings', 'nodeless-for-woocommerce' ),
				'id' => 'nodeless_sats_mode'
			],
			'debug' => [
				'title' => __( 'Debug Log', 'nodeless-for-woocommerce' ),
				'type' => 'checkbox',
				'default' => 'no',
				'desc' => sprintf( _x( 'Enable logging <a href="%s" class="button">View Logs</a>', 'global_settings', 'nodeless-for-woocommerce' ), Logger::getLogFileUrl() ),
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

			$mode = sanitize_key( $_POST['nodeless_mode'] );
            $apiUrl = defined('NODELESS_HOST') ? NODELESS_HOST : ApiHelper::API_URL[$mode];
			$apiKey = sanitize_text_field( $_POST['nodeless_api_key'] );
			$storeId = sanitize_text_field( $_POST['nodeless_store_id'] );

			// Check if the provided API key works.
			try {
				if ( Apihelper::checkApiConnection( $apiUrl, $apiKey, $storeId ) ) {
					Notice::addNotice( 'success', __( 'Successfully verified API key on nodeless.io', 'nodeless-for-woocommerce' ) );

                    // Set up a webhook.
                    if (ApiWebhook::webhookExists($apiUrl, $apiKey, $storeId) === false &&
                        ApiWebhook::registerWebhook( $apiUrl, $apiKey, $storeId) ) {
                        Notice::addNotice( 'success', __( 'Successfully created a webhook for your store.', 'nodeless-for-woocommerce' ) );
                    }
                } else {
					throw new \Exception( __( 'Could not verify permission for the API key and this store. Make sure both are correct.', 'nodeless-for-woocommerce' ) );
				}
			} catch ( \Throwable $e ) {
				$messageException = sprintf(
					__( 'Error fetching data for this API key from server. Please check if the key is valid. Error: %s', 'nodeless-for-woocommerce' ),
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
			! empty( $_POST['nodeless_mode'] ) &&
			! empty( $_POST['nodeless_api_key'] ) &&
			! empty( $_POST['nodeless_store_id'] )
		) {
			return true;
		}

		return false;
	}
}
