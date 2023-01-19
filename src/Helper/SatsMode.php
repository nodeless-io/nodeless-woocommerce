<?php

declare( strict_types=1 );

namespace NodelessIO\WC\Helper;

/**
 * Make SATS as a currency available.
 */
class SatsMode {

	private static $instance;

	public $currencies = [
		'SATS' => [ "Satoshis", "Sats" ]
	];

	public function __construct() {
		add_filter( 'woocommerce_currencies', [ $this, 'addCurrency' ] );
		add_filter( 'woocommerce_currency_symbol', [ $this, 'addSymbol' ], 10, 2 );
	}

	public function addCurrency( $currencies ) {
		foreach ( $this->currencies as $code => $curr ) {
			$currencies[ $code ] = __( $curr[0], 'nodelessio-for-woocommerce' );
		}

		return $currencies;
	}

	public function addSymbol( $symbol, $currency ) {
		if ( $currency === 'SATS' ) {
			$symbol = 'Sats';
		}

		return $symbol;
	}

	public static function instance(): SatsMode {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
