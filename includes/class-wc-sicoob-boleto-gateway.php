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

        // Add boleto payment block to thank you page
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'display_boleto_payment_block'));

        // Enqueue boleto scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_boleto_scripts'));
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
            ),
            'email_section' => array(
                'title' => __('Configurações de E-mail', 'sicoob-payment'),
                'type' => 'title',
                'description' => __('Configure o envio automático de e-mail com os dados do boleto.', 'sicoob-payment'),
            ),
            'send_email' => array(
                'title' => __('Enviar E-mail Automaticamente', 'sicoob-payment'),
                'type' => 'checkbox',
                'label' => __('Enviar e-mail com dados do boleto após a geração', 'sicoob-payment'),
                'default' => 'yes',
                'desc_tip' => true,
                'description' => __('Quando habilitado, um e-mail será enviado automaticamente ao cliente com os dados do boleto.', 'sicoob-payment')
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

        // Get boleto settings
        $account_number = $this->get_option('account_number');
        $contract_number = $this->get_option('contract_number');

        // Validate boleto settings
        if (empty($account_number) || empty($contract_number)) {
            wc_add_notice(__('Configurações do boleto não estão completas. Entre em contato com o administrador.', 'sicoob-payment'), 'error');
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
        }

        // Prepare order data for boleto creation
        $order_data = array(
            'order_id' => $order_id,
            'cpf' => $this->get_customer_cpf($order),
            'nome' => $this->get_customer_name($order),
            'valor' => $order->get_total(),
            'endereco' => $this->get_customer_address($order),
            'bairro' => $this->get_customer_neighborhood($order),
            'cidade' => $this->get_customer_city($order),
            'cep' => $this->get_customer_postcode($order),
            'uf' => $this->get_customer_state($order),
            'email' => $order->get_billing_email()
        );

        // Create boleto
        $boleto_result = WC_Sicoob_Payment_API::create_boleto($order_data);

        if (!$boleto_result['success']) {
            wc_add_notice(
                sprintf(__('Erro ao gerar boleto: %s', 'sicoob-payment'), $boleto_result['message']),
                'error'
            );
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
        }

        // Store boleto data in order meta
        $boleto_data = $boleto_result['data'];
        $order->update_meta_data('_sicoob_boleto_nosso_numero', $boleto_data['nosso_numero'] ?? '');
        $order->update_meta_data('_sicoob_boleto_seu_numero', $boleto_data['seu_numero'] ?? '');
        $order->update_meta_data('_sicoob_boleto_linha_digitavel', $boleto_data['linha_digitavel'] ?? '');
        $order->update_meta_data('_sicoob_boleto_valor', $boleto_data['valor'] ?? 0);
        $order->update_meta_data('_sicoob_boleto_data_vencimento', $boleto_data['data_vencimento'] ?? '');
        $order->update_meta_data('_sicoob_boleto_data_emissao', $boleto_data['data_emissao'] ?? '');
        $order->update_meta_data('_sicoob_boleto_pdf_url', $boleto_data['pdf_saved']['file_url'] ?? '');
        $order->save();

        // Mark order as pending
        $order->update_status('pending', __('Aguardando pagamento via boleto.', 'sicoob-payment'));

        // Send boleto email
        $this->send_boleto_email($order, $boleto_data);

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

        // Check if required boleto fields are configured
        $account_number = $this->get_option('account_number');
        $contract_number = $this->get_option('contract_number');

        if (empty($account_number) || empty($contract_number)) {
            return false;
        }

        return true;
    }

    /**
     * Get customer CPF from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_cpf($order) {
        // Try to get CPF from billing meta
        $cpf = $order->get_meta('_billing_cpf');
        
        if (empty($cpf)) {
            // Try to get CPF from billing meta with different key
            $cpf = $order->get_meta('billing_cpf');
        }
        
        if (empty($cpf)) {
            // Try to get CPF from customer meta
            $customer_id = $order->get_customer_id();
            if ($customer_id) {
                $cpf = get_user_meta($customer_id, 'billing_cpf', true);
            }
        }
        
        // If still empty, try to extract from billing address
        if (empty($cpf)) {
            $cpf = '00000000000'; // Default fallback
        }
        
        return $cpf;
    }

    /**
     * Get customer name from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_name($order) {
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        
        $name = trim($first_name . ' ' . $last_name);
        
        // If billing name is empty, try shipping name
        if (empty($name)) {
            $first_name = $order->get_shipping_first_name();
            $last_name = $order->get_shipping_last_name();
            $name = trim($first_name . ' ' . $last_name);
        }
        
        // If still empty, use customer display name
        if (empty($name)) {
            $name = $order->get_customer_note() ?: __('Cliente', 'sicoob-payment');
        }
        
        return $name;
    }

    /**
     * Get customer address from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_address($order) {
        $address = $order->get_billing_address_1();
        
        if (empty($address)) {
            $address = $order->get_shipping_address_1();
        }
        
        return $address ?: '';
    }

    /**
     * Get customer neighborhood from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_neighborhood($order) {
        $neighborhood = $order->get_meta('_billing_neighborhood');
        
        if (empty($neighborhood)) {
            $neighborhood = $order->get_meta('billing_neighborhood');
        }
        
        if (empty($neighborhood)) {
            $neighborhood = $order->get_meta('_shipping_neighborhood');
        }
        
        return $neighborhood ?: '';
    }

    /**
     * Get customer city from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_city($order) {
        $city = $order->get_billing_city();
        
        if (empty($city)) {
            $city = $order->get_shipping_city();
        }
        
        return $city ?: '';
    }

    /**
     * Get customer postcode from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_postcode($order) {
        $postcode = $order->get_billing_postcode();
        
        if (empty($postcode)) {
            $postcode = $order->get_shipping_postcode();
        }
        
        return $postcode ?: '';
    }

    /**
     * Get customer state from order
     *
     * @param WC_Order $order Order object
     * @return string
     */
    private function get_customer_state($order) {
        $state = $order->get_billing_state();
        
        if (empty($state)) {
            $state = $order->get_shipping_state();
        }
        
        return $state ?: '';
    }

    /**
     * Send boleto email to customer
     *
     * @param WC_Order $order Order object
     * @param array $boleto_data Boleto data
     */
    private function send_boleto_email($order, $boleto_data) {
        // Check if email is enabled
        if ($this->get_option('send_email') !== 'yes') {
            return;
        }

        // Trigger the email action
        do_action('sicoob_boleto_email_notification', $order, $boleto_data);
    }

    /**
     * Enqueue boleto scripts and styles
     */
    public function enqueue_boleto_scripts()
    {
        // Only load on checkout and thank you pages
        if (!is_checkout() && !is_wc_endpoint_url('order-received')) {
            return;
        }

        // Enqueue boleto CSS
        wp_enqueue_style(
            'sicoob-boleto-css',
            SICOOB_PAYMENT_PLUGIN_URL . 'assets/css/sicoob-boleto.css',
            array(),
            SICOOB_PAYMENT_VERSION
        );

        // Enqueue boleto JS
        wp_enqueue_script(
            'sicoob-boleto-js',
            SICOOB_PAYMENT_PLUGIN_URL . 'assets/js/sicoob-boleto.js',
            array('jquery'),
            SICOOB_PAYMENT_VERSION,
            true
        );

        // Localize script
        wp_localize_script('sicoob-boleto-js', 'sicoob_boleto_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sicoob_boleto_nonce'),
            'strings' => array(
                'download_success' => __('Boleto baixado com sucesso!', 'sicoob-payment'),
                'download_error' => __('Erro ao baixar boleto!', 'sicoob-payment'),
                'print_success' => __('Boleto enviado para impressão!', 'sicoob-payment'),
                'print_error' => __('Erro ao imprimir boleto!', 'sicoob-payment'),
            )
        ));
    }

    /**
     * Display boleto payment block on thank you page
     *
     * @param int $order_id Order ID
     */
    public function display_boleto_payment_block($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        // Check if boleto data exists
        $boleto_pdf_url = $order->get_meta('_sicoob_boleto_pdf_url');
        if (empty($boleto_pdf_url)) {
            return;
        }

        // Load template
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/boleto-payment-block.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback template
            $this->display_boleto_payment_block_fallback($order);
        }
    }

    /**
     * Fallback boleto payment block display
     *
     * @param WC_Order $order Order object
     */
    private function display_boleto_payment_block_fallback($order)
    {
        $boleto_pdf_url = $order->get_meta('_sicoob_boleto_pdf_url');
        $boleto_valor = $order->get_meta('_sicoob_boleto_valor');
        $boleto_data_vencimento = $order->get_meta('_sicoob_boleto_data_vencimento');

        if (empty($boleto_pdf_url)) {
            return;
        }

        $formatted_value = wc_price($boleto_valor);
        $formatted_due_date = $boleto_data_vencimento ? date('d/m/Y', strtotime($boleto_data_vencimento)) : '';
        ?>
        <div class="sicoob-boleto-payment-block">
            <div class="sicoob-boleto-header">
                <h3><?php _e('Pagamento via Boleto', 'sicoob-payment'); ?></h3>
                <p><?php _e('Visualize e imprima seu boleto bancário para realizar o pagamento', 'sicoob-payment'); ?></p>
            </div>

            <div class="sicoob-boleto-content">
                <div class="sicoob-boleto-pdf-container">
                    <div class="sicoob-boleto-pdf-header">
                        <h4><?php _e('Boleto Bancário', 'sicoob-payment'); ?></h4>
                        <div class="sicoob-boleto-info">
                            <span class="sicoob-boleto-value"><?php echo $formatted_value; ?></span>
                            <?php if ($formatted_due_date): ?>
                                <span class="sicoob-boleto-due-date"><?php printf(__('Vence em: %s', 'sicoob-payment'), $formatted_due_date); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="sicoob-boleto-pdf-iframe">
                        <iframe src="<?php echo esc_url($boleto_pdf_url); ?>" 
                                width="100%" 
                                height="600" 
                                frameborder="0"
                                title="<?php _e('Boleto Bancário', 'sicoob-payment'); ?>"
                                id="sicoob-boleto-iframe">
                        </iframe>
                    </div>
                </div>

                <div class="sicoob-boleto-actions">
                    <a href="<?php echo esc_url($boleto_pdf_url); ?>" 
                       class="sicoob-boleto-download-btn" 
                       target="_blank" 
                       download>
                        <?php _e('Baixar Boleto', 'sicoob-payment'); ?>
                    </a>
                    
                    <button type="button" class="sicoob-boleto-print-btn" id="sicoob-boleto-print-btn">
                        <?php _e('Imprimir Boleto', 'sicoob-payment'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
