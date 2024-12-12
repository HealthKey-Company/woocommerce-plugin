<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Healthkey_Blocks_Support extends AbstractPaymentMethodType {

    /**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = 'healthkey_payment';

    public function initialize() {
    }

    public function is_active() {
        return true;
    }

    /**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
            'healthkey_gateway-blocks-integration',
            plugin_dir_url(__FILE__) . 'build/index.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ],
            null,
            true
        );

        return [ 'healthkey_gateway-blocks-integration' ];
	}

    public function get_payment_method_data() {
        return ['supports' => $this->get_supported_features(),
                'icon' => plugins_url( 'assets/icon.png', __FILE__ )];
    }
}
