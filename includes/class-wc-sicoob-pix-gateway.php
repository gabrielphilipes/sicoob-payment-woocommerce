<?php
/**
 * Sicoob PIX Payment Gateway
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Sicoob_Pix_Gateway extends WC_Payment_Gateway {

    /**
     * PIX Gateway Constructor
     */
    public function __construct() {
        $this->id = 'sicoob_pix';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('Sicoob PIX', 'sicoob-payment');
        $this->method_description = __('Aceite pagamentos via PIX através do Sicoob.', 'sicoob-payment');

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Set properties
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        // Save settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize form fields for configuration
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Habilitar/Desabilitar', 'sicoob-payment'),
                'type' => 'checkbox',
                'label' => __('Habilitar Sicoob PIX', 'sicoob-payment'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Nome', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Nome a ser apresentado no momento do checkout.', 'sicoob-payment'),
                'default' => __('PIX Sicoob', 'sicoob-payment'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Descrição', 'sicoob-payment'),
                'type' => 'textarea',
                'description' => __('Descrição a ser explicada para o cliente, antes de gerar o PIX.', 'sicoob-payment'),
                'default' => __('Pague com PIX de forma rápida e segura através do Sicoob.', 'sicoob-payment'),
                'desc_tip' => true,
            )
        );
    }

    /**
     * Process payment
     *
     * @param int $order_id Order ID
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
        }

        // Mark order as pending
        $order->update_status('pending', __('Aguardando pagamento via PIX.', 'sicoob-payment'));

        // Remove cart
        WC()->cart->empty_cart();

        // Return success
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    /**
     * Check if gateway is available
     *
     * @return bool
     */
    public function is_available() {
        if ($this->enabled === 'no') {
            return false;
        }

        return true;
    }
}
