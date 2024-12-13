# HealthKey Gateway for WooCommerce

Contributors: Goodbody Wellness
Tags: woocommerce, HealthKey
Requires at least: 4.8.3
Tested up to: 9.4.3
Stable tag: 1.0.0
License: MIT License

Provide HealthKey as a payment option for WooCommerce orders.

## Description

This plugin is for health care providers on the HealthKey platform. If you would like to find out more about joining HealthKey visit [our website](https://www.healthkey.health/industry/providers)

Give your customers the option to pay with HealthKey . The "HealthKey Gateway for WooCommerce" plugin provides the option to choose HealthKey as the payment method at the checkout. It also provides the functionality to display the HealthKey logo on the cart page. For each payment that is authorised by HealthKey, an order will be created inside the WooCommerce system like any other order. When the customer authorises your site to use HealthKey, an order is created and set to pending. When the customer subsequently authorises the payment, the order is changed to processing.

This plugin supports both the new Checkout Block and the Classic Checkout block.

## End User Installation

### Prerequisites

This plugin relies on `wp-json` as it appends routes to the rest API. For this to be active the Permalink setting for the Wordpress installation must not be Plain. There are [instructions for how to change this setting](https://stackoverflow.com/a/53929736). Failure to do so will lead to errors when going through the checkout flow. If you cannot change this setting reach out to HealthKey.

### Installation

This section outlines the steps to install the HealthKey plugin.

1. Go to https://github.com/HealthKey-Company/woocommerce-plugin
2. Click on Code, download Zip
3. Go to the WP-Admin section of your Wordpress site
4. Go to the plugin section
5. Add New
6. Upload Plugin
7. Choose the downloaded zip file and upload
8. Activate the HealthKey plugin
9. Navigate to "WooCommerce > Settings".
10. Click the "Payment" tab.
11. Click the "HealthKey Payment" manage button.
12. Enter the CLIENT_ID, CLIENT_SECRET, AUTH_HOSTNAME and SERVER_API_HOSTNAME that were provided by HealthKey for Production use.
13. Tick Enable HealthKey.
14. Save changes.

## Plugin Development

1. Run `npm install`
2. Clone Woocommerce https://github.com/woocommerce/woocommerce
3. Ensure `.wp-env.json` points to the woocommerce plugin you have locally
4. To start the wordpress environment run `npx wp-env start`
5. Run `npm start` to continuously rebuild changes to the block
