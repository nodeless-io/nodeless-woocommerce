<?php

declare( strict_types=1 );

namespace NodelessIO\WC\Helper;

use NodelessIO\Client\StoreClient;
use NodelessIO\Client\StoreInvoiceClient;

class ApiHelper {
    public bool $configured = false;
    public string $url;
    public string $apiKey;
    public string $storeId;

    public const API_URL = [
        'testnet' => 'https://testnet.nodeless.io',
        'production' => 'https://nodeless.io'
    ];

    public function __construct() {
        if ( $config = self::getConfig() ) {
            $this->url = $config['url'];
            $this->apiKey = $config['api_key'];
            $this->storeId = $config['store_id'];
            $this->configured = true;
        }
    }

    public static function getConfig(): array {
        $mode = get_option('nodeless_mode', 'testnet');
        $url = defined('NODELESS_HOST') ? NODELESS_HOST : self::API_URL[$mode];
        $key = get_option( 'nodeless_api_key' );
        if ( $url && $key ) {
            return [
                'url' => rtrim( $url, '/' ),
                'api_key' => $key,
                'store_id' => get_option( 'nodeless_store_id', null ),
            ];
        } else {
            return [];
        }
    }

    public static function checkApiConnection( string $host, string $apiKey, string $storeId ): bool {
        $client = new StoreClient( $host, $apiKey );
        if ( ! empty( $client->getStore( $storeId ) ) ) {
            return true;
        }

        return false;
    }

    /**
     * Checks if the provided API config already exists in options table.
     */
    public static function apiCredentialsExist( string $apiUrl, string $apiKey, string $storeId ): bool {
        if ( $config = self::getConfig() ) {
            if (
                $config['url'] === $apiUrl &&
                $config['api_key'] === $apiKey &&
                $config['store_id'] === $storeId
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a given invoice id has status of fully paid (settled) or paid late.
     */
    public static function invoiceIsFullyPaid( string $invoiceId ): bool {
        if ( $config = self::getConfig() ) {
            $client = new StoreInvoiceClient( $config['url'], $config['api_key'] );
            try {
                $invoice = $client->getInvoice( $config['store_id'], $invoiceId );

                return $invoice->isPaid() || $invoice->isOverpaid();
            } catch ( \Throwable $e ) {
                Logger::debug( 'Exception while checking if invoice settled ' . $invoiceId . ': ' . $e->getMessage() );
            }
        }

        return false;
    }

}
