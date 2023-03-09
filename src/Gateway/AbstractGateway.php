<?php

declare( strict_types=1 );

namespace NodelessIO\WC\Gateway;

use NodelessIO\Client\StoreInvoiceClient;
use NodelessIO\Response\StoreInvoiceResponse;
use NodelessIO\WC\Helper\ApiHelper;
use NodelessIO\WC\Helper\ApiWebhook;
use NodelessIO\WC\Helper\Logger;
use NodelessIO\WC\Helper\OrderStates;

abstract class AbstractGateway extends \WC_Payment_Gateway {
	const ICON_MEDIA_OPTION = 'icon_media_id';

	protected ApiHelper $apiHelper;

	public function __construct() {
		// General gateway setup.
		$this->icon = $this->getIcon();
		$this->has_fields = false;
		$this->order_button_text = __( 'Proceed with payment', 'nodeless-for-woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user facing set variables.
		$this->title = $this->getTitle();
		$this->description = $this->getDescription();

		$this->apiHelper = new ApiHelper();
		// Debugging & informational settings.
		$this->debug_php_version = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
		$this->debug_plugin_version = NODELESS_VERSION;

		// Actions.
		add_action( 'admin_enqueue_scripts', [ $this, 'addScripts' ] );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->getId(), [
			$this,
			'process_admin_options'
		] );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title' => __( 'Enabled/Disabled', 'nodeless-for-woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enable this payment gateway.', 'nodeless-for-woocommerce' ),
				'default' => 'no',
				'value' => 'yes',
				'desc_tip' => false,
			],
			'title' => [
				'title' => __( 'Title', 'nodeless-for-woocommerce' ),
				'type' => 'text',
				'description' => __( 'Controls the name of this payment method as displayed to the customer during checkout.', 'nodeless-for-woocommerce' ),
				'default' => $this->getTitle(),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __( 'Customer Message', 'nodeless-for-woocommerce' ),
				'type' => 'textarea',
				'description' => __( 'Message to explain how the customer will be paying for the purchase.', 'nodeless-for-woocommerce' ),
				'default' => $this->getDescription(),
				'desc_tip' => true,
			],
			'button' => [
				'title' => __( 'Button text', 'nodeless-for-woocommerce' ),
				'type' => 'textarea',
				'description' => __( 'Button text below the checkout form.', 'nodeless-for-woocommerce' ),
				'default' => __( 'Proceed with payment.', 'nodeless-for-woocommerce' ),
				'desc_tip' => true,
			],
			'icon_upload' => [
				'type' => 'icon_upload',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function process_payment( $orderId ) {
		if ( ! $this->apiHelper->configured ) {
			Logger::debug( 'Nodeless.io API connection not configured, aborting. Please go to Nodeless.io settings and set it up.' );
			throw new \Exception( __( "Can't process order. Please contact us if the problem persists.", 'nodeless-for-woocommerce' ) );
		}

		// Load the order and check it.
		$order = new \WC_Order( $orderId );
		if ( $order->get_id() === 0 ) {
			$message = 'Could not load order id ' . $orderId . ', aborting.';
			Logger::debug( $message, true );
			throw new \Exception( $message );
		}

		// Check for existing invoice and redirect instead.
		if ( $this->validInvoiceExists( $orderId ) ) {
			$existingInvoiceId = get_post_meta( $orderId, 'Nodeless_id', true );
			Logger::debug( 'Found existing Nodeless.io invoice and redirecting to it. Invoice id: ' . $existingInvoiceId );

			return [
				'result' => 'success',
				'redirect' => get_post_meta( $orderId, 'Nodeless_checkoutLink', true ),
			];
		}

		// Create an invoice.
		Logger::debug( 'Creating invoice on Nodeless.io' );
		if ( $invoice = $this->createInvoice( $order ) ) {
			Logger::debug( 'Invoice creation successful, redirecting user to nodeless.io.' );
			$url = $invoice->getData()['checkoutLink'];

			return [
				'result' => 'success',
				'redirect' => $url,
			];
		}
	}

	/**
	 * Process admin options and make sure custom icon upload works.
	 */
	public function process_admin_options() {
		// Store media id.
		$iconFieldName = 'woocommerce_' . $this->getId() . '_' . self::ICON_MEDIA_OPTION;
		if ( $mediaId = sanitize_key( $_POST[ $iconFieldName ] ) ) {
			if ( $mediaId !== $this->get_option( self::ICON_MEDIA_OPTION ) ) {
				$this->update_option( self::ICON_MEDIA_OPTION, $mediaId );
			}
		} else {
			// Reset to empty otherwise.
			$this->update_option( self::ICON_MEDIA_OPTION, '' );
		}

		return parent::process_admin_options();
	}

	/**
	 * Generate html for handling icon uploads with media manager.
	 *
	 * Note: `generate_$type_html()` is a pattern you can use from WooCommerce Settings API to render custom fields.
	 */
	public function generate_icon_upload_html() {
		$mediaId = $this->get_option( self::ICON_MEDIA_OPTION );
		$mediaSrc = '';
		if ( $mediaId ) {
			$mediaSrc = wp_get_attachment_image_src( $mediaId )[0];
		}
		$iconFieldName = 'woocommerce_' . $this->getId() . '_' . self::ICON_MEDIA_OPTION;

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo __( 'Gateway Icon:', 'nodeless-for-woocommerce' ); ?></th>
			<td class="forminp" id="nodeless_icon">
				<div id="nodeless_icon_container">
					<input class="nodeless-icon-button" type="button"
						   name="woocommerce_nodeless_icon_upload_button"
						   value="<?php echo __( 'Upload or select icon', 'nodeless-for-woocommerce' ); ?>"
						   style="<?php echo $mediaId ? 'display:none;' : ''; ?>"
					/>
					<img class="nodeless-icon-image" src="<?php echo esc_url( $mediaSrc ); ?>"
						 style="<?php echo esc_attr( $mediaId ) ? '' : 'display:none;'; ?>"/>
					<input class="nodeless-icon-remove" type="button"
						   name="woocommerce_nodeless_icon_button_remove"
						   value="<?php echo __( 'Remove image', 'nodeless-for-woocommerce' ); ?>"
						   style="<?php echo $mediaId ? '' : 'display:none;'; ?>"
					/>
					<input class="input-text regular-input nodeless-icon-value" type="hidden"
						   name="<?php echo esc_attr( $iconFieldName ); ?>"
						   id="<?php echo esc_attr( $iconFieldName ); ?>"
						   value="<?php echo esc_attr( $mediaId ); ?>"
					/>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get custom gateway icon, if any.
	 */
	public function getIcon(): string {
		$icon = null;
		if ( $mediaId = $this->get_option( self::ICON_MEDIA_OPTION ) ) {
			if ( $customIcon = wp_get_attachment_image_src( $mediaId )[0] ) {
				$icon = $customIcon;
			}
		}

		return $icon ?? NODELESS_PLUGIN_URL . 'assets/images/bitcoin-logo.png';
	}

	/**
	 * Add scripts.
	 */
	public function addScripts( $hook_suffix ) {
		if ( $hook_suffix === 'woocommerce_page_wc-settings' ) {
			wp_enqueue_media();
			wp_register_script(
				'nodeless_abstract_gateway',
				NODELESS_PLUGIN_URL . 'assets/js/gatewayIconMedia.js',
				[ 'jquery' ],
				NODELESS_VERSION
			);
			wp_enqueue_script( 'nodeless_abstract_gateway' );
			wp_localize_script(
				'nodeless_abstract_gateway',
				'nodelessGatewayData',
				[
					'buttonText' => __( 'Use this image', 'nodeless-for-woocommerce' ),
					'titleText' => __( 'Insert image', 'nodeless-for-woocommerce' ),
				]
			);
		}
	}

	/**
	 * Process webhooks from Nodeless.io.
	 */
	public function processWebhook() {
		$rawPostData = file_get_contents( "php://input" );
		Logger::debug( 'Webhook data received: input: ' . print_r( $rawPostData, true ) );
		Logger::debug( 'Webhook headers: ' . print_r( getallheaders(), true ) );

		if ( $rawPostData ) {
			// Validate webhook request.
			// Note: getallheaders() CamelCases all headers for PHP-FPM/Nginx but for others maybe not, so "NodelessIO-Sig" may becomes "Nodelessio-Sig".
			$headers = getallheaders();
			foreach ( $headers as $key => $value ) {
				if ( strtolower( $key ) === 'nodeless-signature' ) {
					$signature = $value;
				}
			}

			if ( ! isset( $signature ) || ! ApiWebhook::validWebhookRequest( $signature, $rawPostData ) ) {
				Logger::debug( 'Failed to validate signature of webhook request.' );
				wp_die( 'Webhook request validation failed.' );
			}

			try {
				$postData = json_decode( $rawPostData, false, 512, JSON_THROW_ON_ERROR );

				if ( ! isset( $postData->uuid ) ) {
					Logger::debug( 'No Nodeless.io invoiceId provided, aborting.' );
					wp_die( 'No Nodeless.io invoiceId provided, aborting.' );
				}

				// Load the order by metadata field Nodeless_id
				$orders = wc_get_orders( [
					'meta_key' => 'Nodeless_id',
					'meta_value' => $postData->uuid
				] );

				// Abort if no orders found.
				if ( count( $orders ) === 0 ) {
					Logger::debug( 'Could not load order by Nodeless.io invoiceId: ' . $postData->uuid );
					wp_die( 'No order found for this invoiceId.', '', [ 'response' => 200 ] );
				}

				// Abort on multiple orders found.
				if ( count( $orders ) > 1 ) {
					Logger::debug( 'Found multiple orders for invoiceId: ' . $postData->invoiceId );
					Logger::debug( print_r( $orders, true ) );
					wp_die( 'Multiple orders found for this invoiceId, aborting.' );
				}

				$this->processOrderStatus( $orders[0], $postData );

			} catch ( \Throwable $e ) {
				Logger::debug( 'Error decoding webook payload: ' . $e->getMessage() );
				Logger::debug( $rawPostData );
			}
		}
	}

	/**
	 * Change order status according to webhook status and backend configuration.
	 */
	protected function processOrderStatus( \WC_Order $order, \stdClass $webhookData ): void {
		if ( ! in_array( $webhookData->status, ApiWebhook::WEBHOOK_STATUSES ) ) {
			Logger::debug( 'Webhook status received but ignored: ' . $webhookData->status );
			return;
		}

		Logger::debug( 'Updating order status with webhook event received for processing: ' . $webhookData->status );
		// Get configured order states or fall back to defaults.
		if ( ! $configuredOrderStates = get_option( 'nodeless_order_states' ) ) {
			$configuredOrderStates = ( new OrderStates() )->getDefaultOrderStateMappings();
		}

		switch ( $webhookData->status ) {

			case 'new':
				Logger::debug( 'Webhook for new invoice received.' );
				break;

			case 'pending_confirmation': // The invoice is paid in full.
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::PENDING_CONFIRMATION ] );
				$order->add_order_note( __( 'Invoice payment received fully, waiting for settlement.', 'nodeless-for-woocommerce' ) );
				break;

			case 'paid':
				Logger::debug( 'Invoice fully paid.' );
				$order->payment_complete();
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::PAID ] );
				$order->add_order_note( __( 'Invoice fully paid.', 'nodeless-for-woocommerce' ) );
				// Store additional data.
				$this->updateWCOrder( $order, $webhookData );
				break;

