<?php
/**
 * Sicoob PIX Payment Gateway
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Sicoob_Pix_Gateway extends WC_Payment_Gateway
{

    /**
     * PIX Gateway Constructor
     */
    public function __construct()
    {
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

        // Add PIX payment block to thank you page
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'display_pix_payment_block'));

        // Enqueue PIX scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_pix_scripts'));

    }

    /**
     * Initialize form fields for configuration
     */
    public function init_form_fields()
    {
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
            ),
            'pix_key' => array(
                'title' => __('Chave PIX de destino', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Chave PIX cadastrada no Sicoob. Pode ser e-mail, CPF/CNPJ ou celular.', 'sicoob-payment'),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array(
                    'placeholder' => __('exemplo@email.com ou 11999999999 ou 12345678901', 'sicoob-payment')
                )
            ),
            'pix_description' => array(
                'title' => __('Descrição do PIX', 'sicoob-payment'),
                'type' => 'text',
                'description' => __('Descrição que aparecerá no PIX (máximo 40 caracteres).', 'sicoob-payment'),
                'default' => __('Compra WooCommerce', 'sicoob-payment'),
                'desc_tip' => true,
                'custom_attributes' => array(
                    'maxlength' => '40',
                    'data-counter' => 'true'
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
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
        }

        // Get PIX settings
        $pix_key = $this->get_option('pix_key');
        $pix_description = $this->get_option('pix_description');

        // Validate PIX settings
        if (empty($pix_key) || empty($pix_description)) {
            wc_add_notice(__('Configurações do PIX não estão completas. Entre em contato com o administrador.', 'sicoob-payment'), 'error');
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
        }

        // Prepare order data for PIX creation
        $order_data = array(
            'cpf' => $this->get_customer_cpf($order),
            'nome' => $this->get_customer_name($order),
            'valor' => $order->get_total()
        );

        // Create PIX COB
        $pix_result = WC_Sicoob_Payment_API::create_pix_cob($order_data, $pix_key, $pix_description);

        if (!$pix_result['success']) {
            wc_add_notice(
                sprintf(__('Erro ao gerar PIX: %s', 'sicoob-payment'), $pix_result['message']),
                'error'
            );
            return array(
                'result' => 'failure',
                'redirect' => ''
            );
        }

        // Store PIX data in order meta
        $pix_data = $pix_result['data'];
        $order->update_meta_data('_sicoob_pix_txid', $pix_data['txid'] ?? '');
        $order->update_meta_data('_sicoob_pix_qrcode', $pix_data['brcode'] ?? '');
        $order->update_meta_data('_sicoob_pix_criacao', $pix_data['calendario']['criacao'] ?? '');
        $order->update_meta_data('_sicoob_pix_expiracao', $pix_data['calendario']['expiracao'] ?? 3600);
        $order->save();

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
    public function is_available()
    {
        if ($this->enabled === 'no') {
            return false;
        }

        // Check if required PIX fields are configured
        $pix_key = $this->get_option('pix_key');
        $pix_description = $this->get_option('pix_description');

        if (empty($pix_key) || empty($pix_description)) {
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
    private function get_customer_cpf($order)
    {
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
    private function get_customer_name($order)
    {
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
     * Enqueue PIX scripts and styles
     */
    public function enqueue_pix_scripts()
    {
        // Only load on checkout and thank you pages
        if (!is_checkout() && !is_wc_endpoint_url('order-received')) {
            return;
        }

        // Enqueue PIX CSS
        wp_enqueue_style(
            'sicoob-pix-css',
            SICOOB_PAYMENT_PLUGIN_URL . 'assets/css/sicoob-pix.css',
            array(),
            SICOOB_PAYMENT_VERSION
        );

        // Enqueue PIX JS
        wp_enqueue_script(
            'sicoob-pix-js',
            SICOOB_PAYMENT_PLUGIN_URL . 'assets/js/sicoob-pix.js',
            array('jquery'),
            SICOOB_PAYMENT_VERSION,
            true
        );

        // Enqueue QR Code library
        wp_enqueue_script(
            'qrcode-js',
            SICOOB_PAYMENT_PLUGIN_URL . 'assets/js/qrcode.min.js',
            array(),
            '1.0.0',
            true
        );

        // Localize script
        wp_localize_script('sicoob-pix-js', 'sicoob_pix_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sicoob_pix_nonce'),
            'strings' => array(
                'copy_success' => __('Código PIX copiado!', 'sicoob-payment'),
                'copy_error' => __('Erro ao copiar código PIX!', 'sicoob-payment'),
                'qr_error' => __('Erro ao gerar QR Code!', 'sicoob-payment'),
            )
        ));
    }

    /**
     * Display PIX payment block on thank you page
     *
     * @param int $order_id Order ID
     */
    public function display_pix_payment_block($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== $this->id) {
            return;
        }

        // Check if PIX data exists
        $pix_qrcode = $order->get_meta('_sicoob_pix_qrcode');
        if (empty($pix_qrcode)) {
            return;
        }

        // Load template
        $template_path = plugin_dir_path(dirname(__FILE__)) . 'templates/pix-payment-block.php';

        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback template
            $this->display_pix_payment_block_fallback($order);
        }
    }

    /**
     * Fallback PIX payment block display
     *
     * @param WC_Order $order Order object
     */
    private function display_pix_payment_block_fallback($order)
    {
        $pix_qrcode = $order->get_meta('_sicoob_pix_qrcode');
        $pix_txid = $order->get_meta('_sicoob_pix_txid');

        if (empty($pix_qrcode)) {
            return;
        }
        ?>
        <div class="sicoob-pix-payment-block">
            <div class="sicoob-pix-header">
                <h3><?php _e('Pagamento via PIX', 'sicoob-payment'); ?></h3>
                <p><?php _e('Escaneie o QR Code ou copie o código PIX para realizar o pagamento', 'sicoob-payment'); ?></p>
            </div>

            <div class="sicoob-pix-content">
                <div class="sicoob-pix-left">
                    <div class="sicoob-pix-qr-container">
                        <div class="sicoob-pix-qr-code" data-qr-code="<?php echo esc_attr($pix_qrcode); ?>">
                            <!-- QR Code will be generated by JavaScript -->
                        </div>
                        <p class="sicoob-pix-qr-text">
                            <?php _e('Escaneie com o app do seu banco', 'sicoob-payment'); ?>
                        </p>
                    </div>

                    <div class="sicoob-pix-code-container">
                        <input type="text" class="sicoob-pix-code-input" value="<?php echo esc_attr($pix_qrcode); ?>" readonly>
                        <button type="button" class="sicoob-pix-copy-btn">
                            <?php _e('Copiar', 'sicoob-payment'); ?>
                        </button>
                    </div>

                    <textarea class="sicoob-pix-code-textarea" readonly
                        style="display: none;"><?php echo esc_textarea($pix_qrcode); ?></textarea>
                </div>

                <div class="sicoob-pix-right">
                    <div class="sicoob-pix-instructions">
                        <div class="sicoob-pix-step">
                            <div class="sicoob-pix-step-number">1</div>
                            <div class="sicoob-pix-step-content">
                                <h4 class="sicoob-pix-step-title"><?php _e('Abra o app do seu banco', 'sicoob-payment'); ?></h4>
                                <p class="sicoob-pix-step-text">
                                    <?php _e('Acesse o aplicativo do seu banco no celular ou internet banking.', 'sicoob-payment'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="sicoob-pix-step">
                            <div class="sicoob-pix-step-number">2</div>
                            <div class="sicoob-pix-step-content">
                                <h4 class="sicoob-pix-step-title"><?php _e('Escolha a opção PIX', 'sicoob-payment'); ?></h4>
                                <p class="sicoob-pix-step-text">
                                    <?php _e('Procure pela opção "PIX" ou "Pagar com PIX" no menu principal.', 'sicoob-payment'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="sicoob-pix-step">
                            <div class="sicoob-pix-step-number">3</div>
                            <div class="sicoob-pix-step-content">
                                <h4 class="sicoob-pix-step-title"><?php _e('Escaneie o QR Code', 'sicoob-payment'); ?></h4>
                                <p class="sicoob-pix-step-text">
                                    <?php _e('Use a câmera do app para escanear o QR Code ou cole o código PIX.', 'sicoob-payment'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="sicoob-pix-step">
                            <div class="sicoob-pix-step-number">4</div>
                            <div class="sicoob-pix-step-content">
                                <h4 class="sicoob-pix-step-title"><?php _e('Confirme o pagamento', 'sicoob-payment'); ?></h4>
                                <p class="sicoob-pix-step-text">
                                    <?php _e('Verifique os dados e confirme o pagamento. O valor será debitado instantaneamente.', 'sicoob-payment'); ?>
                                </p>
                            </div>
                        </div>

                        <div class="sicoob-pix-step">
                            <div class="sicoob-pix-step-number">5</div>
                            <div class="sicoob-pix-step-content">
                                <h4 class="sicoob-pix-step-title"><?php _e('Aguarde a confirmação', 'sicoob-payment'); ?></h4>
                                <p class="sicoob-pix-step-text">
                                    <?php _e('O pedido será processado automaticamente após a confirmação do pagamento.', 'sicoob-payment'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
