<?php

namespace NodelessIO\WC\Gateway;

/**
 * Default Gateway.
 */
class DefaultGateway extends AbstractGateway {

	public function __construct() {
		// Set the id first.
		$this->id = 'nodeless';

		// Call parent constructor.
		parent::__construct();

		// General gateway setup.
		$this->order_button_text = $this->get_option( 'button', __( 'Proceed with payment', 'nodeless-for-woocommerce' ) );
		// Admin facing title and description.
		$this->method_title = 'Nodeless.io';
		$this->method_description = __( 'Nodeless.io default gateway supporting easy Bitcoin payments.', 'nodeless-for-woocommerce' );

		// Actions.
		add_action( 'woocommerce_api_nodeless', [ $this, 'processWebhook' ] );
        add_filter( 'woocommerce_gateway_icon', [ $this, 'adjustIcon'], 10, 3 );
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->get_option( 'title', 'Pay with Bitcoin/Lightning Network' );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription(): string {
		return $this->get_option( 'description', 'You will be redirected to Nodeless.io to complete your purchase.' );
	}

	/**
	 * @inheritDoc
	 */
	public function init_form_fields(): void {
		parent::init_form_fields();
	}

    /**
     * Wrap div around icon for better flexbox handling.
     */
    public function adjustIcon($icon, $gateway_id) {
        if ($gateway_id === 'nodeless') {
            return '<div class="nodeless-woocommerce-icon">' . $icon . '</div>';
        }
    }

}
