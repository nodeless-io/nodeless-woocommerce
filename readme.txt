=== Nodeless for WooCommerce ===
Contributors: nodeless,ndeet
Tags: Bitcoin, Lightning Network, WooCommerce, payment gateway, accept bitcoin, bitcoin plugin, bitcoin payment processor, bitcoin e-commerce, cryptocurrency
Requires at least: 5.9
Tested up to: 6.1
Requires PHP: 8.0
Stable tag: 1.0.1
License: MIT
License URI: https://github.com/nodeless-io/nodeless-woocommerce/blob/master/license.txt

Nodeless is a Bitcoin payment processor which allows you to receive payments in Bitcoin on-chain and over the Lightning network.

== Description ==

Accept Bitcoin and Lightning payments in your online store, charity or fundraiser, all without all the complexities of managing a lightning node. Get payments sent directly to your cold storage or lightning address.

== Installation ==

This plugin requires WooCommerce. Please make sure you have WooCommerce installed.

To integrate Nodeless into an existing WooCommerce store, follow the steps below:


### 1. Install Nodeless for WooCommerce Plugin ###

* Make sure you have signed up for an account on https://nodeless.io
* In WordPress Admin: go to Plugins -> Add new:
  * Option 1: search for "Nodeless for WooCommerce", install and activate it
  * Option 2: download the latest release .zip from https://github.com/nodeless-io/nodeless-woocommerce/releases
* You can find the Nodeless Settings in WooCommerce -> Settings -> Nodeless Settings
* You need to enter at least your API token (whcih you can get from your Nodeless account settings page)
* After saving you should see a success notification that a webhook was created and you are good to go.

### 2. Testing the checkout ###

Making a small test-purchase from your own store, will give you a piece of mind. Always make sure that everything is set up correctly before going live.

== Frequently Asked Questions ==

Q: Can I test the service without real money?
A: Yes you can go to https://testnet.nodeless.io and use it with testnet bitcoin

== Changelog ==

= 1.0.1 :: 2023-03-19 =
* Testing deployment flow with another release.

= 1.0.0 :: 2023-03-16 =
* First release.