			case 'expired':
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::EXPIRED ] );
				$order->add_order_note( __( 'Invoice expired.', 'nodeless-for-woocommerce' ) );
				break;

			case 'cancelled':
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::CANCELLED ] );
				$order->add_order_note( __( 'Invoice was cancelled.', 'nodeless-for-woocommerce' ) );
				break;

			case 'underpaid':
				Logger::debug( 'Invoice underpaid.' );
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::UNDERPAID ] );
				$order->add_order_note( __( 'Invoice is underpaid. Needs manual checking', 'nodeless-for-woocommerce' ) );
				$this->updateWCOrder( $order, $webhookData );
				break;

			case 'overpaid':
				Logger::debug( 'Invoice ovderpaid.' );
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::UNDERPAID ] );
				$order->add_order_note( __( 'Invoice is overpaid. Needs manual checking', 'nodeless-for-woocommerce' ) );
				$this->updateWCOrder( $order, $webhookData );
				break;

			case 'in_flight':
				Logger::debug( 'Invoice in flight.' );
				$this->updateWCOrderStatus( $order, $configuredOrderStates[ OrderStates::IN_FLIGHT ] );
				$order->add_order_note( __( 'Invoice is in flight. Eventually needs manual checking if no paid status follows.', 'nodeless-for-woocommerce' ) );
				$this->updateWCOrder( $order, $webhookData );
				break;
		}
	}

	/**
	 * Checks if the order has already a Nodeless.io invoice set and checks if it is still
	 * valid to avoid creating multiple invoices for the same order on Nodeless.io end.
	 *
	 * @param int $orderId
	 *
	 * @return mixed Returns false if no valid invoice found or the invoice id.
	 */
	protected function validInvoiceExists( int $orderId ): bool {
		// Check order metadata for Nodeless_id.
		if ( $invoiceId = get_post_meta( $orderId, 'Nodeless_id', true ) ) {
			// Validate the order status on Nodeless.io server.
			$client = new StoreInvoiceClient( $this->apiHelper->url, $this->apiHelper->apiKey );
			try {
				Logger::debug( 'Trying to fetch existing invoice from Nodeless.io.' );
				$invoice = $client->getInvoice( $this->apiHelper->storeId, $invoiceId );
				$invalidStates = [ 'expired', 'cancelled' ];
				if ( in_array( $invoice->getData()['status'], $invalidStates ) ) {
					return false;
				} else {
					return true;
				}
			} catch ( \Throwable $e ) {
				Logger::debug( $e->getMessage() );
			}
		}

		return false;
	}

	/**
	 * Update WC order status (if a valid mapping is set).
	 */
	public function updateWCOrderStatus( \WC_Order $order, string $status ): void {
		if ( $status !== OrderStates::IGNORE ) {
			Logger::debug( 'Updating order status from ' . $order->get_status() . ' to ' . $status );
			$order->update_status( $status );
		}
	}

	/**
	 * Update the order with relevant additional data returned by the webhook.
	 */
	public function updateWCOrder( \WC_Order $order, \stdClass $webhookData ): void {
		// TODO: as soon as available: paidAmount, txid, preimate, etc.
		$order->update_meta_data( 'Nodeless_paidAmount', $webhookData->paidAmount ?? 0 );

		$order->save();
	}

	/**
	 * Create an invoice on Nodeless.io.
	 */
	public function createInvoice( \WC_Order $order ): ?\NodelessIO\Response\StoreInvoiceResponse {
		// In case some plugins customizing the order number we need to pass that along, defaults to internal ID.
		$orderNumber = $order->get_order_number();
		Logger::debug( 'Got order number: ' . $orderNumber . ' and order ID: ' . $order->get_id() );

		$metadata = [];

		// Prepare metadata.
		$metadata += $this->prepareMetadata( $order );

		// Send customer data only if option is set.
		if ( get_option( 'nodeless_send_customer_data' ) === 'yes' ) {
			$metadata += $this->prepareCustomerMetadata( $order );
		}

		// Redirect url.
		$redirectUrl = $this->get_return_url( $order );
		Logger::debug( 'Setting redirect url to: ' . $redirectUrl );

		$currency = $order->get_currency();
		$amount = (string) $order->get_total(); // unlike method signature suggests, it returns string.

		// Create the invoice on Nodeless.io.
		$client = new StoreInvoiceClient( $this->apiHelper->url, $this->apiHelper->apiKey );
		try {
			$invoice = $client->createInvoice(
				$this->apiHelper->storeId,
				$amount,
				$currency,
				$order->get_billing_email(),
				$redirectUrl,
				$metadata,
			);

			$this->updateOrderMetadata( $order->get_id(), $invoice );

			return $invoice;

		} catch ( \Throwable $e ) {
			Logger::debug( $e->getMessage(), true );
			throw new \Exception( __( "Can't process order. Please contact us if the problem persists.", 'nodeless-for-woocommerce' ) );
		}
	}

	/**
	 * Maps customer billing metadata.
	 */
	protected function prepareCustomerMetadata( \WC_Order $order ): array {
		return [
			'buyerEmail' => $order->get_billing_email(),
			'buyerName' => $order->get_formatted_billing_full_name(),
			'buyerAddress1' => $order->get_billing_address_1(),
			'buyerAddress2' => $order->get_billing_address_2(),
			'buyerCity' => $order->get_billing_city(),
			'buyerState' => $order->get_billing_state(),
			'buyerZip' => $order->get_billing_postcode(),
			'buyerCountry' => $order->get_billing_country()
		];
	}

	/**
	 * Prepare metadata.
	 */
	protected function prepareMetadata( $order ): array {
		return [
			'orderId' => $order->get_id(),
			'orderNo' => $order->get_order_number(),
			'orderUrl' => $order->get_edit_order_url(),
			'pluginVersion' => constant( 'NODELESS_VERSION' )
		];
	}

	/**
	 * References WC order metadata with Nodeless.io invoice data.
	 */
	protected function updateOrderMetadata( int $orderId, StoreInvoiceResponse $invoice ) {
		// Store relevant Nodeless.io invoice data.
		update_post_meta( $orderId, 'Nodeless_checkoutLink', $invoice->getData()['checkoutLink'] );
		update_post_meta( $orderId, 'Nodeless_id', $invoice->getData()['id'] );
	}

	/**
	 * Get customer visible gateway title.
	 */
	public function getTitle(): string {
		return $this->get_option( 'title', 'Bitcoin, Lightning Network' );
	}

	/**
	 * Get customer facing gateway description.
	 */
	public function getDescription(): string {
		return $this->get_option( 'description', 'You will be redirected to Nodeless.io to complete your purchase.' );
	}

}
