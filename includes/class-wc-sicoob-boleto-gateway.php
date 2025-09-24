<?php
/**
 * Sicoob Boleto Payment Gateway
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Sicoob_Boleto_Gateway extends WC_Payment_Gateway {

    /**
     * Boleto Gateway Constructor
     */
    public function __construct() {
        $this->id = 'sicoob_boleto';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('Sicoob Boleto', 'sicoob-payment');
        $this->method_description = __('Aceite pagamentos via boleto bancário através do Sicoob.', 'sicoob-payment');

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
                'label' => __('Habilitar Sicoob Boleto', 'sicoob-payment'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Nome', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Nome a ser apresentado no momento do checkout.', 'sicoob-payment'),
                'default' => __('Boleto Sicoob', 'sicoob-payment'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Descrição', 'sicoob-payment'),
                'type' => 'textarea',
                'description' => __('Descrição a ser explicada para o cliente, antes de gerar o boleto.', 'sicoob-payment'),
                'default' => __('Pague com boleto bancário de forma segura através do Sicoob.', 'sicoob-payment'),
                'desc_tip' => true,
            ),
            'account_number' => array(
                'title' => __('Número da Conta Corrente', 'sicoob-payment'),
                'type' => 'tel',
                'description' => __('Número da conta corrente no Sicoob (somente números).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'pattern' => '[0-9]*',
                    'inputmode' => 'numeric'
                )
            ),
            'contract_number' => array(
                'title' => __('Número do Contrato', 'sicoob-payment'),
                'type' => 'tel',
                'description' => __('Número do contrato com o Sicoob (somente números).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'pattern' => '[0-9]*',
                    'inputmode' => 'numeric'
                )
            ),
            'due_days' => array(
                'title' => __('Dias para Vencimento', 'sicoob-payment'),
                'type' => 'tel',
                'description' => __('Número de dias para vencimento do boleto.', 'sicoob-payment'),
                'default' => '3',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'pattern' => '[0-9]*',
                    'inputmode' => 'numeric',
                    'min' => '1',
                    'max' => '30'
                )
            ),
            'instructions_section' => array(
                'title' => __('Instruções do Boleto', 'sicoob-payment'),
                'type' => 'title',
                'description' => __('Configure as instruções que aparecerão no boleto bancário.', 'sicoob-payment'),
            ),
            'instruction_1' => array(
                'title' => __('Instrução 1', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Primeira linha de instrução (máximo 40 caracteres).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'maxlength' => '40'
                )
            ),
            'instruction_2' => array(
                'title' => __('Instrução 2', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Segunda linha de instrução (máximo 40 caracteres).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'maxlength' => '40'
                )
            ),
            'instruction_3' => array(
                'title' => __('Instrução 3', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Terceira linha de instrução (máximo 40 caracteres).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'maxlength' => '40'
                )
            ),
            'instruction_4' => array(
                'title' => __('Instrução 4', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Quarta linha de instrução (máximo 40 caracteres).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'maxlength' => '40'
                )
            ),
            'instruction_5' => array(
                'title' => __('Instrução 5', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Quinta linha de instrução (máximo 40 caracteres).', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'maxlength' => '40'
                )
            ),
            'suggestions_button' => array(
                'title' => __('Sugestões de Instruções', 'sicoob-payment'),
                'type' => 'button',
                'description' => __('Clique para inserir sugestões de instruções pré-definidas.', 'sicoob-payment'),
                'class' => 'button button-secondary',
                'custom_attributes' => array(
                    'onclick' => 'sicoobInsertSuggestions()'
                )
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
        $order->update_status('pending', __('Aguardando pagamento via boleto.', 'sicoob-payment'));

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
