<?php

declare(strict_types=1);

namespace NodelessIO\WC\Helper;

use NodelessIO\Response\StoreWebhookResponse;
use NodelessIO\Client\StoreWebhookClient;

class ApiWebhook {
    public const WEBHOOK_STATUSES = [
        'pending_confirmation',
        'paid',
        'expired',
        'cancelled',
        'underpaid',
        'overpaid',
        'in_flight'
    ];

	/**
	 * Get locally stored webhook data and check if it exists on the store.
	 */
	public static function webhookExists(string $apiUrl, string $apiKey, string $storeId): bool {
		if ( $storedWebhook = get_option( 'nodeless_webhook' ) ) {
			try {
				$client = new StoreWebhookClient( $apiUrl, $apiKey );
				$existingWebhook = $client->getWebhook( $storeId, $storedWebhook['id'] );
				// Check for the url here as it could have been changed on BTCPay Server making the webhook not work for WooCommerce anymore.
				if (
					$existingWebhook->getId() === $storedWebhook['id'] &&
					strpos( $existingWebhook->getData()['url'], $storedWebhook['url'] ) !== false
				) {
					return true;
				}
			} catch (\Throwable $e) {
				Logger::debug('Error fetching existing Webhook from nodeless.io. Message: ' . $e->getMessage());
			}
		}

		return false;
	}

	/**
	 * Register a webhook on BTCPay Server and store it locally.
	 */
	public static function registerWebhook(string $apiUrl, $apiKey, $storeId): ?StoreWebhookResponse {
		try {
			$client = new StoreWebhookClient( $apiUrl, $apiKey );
            $secret = wp_generate_password(20);
            $url = WC()->api_request_url( 'nodeless' );

            $webhook = $client->createWebhook(
                $storeId,
                'store',
                $url,
                self::WEBHOOK_STATUSES,
                $secret,
                'active'
            );

			// Store in option table.
			update_option(
				'nodeless_webhook',
				[
					'id' => $webhook->getId(),
					'secret' => $secret,
					'url' => $url
				]
			);

			return $webhook;
		} catch (\Throwable $e) {
			Logger::debug('Error creating a new webhook nodeless.io: ' . $e->getMessage());
		}

		return null;
	}

	/**
	 * Load existing webhook data from BTCPay Server, defaults to locally stored webhook.
	 */
	public static function getWebhook(?string $webhookId): ?StoreWebhookResponse {
		$existingWebhook = get_option('nodeless_webhook');
		$config = ApiHelper::getConfig();

		try {
			$client = new StoreWebhookClient( $config['url'], $config['api_key'] );
			$webhook = $client->getWebhook(
				$config['store_id'],
				$webhookId ?? $existingWebhook['id'],
				);

			return $webhook;
		} catch (\Throwable $e) {
			Logger::debug('Error fetching existing Webhook from nodeless.io: ' . $e->getMessage());
		}

		return null;
	}

    /**
     * Check webhook signature to be a valid request.
     */
    public static function validWebhookRequest( string $signature, string $requestData ): bool {
        $storedWebhook = get_option( 'nodeless_webhook' );
        Logger::debug( __FUNCTION__ . ' Signature: ' . $signature );
        Logger::debug( __FUNCTION__ . ' Configured: ' . $storedWebhook['secret'] );
        if ( ApiHelper::getConfig() ) {
            $expectedHeader = hash_hmac( 'sha256', $requestData, $storedWebhook['secret'] );
            Logger::debug( __FUNCTION__ . ' Expected header: ' . $expectedHeader );

            if ( $expectedHeader === $signature ) {
                return true;
            }
        }

        return false;
    }

}
