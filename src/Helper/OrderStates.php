<?php

declare( strict_types=1 );

namespace NodelessIO\WC\Helper;

/**
 * Helper class to render the order_states as a custom field in global settings form.
 */
class OrderStates {
	const NEW = 'new';
	const PENDING_CONFIRMATION = 'pending_confirmation';
	const PAID = 'paid';
	const EXPIRED = 'expired';
	const CANCELLED = 'cancelled';
	const OVERPAID = 'overpaid';
	const UNDERPAID = 'underpaid';
	const IN_FLIGHT = 'in_flight';
	const IGNORE = 'NODELESS_IGNORE';

	public function getDefaultOrderStateMappings(): array {
		return [
			self::NEW => 'wc-pending',
			self::PENDING_CONFIRMATION => 'wc-on-hold',
			self::PAID => self::IGNORE,
			self::EXPIRED => 'wc-cancelled',
			self::CANCELLED => 'wc-cancelled',
			self::UNDERPAID => 'wc-on-hold',
			self::OVERPAID => 'wc-processing',
			self::IN_FLIGHT => 'wc-on-hold'
		];
	}

	public function getOrderStateLabels(): array {
		return [
			self::NEW => _x( 'New', 'global_settings', 'nodeless-for-woocommerce' ),
			self::PENDING_CONFIRMATION => _x( 'Pending confirmation', 'global_settings', 'nodeless-for-woocommerce' ),
			self::PAID => _x( 'Paid', 'global_settings', 'nodeless-for-woocommerce' ),
			self::EXPIRED => _x( 'Expired', 'global_settings', 'nodeless-for-woocommerce' ),
			self::CANCELLED => _x( 'Cancelled', 'global_settings', 'nodeless-for-woocommerce' ),
			self::UNDERPAID => _x( 'Underpaid', 'global_settings', 'nodeless-for-woocommerce' ),
			self::OVERPAID => _x( 'Overpaid', 'global_settings', 'nodeless-for-woocommerce' ),
			self::IN_FLIGHT => _x( 'In flight', 'global_settings', 'nodeless-for-woocommerce' )
		];
	}

	public function renderOrderStatesHtml( $value ) {
		$nodelessStates = $this->getOrderStateLabels();
		$defaultStates = $this->getDefaultOrderStateMappings();

		$wcStates = wc_get_order_statuses();
		$wcStates = [ self::IGNORE => _x( '- no mapping / defaults -', 'global_settings', 'nodeless-for-woocommerce' ) ] + $wcStates;
		$orderStates = get_option( $value['id'] );
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">Order States:</th>
			<td class="forminp" id="<?php echo esc_attr( $value['id'] ) ?>">
				<table cellspacing="0">
					<?php

					foreach ( $nodelessStates as $nodelessState => $nodelessName ) {
						?>
						<tr>
							<th><?php echo esc_html( $nodelessName ); ?></th>
							<td>
								<select
									name="<?php echo esc_attr( $value['id'] ) ?>[<?php echo esc_html( $nodelessState ); ?>]">
									<?php

									foreach ( $wcStates as $wcState => $wcName ) {
										$selectedOption = $orderStates[ $nodelessState ];

										if ( true === empty( $selectedOption ) ) {
											$selectedOption = $defaultStates[ $nodelessState ];
										}

										if ( $selectedOption === $wcState ) {
											echo '<option value="' . esc_attr( $wcState ) . '" selected>' . esc_html( $wcName ) . '</option>' . PHP_EOL;
										} else {
											echo '<option value="' . esc_attr( $wcState ) . '">' . esc_html( $wcName ) . '</option>' . PHP_EOL;
										}
									}
									?>
								</select>
							</td>
						</tr>
						<?php
					}

					?>
				</table>
				<p class="description">
					<?php echo _x( 'By keeping default behavior for the "Paid" status you make sure that WooCommerce handles orders of virtual and only downloadable products properly and set those orders to "complete" instead of "processing" (like for orders containing physical products).', 'global_settings', 'nodeless-for-woocommerce' ); ?>
				</p>
			</td>
		</tr>
		<?php
	}
}
