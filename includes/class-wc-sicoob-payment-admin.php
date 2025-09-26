<?php
/**
 * Sicoob Payment Admin Class
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Sicoob_Payment_Admin {

    /**
     * Admin Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_post_sicoob_save_logs_config', array($this, 'save_logs_config'));
        add_action('admin_post_sicoob_save_auth_config', array($this, 'save_auth_config'));
        add_action('admin_post_sicoob_remove_certificate', array($this, 'remove_certificate'));
        add_action('wp_ajax_sicoob_remove_certificate', array($this, 'ajax_remove_certificate'));
        add_action('wp_ajax_sicoob_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_sicoob_test_pix_generation', array($this, 'ajax_test_pix_generation'));
        add_action('wp_ajax_sicoob_test_boleto_generation', array($this, 'ajax_test_boleto_generation'));
        add_action('wp_ajax_sicoob_test_boleto_email', array($this, 'ajax_test_boleto_email'));
        add_action('wp_ajax_sicoob_register_webhook', array($this, 'ajax_register_webhook'));
        add_action('wp_ajax_sicoob_unregister_webhook', array($this, 'ajax_unregister_webhook'));
        add_action('wp_ajax_sicoob_check_webhook_status', array($this, 'ajax_check_webhook_status'));
        add_action('wp_ajax_sicoob_check_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_sicoob_check_payment_status', array($this, 'ajax_check_payment_status'));
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Sicoob Payment', 'sicoob-payment'),
            __('Sicoob Payment', 'sicoob-payment'),
            'manage_woocommerce',
            'sicoob-payment',
            array($this, 'admin_page')
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Load assets in plugin settings pages
        if ('woocommerce_page_sicoob-payment' === $hook) {
            wp_enqueue_style(
                'sicoob-payment-admin',
                SICOOB_PAYMENT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                SICOOB_PAYMENT_VERSION
            );

            wp_enqueue_script(
                'sicoob-payment-admin',
                SICOOB_PAYMENT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                SICOOB_PAYMENT_VERSION,
                true
            );

            // Localizar script com parâmetros AJAX
            wp_localize_script('sicoob-payment-admin', 'sicoob_payment_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sicoob_remove_certificate'),
                'test_api_nonce' => wp_create_nonce('sicoob_test_api'),
                'test_pix_nonce' => wp_create_nonce('sicoob_test_pix_generation'),
                'test_boleto_nonce' => wp_create_nonce('sicoob_test_boleto_generation'),
                'test_boleto_email_nonce' => wp_create_nonce('sicoob_test_boleto_email'),
                'webhook_register_nonce' => wp_create_nonce('sicoob_register_webhook'),
                'webhook_unregister_nonce' => wp_create_nonce('sicoob_unregister_webhook'),
                'webhook_status_nonce' => wp_create_nonce('sicoob_check_webhook_status'),
            ));
        }

        // Load assets in plugin settings pages: PIX and Boleto
        if (strpos($hook, 'wc-settings') !== false && isset($_GET['section'])) {
            $section = sanitize_text_field($_GET['section']);
            
            if (in_array($section, array('sicoob_pix', 'sicoob_boleto'))) {
                wp_enqueue_style(
                    'sicoob-payment-admin',
                    SICOOB_PAYMENT_PLUGIN_URL . 'assets/css/admin.css',
                    array(),
                    SICOOB_PAYMENT_VERSION
                );

                wp_enqueue_script(
                    'sicoob-payment-admin',
                    SICOOB_PAYMENT_PLUGIN_URL . 'assets/js/admin.js',
                    array('jquery'),
                    SICOOB_PAYMENT_VERSION,
                    true
                );

                // Localizar script com parâmetros AJAX
                wp_localize_script('sicoob-payment-admin', 'sicoob_payment_params', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('sicoob_remove_certificate'),
                    'test_api_nonce' => wp_create_nonce('sicoob_test_api'),
                    'test_pix_nonce' => wp_create_nonce('sicoob_test_pix_generation'),
                    'test_boleto_nonce' => wp_create_nonce('sicoob_test_boleto_generation'),
                    'test_boleto_email_nonce' => wp_create_nonce('sicoob_test_boleto_email'),
                    'webhook_register_nonce' => wp_create_nonce('sicoob_register_webhook'),
                    'webhook_unregister_nonce' => wp_create_nonce('sicoob_unregister_webhook'),
                    'webhook_status_nonce' => wp_create_nonce('sicoob_check_webhook_status'),
                ));
            }
        }
    }

    /**
     * Save logs configuration
     */
    public function save_logs_config() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['sicoob_logs_nonce'], 'sicoob_save_logs_config')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter configuração atual
        $config = get_option('sicoob_payment_config', array());

        // Atualizar configuração de logs
        $config['enable_logs'] = isset($_POST['enable_logs']) ? 'yes' : 'no';

        // Salvar configuração
        update_option('sicoob_payment_config', $config);

        // Redirecionar com mensagem de sucesso
        $redirect_url = add_query_arg(
            array(
                'page' => 'sicoob-payment',
                'message' => 'logs_saved'
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Get logs configuration
     */
    private function get_logs_config() {
        $config = get_option('sicoob_payment_config', array());
        return isset($config['enable_logs']) ? $config['enable_logs'] : 'no';
    }

    /**
     * Get authentication configuration
     */
    private function get_auth_config() {
        $config = get_option('sicoob_payment_config', array());
        return array(
            'client_id' => isset($config['client_id']) ? $config['client_id'] : '',
            'certificate_path' => isset($config['certificate_path']) ? $config['certificate_path'] : '',
        );
    }

    /**
     * Save authentication configuration
     */
    public function save_auth_config() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['sicoob_auth_nonce'], 'sicoob_save_auth_config')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter configuração atual
        $config = get_option('sicoob_payment_config', array());

        // Processar ID do Cliente
        if (!empty($_POST['client_id'])) {
            $config['client_id'] = sanitize_text_field($_POST['client_id']);
        } elseif (!empty($_POST['existing_client_id'])) {
            // Se o campo principal está vazio mas há um valor existente, manter o existente
            $config['client_id'] = sanitize_text_field($_POST['existing_client_id']);
        }

        // Processar upload do certificado
        if (!empty($_FILES['certificate_file']['name'])) {
            $upload_result = $this->handle_certificate_upload();
            
            if ($upload_result['success']) {
                $config['certificate_path'] = $upload_result['file_path'];
            } else {
                // Redirecionar com erro
                $redirect_url = add_query_arg(
                    array(
                        'page' => 'sicoob-payment',
                        'error' => 'certificate_upload_failed',
                        'error_message' => urlencode($upload_result['message'])
                    ),
                    admin_url('admin.php')
                );
                wp_redirect($redirect_url);
                exit;
            }
        }

        // Salvar configuração
        update_option('sicoob_payment_config', $config);

        // Redirecionar com mensagem de sucesso
        $redirect_url = add_query_arg(
            array(
                'page' => 'sicoob-payment',
                'message' => 'auth_saved'
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle certificate file upload
     *
     * @return array
     */
    private function handle_certificate_upload() {
        $file = $_FILES['certificate_file'];
        
        // Verificar se há erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array(
                'success' => false,
                'message' => __('Erro no upload do arquivo.', 'sicoob-payment')
            );
        }

        // Validar tipo de arquivo
        $allowed_extensions = array('pem', 'crt', 'key');
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            return array(
                'success' => false,
                'message' => __('Tipo de arquivo não permitido. Use apenas arquivos .PEM, .CRT ou .KEY.', 'sicoob-payment')
            );
        }

        // Validar tamanho do arquivo (1MB máximo)
        if ($file['size'] > 1024 * 1024) {
            return array(
                'success' => false,
                'message' => __('Arquivo muito grande. Tamanho máximo permitido: 1MB.', 'sicoob-payment')
            );
        }

        // Validar conteúdo do certificado
        $certificate_content = file_get_contents($file['tmp_name']);
        if (!$this->validate_certificate_content($certificate_content)) {
            return array(
                'success' => false,
                'message' => __('Arquivo de certificado inválido. Verifique se é um certificado válido do Sicoob.', 'sicoob-payment')
            );
        }

        // Criar diretório de certificados se não existir
        $certificates_dir = SICOOB_PAYMENT_PLUGIN_DIR . 'certificates/';
        if (!file_exists($certificates_dir)) {
            wp_mkdir_p($certificates_dir);
        }

        // Gerar nome único para o arquivo
        $file_name = 'certificate_' . uniqid() . '_' . time() . '.' . $file_extension;
        $file_path = $certificates_dir . $file_name;

        // Mover arquivo para o diretório de certificados
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Definir permissões seguras
            chmod($file_path, 0600);
            
            return array(
                'success' => true,
                'file_path' => $file_path,
                'message' => __('Certificado carregado com sucesso.', 'sicoob-payment')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Erro ao salvar o arquivo. Verifique as permissões do diretório.', 'sicoob-payment')
            );
        }
    }

    /**
     * Validate certificate content
     *
     * @param string $content
     * @return bool
     */
    private function validate_certificate_content($content) {
        // Verificar se contém headers de certificado
        $certificate_headers = array(
            '-----BEGIN CERTIFICATE-----',
            '-----BEGIN PRIVATE KEY-----',
            '-----BEGIN RSA PRIVATE KEY-----',
            '-----BEGIN ENCRYPTED PRIVATE KEY-----'
        );

        foreach ($certificate_headers as $header) {
            if (strpos($content, $header) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove certificate (POST action)
     */
    public function remove_certificate() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['sicoob_remove_nonce'], 'sicoob_remove_certificate')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        $result = $this->process_certificate_removal();

        // Redirecionar com mensagem
        $redirect_url = add_query_arg(
            array(
                'page' => 'sicoob-payment',
                'message' => $result['success'] ? 'certificate_removed' : 'certificate_remove_failed',
                'error_message' => $result['success'] ? '' : urlencode($result['message'])
            ),
            admin_url('admin.php')
        );

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Remove certificate (AJAX action)
     */
    public function ajax_remove_certificate() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_remove_certificate')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        $result = $this->process_certificate_removal();

        wp_send_json($result);
    }

    /**
     * Process certificate removal
     *
     * @return array
     */
    private function process_certificate_removal() {
        // Obter configuração atual
        $config = get_option('sicoob_payment_config', array());

        if (empty($config['certificate_path'])) {
            return array(
                'success' => false,
                'message' => __('Nenhum certificado encontrado para remover.', 'sicoob-payment')
            );
        }

        $certificate_path = $config['certificate_path'];

        // Remover arquivo se existir
        if (file_exists($certificate_path)) {
            if (!unlink($certificate_path)) {
                return array(
                    'success' => false,
                    'message' => __('Erro ao remover o arquivo de certificado.', 'sicoob-payment')
                );
            }
        }

        // Remover referência do banco de dados
        unset($config['certificate_path']);
        update_option('sicoob_payment_config', $config);

        return array(
            'success' => true,
            'message' => __('Certificado removido com sucesso.', 'sicoob-payment')
        );
    }

    /**
     * Test API connection (AJAX action)
     */
    public function ajax_test_api() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_test_api')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        $scope_type = sanitize_text_field($_POST['scope_type']);
        
        // Definir scope baseado no tipo
        $scope = ($scope_type === 'boleto') ? WC_Sicoob_Payment_API::BOLETO_SCOPE : WC_Sicoob_Payment_API::PIX_SCOPE;
        
        // Capturar informações da requisição antes de fazer o teste
        $auth_config = WC_Sicoob_Payment_API::get_auth_config();
        
        // Preparar dados da requisição que será enviada
        $token_data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $auth_config['client_id'],
            'scope' => $scope
        );
        
        $request_headers = array(
            'Content-Type: application/x-www-form-urlencoded'
        );
        
        $request_info = array(
            'method' => 'POST',
            'headers' => $request_headers,
            'body' => $token_data,
            'endpoint' => WC_Sicoob_Payment_API::AUTH_ENDPOINT,
            'curl_options' => array(
                'CURLOPT_SSLCERT' => $auth_config['certificate_path'],
                'CURLOPT_SSLKEY' => $auth_config['certificate_path'],
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_SSL_VERIFYHOST' => 2
            )
        );
        
        // Fazer teste da API
        $result = WC_Sicoob_Payment_API::get_access_token($scope);
        
        // Preparar resposta para exibição
        $response_data = array(
            'scope_type' => $scope_type,
            'scope_used' => $scope,
            'timestamp' => current_time('mysql'),
            'request_info' => $request_info,
            'auth_config' => array(
                'ssl_certificate' => $auth_config['certificate_path'],
                'ssl_key' => $auth_config['certificate_path'],
                'certificate_exists' => file_exists($auth_config['certificate_path']),
                'client_id_configured' => !empty($auth_config['client_id'])
            ),
            'result' => $result
        );
        
        wp_send_json_success($response_data);
    }

    /**
     * Test PIX generation (AJAX action)
     */
    public function ajax_test_pix_generation() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_test_pix_generation')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter configurações do gateway PIX
        $pix_settings = WC_Sicoob_Payment_API::get_pix_gateway_settings();
        
        // Verificar se as configurações estão completas
        if (empty($pix_settings['pix_key']) || empty($pix_settings['pix_description'])) {
            wp_send_json_error(array(
                'message' => __('Configurações do PIX não estão completas. Configure a chave PIX e descrição nas configurações do gateway.', 'sicoob-payment'),
                'settings' => $pix_settings
            ));
        }

        // Gerar dados randômicos para teste
        $test_data = $this->generate_random_test_data();
        
        // Preparar informações da requisição
        $request_info = array(
            'test_data' => $test_data,
            'pix_settings' => $pix_settings,
            'timestamp' => current_time('mysql'),
            'endpoint' => WC_Sicoob_Payment_API::PIX_ENDPOINT . '/cob'
        );

        // Fazer teste de geração de PIX
        $result = WC_Sicoob_Payment_API::create_pix_cob(
            $test_data, 
            $pix_settings['pix_key'], 
            $pix_settings['pix_description']
        );

        // Preparar resposta para exibição
        $response_data = array(
            'request_info' => $request_info,
            'result' => $result,
            'success' => $result['success']
        );

        if ($result['success']) {
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error($response_data);
        }
    }

    /**
     * Test Boleto generation (AJAX action)
     * 
     * Testa a geração de boleto usando dados fictícios para validar
     * a configuração e comunicação com a API do Sicoob.
     */
    public function ajax_test_boleto_generation() {
        // Verificar nonce de segurança
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_test_boleto_generation')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões do usuário
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter configurações do gateway Boleto
        $boleto_settings = WC_Sicoob_Payment_API::get_boleto_gateway_settings();
        
        // Validar configurações obrigatórias
        $validation_result = $this->validate_boleto_settings($boleto_settings);
        if (!$validation_result['valid']) {
            wp_send_json_error(array(
                'message' => $validation_result['message'],
                'settings' => $boleto_settings,
                'missing_fields' => $validation_result['missing_fields']
            ));
        }

        // Gerar dados de teste realistas
        $test_data = $this->generate_random_boleto_test_data();
        
        // Preparar informações da requisição para debug
        $request_info = array(
            'test_data' => $test_data,
            'boleto_settings' => $this->sanitize_boleto_settings_for_display($boleto_settings),
            'timestamp' => current_time('mysql'),
            'endpoint' => WC_Sicoob_Payment_API::BOLETO_ENDPOINT,
            'test_type' => 'boleto_generation'
        );

        // Log do teste iniciado
        WC_Sicoob_Payment::log_message(
            sprintf(__('Iniciando teste de geração de boleto - Pedido: %s, Cliente: %s', 'sicoob-payment'), 
                $test_data['order_id'], 
                $test_data['nome']
            ),
            'info'
        );

        // Executar teste de geração de Boleto
        $result = WC_Sicoob_Payment_API::create_boleto($test_data);

        // Log do resultado
        if ($result['success']) {
            WC_Sicoob_Payment::log_message(
                sprintf(__('Teste de boleto bem-sucedido - Nosso Número: %s', 'sicoob-payment'), 
                    $result['data']['nosso_numero'] ?? 'N/A'
                ),
                'info'
            );
        } else {
            WC_Sicoob_Payment::log_message(
                sprintf(__('Teste de boleto falhou: %s', 'sicoob-payment'), $result['message']),
                'error'
            );
        }

        // Preparar resposta estruturada
        $response_data = array(
            'request_info' => $request_info,
            'result' => $result,
            'success' => $result['success'],
            'test_summary' => $this->generate_boleto_test_summary($result, $test_data)
        );

        // Retornar resposta apropriada
        if ($result['success']) {
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error($response_data);
        }
    }

    /**
     * Generate random test data for PIX
     *
     * @return array
     */
    private function generate_random_test_data() {
        $cpf = '73371160041';
        
        $nomes = array(
            'João Silva Santos',
            'Maria Oliveira Costa',
            'Pedro Almeida Lima',
            'Ana Paula Rodrigues',
            'Carlos Eduardo Ferreira',
            'Lucia Helena Souza',
            'Roberto Carlos Mendes',
            'Fernanda Beatriz Alves'
        );
        
        $valor = 0.01;
        
        return array(
            'cpf' => $cpf,
            'nome' => $nomes[array_rand($nomes)],
            'valor' => $valor
        );
    }

    /**
     * Validate boleto gateway settings
     *
     * @param array $settings Boleto gateway settings
     * @return array Validation result
     */
    private function validate_boleto_settings($settings) {
        $missing_fields = array();
        
        if (empty($settings['account_number'])) {
            $missing_fields[] = __('Número da Conta Corrente', 'sicoob-payment');
        }
        
        if (empty($settings['contract_number'])) {
            $missing_fields[] = __('Número do Contrato', 'sicoob-payment');
        }
        
        if (!empty($missing_fields)) {
            return array(
                'valid' => false,
                'message' => sprintf(
                    __('Configurações do Boleto incompletas. Campos obrigatórios: %s', 'sicoob-payment'),
                    implode(', ', $missing_fields)
                ),
                'missing_fields' => $missing_fields
            );
        }
        
        return array('valid' => true);
    }

    /**
     * Sanitize boleto settings for display (hide sensitive data)
     *
     * @param array $settings Raw boleto settings
     * @return array Sanitized settings
     */
    private function sanitize_boleto_settings_for_display($settings) {
        $sanitized = $settings;
        
        // Mascarar dados sensíveis
        if (!empty($sanitized['account_number'])) {
            $account = $sanitized['account_number'];
            $sanitized['account_number'] = substr($account, 0, 2) . str_repeat('*', strlen($account) - 4) . substr($account, -2);
        }
        
        if (!empty($sanitized['contract_number'])) {
            $contract = $sanitized['contract_number'];
            $sanitized['contract_number'] = substr($contract, 0, 2) . str_repeat('*', strlen($contract) - 4) . substr($contract, -2);
        }
        
        return $sanitized;
    }

    /**
     * Generate test summary for boleto generation
     *
     * @param array $result API result
     * @param array $test_data Test data used
     * @return array Test summary
     */
    private function generate_boleto_test_summary($result, $test_data) {
        $summary = array(
            'test_type' => 'boleto_generation',
            'test_data_used' => array(
                'order_id' => $test_data['order_id'],
                'cpf' => substr($test_data['cpf'], 0, 3) . '.***.**' . substr($test_data['cpf'], -2),
                'nome' => $test_data['nome'],
                'valor' => 'R$ ' . number_format($test_data['valor'], 2, ',', '.')
            ),
            'api_response' => array(
                'success' => $result['success'],
                'message' => $result['message']
            )
        );
        
        if ($result['success'] && isset($result['data'])) {
            $summary['generated_boleto'] = array(
                'nosso_numero' => $result['data']['nosso_numero'] ?? 'N/A',
                'linha_digitavel' => $result['data']['linha_digitavel'] ?? 'N/A',
                'valor' => 'R$ ' . number_format($result['data']['valor'] ?? 0, 2, ',', '.'),
                'data_vencimento' => $result['data']['data_vencimento'] ?? 'N/A',
                'pdf_generated' => isset($result['data']['pdf_saved']) && $result['data']['pdf_saved']['success']
            );
        }
        
        return $summary;
    }

    /**
     * Generate random test data for Boleto
     * 
     * Gera dados de teste realistas para validação da geração de boleto.
     * Utiliza dados fictícios mas válidos para testes.
     *
     * @return array Test data for boleto generation
     */
    private function generate_random_boleto_test_data() {
        // CPF válido para testes (não real)
        $cpf = '73371160041';
        
        $nomes = array(
            'João Silva Santos',
            'Maria Oliveira Costa',
            'Pedro Almeida Lima',
            'Ana Paula Rodrigues',
            'Carlos Eduardo Ferreira',
            'Lucia Helena Souza',
            'Roberto Carlos Mendes',
            'Fernanda Beatriz Alves'
        );
        
        $enderecos = array(
            'Rua das Flores, 123',
            'Avenida Brasil, 456',
            'Rua da Paz, 789',
            'Alameda dos Anjos, 321',
            'Rua do Comércio, 654'
        );
        
        $bairros = array(
            'Centro',
            'Vila Nova',
            'Jardim das Américas',
            'Boa Vista',
            'Industrial'
        );
        
        $cidades = array(
            'São Paulo',
            'Rio de Janeiro',
            'Belo Horizonte',
            'Salvador',
            'Brasília'
        );
        
        $ceps = array(
            '01234567',
            '12345678',
            '23456789',
            '34567890',
            '45678901'
        );
        
        $ufs = array(
            'SP',
            'RJ',
            'MG',
            'BA',
            'DF'
        );
        
        $emails = array(
            'teste1@exemplo.com',
            'teste2@exemplo.com',
            'teste3@exemplo.com',
            'teste4@exemplo.com',
            'teste5@exemplo.com'
        );
        
        // Valor mínimo para teste (R$ 0,01)
        $valor = 0.01;
        
        // ID único baseado em timestamp
        $order_id = 'TEST-' . time();
        
        return array(
            'order_id' => $order_id,
            'cpf' => $cpf,
            'nome' => $nomes[array_rand($nomes)],
            'valor' => $valor,
            'endereco' => $enderecos[array_rand($enderecos)],
            'bairro' => $bairros[array_rand($bairros)],
            'cidade' => $cidades[array_rand($cidades)],
            'cep' => $ceps[array_rand($ceps)],
            'uf' => $ufs[array_rand($ufs)],
            'email' => $emails[array_rand($emails)]
        );
    }

    /**
     * Test Boleto email sending (AJAX action)
     * 
     * Testa o envio de e-mail do boleto usando dados fictícios
     * para validar a configuração e funcionamento do sistema de e-mails.
     */
    public function ajax_test_boleto_email() {
        // Verificar nonce de segurança
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_test_boleto_email')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões do usuário
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter e-mail de teste
        $test_email = sanitize_email($_POST['test_email'] ?? '');
        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error(array(
                'message' => __('E-mail de teste inválido. Forneça um e-mail válido.', 'sicoob-payment')
            ));
        }

        // Verificar se a classe de e-mail está registrada
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        
        if (!isset($emails['WC_Sicoob_Boleto_Email'])) {
            wp_send_json_error(array(
                'message' => __('Classe de e-mail do boleto não está registrada. Verifique se o plugin está ativo.', 'sicoob-payment')
            ));
        }

        // Criar pedido fictício para teste
        $test_order = $this->create_test_order($test_email);
        
        // Gerar dados de boleto fictícios
        $test_boleto_data = $this->generate_test_boleto_data();
        
        // Preparar informações da requisição para debug
        $request_info = array(
            'test_email' => $test_email,
            'test_order_id' => $test_order->get_id(),
            'test_boleto_data' => $test_boleto_data,
            'timestamp' => current_time('mysql'),
            'test_type' => 'boleto_email'
        );

        // Log do teste iniciado
        WC_Sicoob_Payment::log_message(
            sprintf(__('Iniciando teste de e-mail de boleto - E-mail: %s, Pedido: %s', 'sicoob-payment'), 
                $test_email, 
                $test_order->get_id()
            ),
            'info'
        );

        try {
            // Tentar enviar o e-mail usando o hook
            do_action('sicoob_boleto_email_notification', $test_order, $test_boleto_data);
            
            // Log do sucesso
            WC_Sicoob_Payment::log_message(
                sprintf(__('Teste de e-mail de boleto bem-sucedido - E-mail: %s', 'sicoob-payment'), $test_email),
                'info'
            );

            // Preparar resposta de sucesso
            $response_data = array(
                'request_info' => $request_info,
                'success' => true,
                'message' => sprintf(__('E-mail de teste enviado com sucesso para %s!', 'sicoob-payment'), $test_email),
                'test_summary' => array(
                    'test_type' => 'boleto_email',
                    'email_sent_to' => $test_email,
                    'order_id' => $test_order->get_id(),
                    'boleto_data' => $test_boleto_data
                )
            );

            wp_send_json_success($response_data);

        } catch (Exception $e) {
            // Log do erro
            WC_Sicoob_Payment::log_message(
                sprintf(__('Teste de e-mail de boleto falhou: %s', 'sicoob-payment'), $e->getMessage()),
                'error'
            );

            wp_send_json_error(array(
                'request_info' => $request_info,
                'message' => sprintf(__('Erro ao enviar e-mail de teste: %s', 'sicoob-payment'), $e->getMessage()),
                'error_details' => $e->getMessage()
            ));
        }
    }

    /**
     * Create test order for email testing
     *
     * @param string $email Test email address
     * @return WC_Order
     */
    private function create_test_order($email) {
        // Criar pedido fictício
        $order = wc_create_order();
        
        // Definir dados do cliente
        $order->set_billing_email($email);
        $order->set_billing_first_name('Teste');
        $order->set_billing_last_name('E-mail');
        $order->set_billing_address_1('Rua de Teste, 123');
        $order->set_billing_city('São Paulo');
        $order->set_billing_state('SP');
        $order->set_billing_postcode('01234567');
        $order->set_billing_country('BR');
        
        // Adicionar produto fictício
        $product = new WC_Product_Simple();
        $product->set_name('Produto de Teste - E-mail');
        $product->set_price(0.01);
        $product->save();
        
        $order->add_product($product, 1);
        $order->calculate_totals();
        $order->save();
        
        return $order;
    }

    /**
     * Generate test boleto data
     *
     * @return array
     */
    private function generate_test_boleto_data() {
        return array(
            'nosso_numero' => 'TEST' . time(),
            'linha_digitavel' => '12345.67890.12345.678901.23456.78901.2.34567890123',
            'valor' => 0.01,
            'data_vencimento' => date('Y-m-d', strtotime('+3 days')),
            'data_emissao' => date('Y-m-d'),
            'pdf_url' => home_url('/wp-content/uploads/test-boleto.pdf') // URL fictícia
        );
    }

    /**
     * Register PIX webhook (AJAX action)
     */
    public function ajax_register_webhook() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_register_webhook')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter chave PIX das configurações
        $pix_settings = WC_Sicoob_Payment_API::get_pix_gateway_settings();
        $pix_key = $pix_settings['pix_key'];

        if (empty($pix_key)) {
            wp_send_json_error(array(
                'message' => __('Chave PIX não configurada. Configure a chave PIX nas configurações do gateway.', 'sicoob-payment')
            ));
        }

        // Gerar URL do webhook
        $webhook_url = home_url('/webhook/sicoob');

        // Log do registro iniciado
        WC_Sicoob_Payment::log_message(
            sprintf(__('Iniciando registro de webhook PIX - Chave: %s, URL: %s', 'sicoob-payment'), 
                $pix_key, 
                $webhook_url
            ),
            'info'
        );

        // Registrar webhook
        $result = WC_Sicoob_Payment_API::register_pix_webhook($pix_key, $webhook_url);

        // Preparar resposta
        $response_data = array(
            'pix_key' => $pix_key,
            'webhook_url' => $webhook_url,
            'timestamp' => current_time('mysql'),
            'result' => $result
        );

        if ($result['success']) {
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error($response_data);
        }
    }

    /**
     * Unregister PIX webhook (AJAX action)
     */
    public function ajax_unregister_webhook() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_unregister_webhook')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter chave PIX das configurações
        $pix_settings = WC_Sicoob_Payment_API::get_pix_gateway_settings();
        $pix_key = $pix_settings['pix_key'];

        if (empty($pix_key)) {
            wp_send_json_error(array(
                'message' => __('Chave PIX não configurada. Configure a chave PIX nas configurações do gateway.', 'sicoob-payment')
            ));
        }

        // Log da remoção iniciada
        WC_Sicoob_Payment::log_message(
            sprintf(__('Iniciando remoção de webhook PIX - Chave: %s', 'sicoob-payment'), $pix_key),
            'info'
        );

        // Remover webhook
        $result = WC_Sicoob_Payment_API::unregister_pix_webhook($pix_key);

        // Preparar resposta
        $response_data = array(
            'pix_key' => $pix_key,
            'timestamp' => current_time('mysql'),
            'result' => $result
        );

        if ($result['success']) {
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error($response_data);
        }
    }

    /**
     * Check PIX webhook status (AJAX action)
     */
    public function ajax_check_webhook_status() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_check_webhook_status')) {
            wp_die(__('Ação não autorizada.', 'sicoob-payment'));
        }

        // Verificar permissões
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'sicoob-payment'));
        }

        // Obter chave PIX das configurações
        $pix_settings = WC_Sicoob_Payment_API::get_pix_gateway_settings();
        $pix_key = $pix_settings['pix_key'];

        if (empty($pix_key)) {
            wp_send_json_error(array(
                'message' => __('Chave PIX não configurada. Configure a chave PIX nas configurações do gateway.', 'sicoob-payment')
            ));
        }

        // Log da consulta iniciada
        WC_Sicoob_Payment::log_message(
            sprintf(__('Consultando status do webhook PIX - Chave: %s', 'sicoob-payment'), $pix_key),
            'info'
        );

        // Consultar status do webhook
        $result = WC_Sicoob_Payment_API::get_pix_webhook_status($pix_key);

        // Preparar resposta
        $response_data = array(
            'pix_key' => $pix_key,
            'webhook_url' => home_url('/webhook/sicoob'),
            'timestamp' => current_time('mysql'),
            'result' => $result
        );

        if ($result['success']) {
            wp_send_json_success($response_data);
        } else {
            wp_send_json_error($response_data);
        }
    }

    /**
     * Display admin page
     */
    public function admin_page() {
        $logs_enabled = $this->get_logs_config();
        $auth_config = $this->get_auth_config();
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
        $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($message === 'logs_saved'): ?>
                <div class="sicoob-notice success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Configurações de logs salvas com sucesso!', 'sicoob-payment'); ?>
                </div>
            <?php endif; ?>

            <?php if ($message === 'auth_saved'): ?>
                <div class="sicoob-notice success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Configurações de autenticação salvas com sucesso!', 'sicoob-payment'); ?>
                </div>
            <?php endif; ?>

            <?php if ($message === 'certificate_removed'): ?>
                <div class="sicoob-notice success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Certificado removido com sucesso!', 'sicoob-payment'); ?>
                </div>
            <?php endif; ?>

            <?php if ($error === 'certificate_upload_failed'): ?>
                <div class="sicoob-notice error">
                    <span class="dashicons dashicons-warning"></span>
                    <?php 
                    $error_message = isset($_GET['error_message']) ? sanitize_text_field($_GET['error_message']) : __('Erro no upload do certificado.', 'sicoob-payment');
                    echo esc_html($error_message); 
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($message === 'certificate_remove_failed'): ?>
                <div class="sicoob-notice error">
                    <span class="dashicons dashicons-warning"></span>
                    <?php 
                    $error_message = isset($_GET['error_message']) ? sanitize_text_field($_GET['error_message']) : __('Erro ao remover o certificado.', 'sicoob-payment');
                    echo esc_html($error_message); 
                    ?>
                </div>
            <?php endif; ?>


            <div class="sicoob-payment-admin">
                <p><?php _e('Bem-vindo à área administrativa do Sicoob Payment.', 'sicoob-payment'); ?></p>
                <p><?php _e('Aqui você poderá configurar e gerenciar os pagamentos via PIX e Boleto do Sicoob.', 'sicoob-payment'); ?></p>

                <!-- Bloco de Dados de Autenticação -->
                <div class="sicoob-config-block">
                    <div class="sicoob-config-block-header">
                        <h2>
                            <span class="dashicons dashicons-lock"></span>
                            <?php _e('Dados de Autenticação', 'sicoob-payment'); ?>
                        </h2>
                    </div>
                    
                    <div class="sicoob-config-block-content">
                        <!-- Alerta de Segurança -->
                        <div class="sicoob-security-alert">
                            <span class="dashicons dashicons-shield"></span>
                            <div class="sicoob-security-alert-content">
                                <div class="sicoob-security-alert-title">
                                    <?php _e('Dados Sensíveis', 'sicoob-payment'); ?>
                                </div>
                                <div class="sicoob-security-alert-text">
                                    <?php _e('As informações abaixo são sensíveis e serão utilizadas para autenticação com a API do Sicoob. Mantenha-as seguras e não as compartilhe.', 'sicoob-payment'); ?>
                                </div>
                            </div>
                        </div>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="sicoob-config-form">
                            <?php wp_nonce_field('sicoob_save_auth_config', 'sicoob_auth_nonce'); ?>
                            <input type="hidden" name="action" value="sicoob_save_auth_config">
                            <?php if (!empty($auth_config['client_id'])): ?>
                                <input type="hidden" name="existing_client_id" value="<?php echo esc_attr($auth_config['client_id']); ?>">
                            <?php endif; ?>
                            
                            <!-- ID do Cliente -->
                            <div class="sicoob-config-field">
                                <label for="client_id">
                                    <?php _e('ID do Cliente', 'sicoob-payment'); ?>
                                </label>
                                
                                <?php if (!empty($auth_config['client_id'])): ?>
                                    <div class="sicoob-sensitive-status configured">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('ID do Cliente configurado', 'sicoob-payment'); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="sicoob-sensitive-status not-configured">
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php _e('ID do Cliente não configurado', 'sicoob-payment'); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="sicoob-input-with-button">
                                    <input 
                                        type="text" 
                                        id="client_id" 
                                        name="client_id" 
                                        value=""
                                        placeholder="<?php echo !empty($auth_config['client_id']) ? '••••••••••' : __('Digite o ID do Cliente', 'sicoob-payment'); ?>"
                                        class="sicoob-auth-field"
                                        <?php echo !empty($auth_config['client_id']) ? 'readonly' : ''; ?>
                                    >
                                    
                                    <?php if (!empty($auth_config['client_id'])): ?>
                                        <button type="button" class="sicoob-change-data-btn">
                                            <span class="dashicons dashicons-edit"></span>
                                            <?php _e('Alterar', 'sicoob-payment'); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="sicoob-config-field-description">
                                    <?php _e('ID do Cliente disponibilizado no painel de desenvolvedor do Sicoob.', 'sicoob-payment'); ?>
                                </div>
                            </div>

                            <!-- Certificado Digital -->
                            <div class="sicoob-config-field">
                                <label for="certificate_file">
                                    <?php _e('Certificado Digital (.PEM)', 'sicoob-payment'); ?>
                                </label>
                                
                                <div class="sicoob-config-field-description">
                                    <?php _e('Certificado digital no formato .PEM obtido no painel de desenvolvedor do Sicoob. Será utilizado para autenticação nas requisições cURL.', 'sicoob-payment'); ?>
                                </div>

                                <?php if (!empty($auth_config['certificate_path']) && file_exists($auth_config['certificate_path'])): ?>
                                    <div class="sicoob-sensitive-status configured">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php _e('Certificado configurado', 'sicoob-payment'); ?>
                                        <div class="sicoob-certificate-info">
                                            <small><?php _e('Arquivo:', 'sicoob-payment'); ?> <?php echo esc_html(basename($auth_config['certificate_path'])); ?></small>
                                        </div>
                                        <div class="sicoob-certificate-actions">
                                            <button type="button" class="sicoob-remove-certificate-btn" data-certificate-path="<?php echo esc_attr($auth_config['certificate_path']); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                                <?php _e('Remover Certificado', 'sicoob-payment'); ?>
                                            </button>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="sicoob-sensitive-status not-configured">
                                        <span class="dashicons dashicons-warning"></span>
                                        <?php _e('Certificado não configurado', 'sicoob-payment'); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="sicoob-file-upload-wrapper">
                                    <input 
                                        type="file" 
                                        id="certificate_file" 
                                        name="certificate_file" 
                                        accept=".pem,.crt,.key"
                                        class="sicoob-file-input"
                                    >
                                    
                                    <div class="sicoob-file-upload-info">
                                        <div class="sicoob-file-requirements">
                                            <strong><?php _e('Requisitos do arquivo:', 'sicoob-payment'); ?></strong>
                                            <ul>
                                                <li><?php _e('Formato: .PEM, .CRT ou .KEY', 'sicoob-payment'); ?></li>
                                                <li><?php _e('Tamanho máximo: 1MB', 'sicoob-payment'); ?></li>
                                                <li><?php _e('Certificado válido do Sicoob', 'sicoob-payment'); ?></li>
                                            </ul>
                                        </div>
                                        
                                        <div class="sicoob-file-validation" id="certificate-validation" style="display: none;">
                                            <div class="sicoob-validation-message"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sicoob-config-actions">
                                <button type="submit" class="sicoob-btn sicoob-btn-primary">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php _e('Salvar Configurações', 'sicoob-payment'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bloco de Configurações de Logs -->
                <div class="sicoob-config-block">
                    <div class="sicoob-config-block-header">
                        <h2>
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Configurações de Logs', 'sicoob-payment'); ?>
                        </h2>
                    </div>
                    
                    <div class="sicoob-config-block-content">
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sicoob-config-form">
                            <?php wp_nonce_field('sicoob_save_logs_config', 'sicoob_logs_nonce'); ?>
                            <input type="hidden" name="action" value="sicoob_save_logs_config">
                            
                            <div class="sicoob-config-field">
                                <div class="sicoob-checkbox-wrapper">
                                    <input 
                                        type="checkbox" 
                                        id="enable_logs" 
                                        name="enable_logs" 
                                        value="yes" 
                                        <?php checked($logs_enabled, 'yes'); ?>
                                    >
                                    <label for="enable_logs">
                                        <?php _e('Habilitar registro de logs em arquivos', 'sicoob-payment'); ?>
                                        <span class="sicoob-checkbox-status <?php echo $logs_enabled === 'yes' ? 'enabled' : 'disabled'; ?>">
                                            <span class="dashicons <?php echo $logs_enabled === 'yes' ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                                            <?php echo $logs_enabled === 'yes' ? __('Ativado', 'sicoob-payment') : __('Desativado', 'sicoob-payment'); ?>
                                        </span>
                                    </label>
                                </div>
                                
                                <div class="sicoob-config-field-description">
                                    <?php _e('Quando habilitado, o plugin registrará logs detalhados das transações e operações em arquivos. Isso é útil para depuração e monitoramento, mas pode gerar muitos arquivos de log.', 'sicoob-payment'); ?>
                                </div>
                            </div>

                            <div class="sicoob-config-actions">
                                <button type="submit" class="sicoob-btn sicoob-btn-primary">
                                    <span class="dashicons dashicons-saved"></span>
                                    <?php _e('Salvar Configurações', 'sicoob-payment'); ?>
                                </button>
                                
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-status&tab=logs')); ?>" class="sicoob-btn sicoob-btn-secondary">
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Ver Logs do WooCommerce', 'sicoob-payment'); ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bloco de Teste da API -->
                <div class="sicoob-config-block">
                    <div class="sicoob-config-block-header">
                        <h2>
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Teste da API', 'sicoob-payment'); ?>
                        </h2>
                    </div>
                    
                    <div class="sicoob-config-block-content">
                        <div class="sicoob-test-section">
                            <h3><?php _e('Teste de Token de Acesso', 'sicoob-payment'); ?></h3>
                            <p><?php _e('Use este bloco para testar a comunicação com a API do Sicoob e verificar se as configurações estão corretas.', 'sicoob-payment'); ?></p>
                            
                            <div class="sicoob-test-actions">
                                <button type="button" id="test-pix-token" class="sicoob-btn sicoob-btn-secondary">
                                    <span class="dashicons dashicons-admin-network"></span>
                                    <?php _e('Testar Token PIX', 'sicoob-payment'); ?>
                                </button>
                                
                                <button type="button" id="test-boleto-token" class="sicoob-btn sicoob-btn-secondary">
                                    <span class="dashicons dashicons-admin-network"></span>
                                    <?php _e('Testar Token Boleto', 'sicoob-payment'); ?>
                                </button>
                                
                                <button type="button" id="test-pix-generation" class="sicoob-btn sicoob-btn-primary">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <?php _e('Testar Geração PIX', 'sicoob-payment'); ?>
                                </button>
                                
                                <button type="button" id="test-boleto-generation" class="sicoob-btn sicoob-btn-primary">
                                    <span class="dashicons dashicons-media-document"></span>
                                    <?php _e('Testar Geração Boleto', 'sicoob-payment'); ?>
                                </button>
                            </div>
                            
                            <div id="api-test-results" class="sicoob-test-results" style="display: none;">
                                <h4><?php _e('Resultado do Teste:', 'sicoob-payment'); ?></h4>
                                <div class="sicoob-test-response">
                                    <pre id="api-response-content"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloco de Webhook PIX -->
                <div class="sicoob-config-block">
                    <div class="sicoob-config-block-header">
                        <h2>
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php _e('Webhook PIX', 'sicoob-payment'); ?>
                        </h2>
                    </div>
                    
                    <div class="sicoob-config-block-content">
                        <div class="sicoob-webhook-section">
                            <h3><?php _e('Configuração do Webhook PIX', 'sicoob-payment'); ?></h3>
                            <p><?php _e('Configure o webhook para receber notificações automáticas de pagamentos PIX. O webhook deve aceitar apenas requisições POST na URL especificada.', 'sicoob-payment'); ?></p>
                            
                            <?php
                            // Obter configurações PIX
                            $pix_settings = WC_Sicoob_Payment_API::get_pix_gateway_settings();
                            $pix_key = $pix_settings['pix_key'];
                            $webhook_url = home_url('/webhook/sicoob');
                            ?>
                            
                            <div class="sicoob-webhook-info">
                                <div class="sicoob-webhook-url">
                                    <label><?php _e('URL do Webhook:', 'sicoob-payment'); ?></label>
                                    <div class="sicoob-url-display">
                                        <code><?php echo esc_html($webhook_url); ?></code>
                                        <button type="button" class="sicoob-copy-url-btn" data-url="<?php echo esc_attr($webhook_url); ?>">
                                            <span class="dashicons dashicons-admin-page"></span>
                                            <?php _e('Copiar', 'sicoob-payment'); ?>
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if (empty($pix_key)): ?>
                                    <div class="sicoob-webhook-warning">
                                        <span class="dashicons dashicons-warning"></span>
                                        <p><?php _e('Chave PIX não configurada. Configure a chave PIX nas configurações do gateway PIX para poder usar o webhook.', 'sicoob-payment'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="sicoob-webhook-actions">
                                <button type="button" id="check-webhook-status" class="sicoob-btn sicoob-btn-secondary" <?php echo empty($pix_key) ? 'disabled' : ''; ?>>
                                    <span class="dashicons dashicons-visibility"></span>
                                    <?php _e('Verificar Status', 'sicoob-payment'); ?>
                                </button>
                                
                                <button type="button" id="register-webhook" class="sicoob-btn sicoob-btn-primary" <?php echo empty($pix_key) ? 'disabled' : ''; ?>>
                                    <span class="dashicons dashicons-admin-links"></span>
                                    <?php _e('Vincular Webhook', 'sicoob-payment'); ?>
                                </button>
                                
                                <button type="button" id="unregister-webhook" class="sicoob-btn sicoob-btn-danger" <?php echo empty($pix_key) ? 'disabled' : ''; ?>>
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php _e('Desvincular Webhook', 'sicoob-payment'); ?>
                                </button>
                            </div>
                            
                            <div id="webhook-status-display" class="sicoob-webhook-status" style="display: none;">
                                <h4><?php _e('Status do Webhook:', 'sicoob-payment'); ?></h4>
                                <div class="sicoob-status-content">
                                    <div class="sicoob-status-indicator">
                                        <span class="sicoob-status-icon"></span>
                                        <span class="sicoob-status-text"></span>
                                    </div>
                                    <div class="sicoob-status-details"></div>
                                </div>
                            </div>
                            
                            <div id="webhook-test-results" class="sicoob-test-results" style="display: none;">
                                <h4><?php _e('Resultado da Operação:', 'sicoob-payment'); ?></h4>
                                <div class="sicoob-test-response">
                                    <pre id="webhook-response-content"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloco de Teste de E-mail -->
                <div class="sicoob-config-block">
                    <div class="sicoob-config-block-header">
                        <h2>
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php _e('Teste de E-mail do Boleto', 'sicoob-payment'); ?>
                        </h2>
                    </div>
                    
                    <div class="sicoob-config-block-content">
                        <div class="sicoob-test-section">
                            <h3><?php _e('Teste de Envio de E-mail', 'sicoob-payment'); ?></h3>
                            <p><?php _e('Use este bloco para testar o envio de e-mail com dados do boleto. Um e-mail de teste será enviado com dados fictícios.', 'sicoob-payment'); ?></p>
                            
                            <div class="sicoob-email-test-form">
                                <div class="sicoob-config-field">
                                    <label for="test-email-address">
                                        <?php _e('E-mail de Teste', 'sicoob-payment'); ?>
                                    </label>
                                    <input 
                                        type="email" 
                                        id="test-email-address" 
                                        placeholder="<?php _e('Digite seu e-mail para receber o teste', 'sicoob-payment'); ?>"
                                        class="sicoob-email-input"
                                    >
                                    <div class="sicoob-config-field-description">
                                        <?php _e('Digite um e-mail válido onde você deseja receber o e-mail de teste do boleto.', 'sicoob-payment'); ?>
                                    </div>
                                </div>
                                
                                <div class="sicoob-test-actions">
                                    <button type="button" id="test-boleto-email" class="sicoob-btn sicoob-btn-primary">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <?php _e('Enviar E-mail de Teste', 'sicoob-payment'); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div id="email-test-results" class="sicoob-test-results" style="display: none;">
                                <h4><?php _e('Resultado do Teste de E-mail:', 'sicoob-payment'); ?></h4>
                                <div class="sicoob-test-response">
                                    <pre id="email-response-content"></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to check payment status
     */
    public function ajax_check_payment_status() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'sicoob_pix_nonce')) {
            wp_die(__('Nonce inválido.', 'sicoob-payment'));
        }

        // Get order ID from request
        $order_id = intval($_POST['order_id']);
        
        if (!$order_id) {
            wp_send_json_error(array(
                'message' => __('ID do pedido inválido.', 'sicoob-payment')
            ));
        }

        // Get order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Pedido não encontrado.', 'sicoob-payment')
            ));
        }

        // Check if order is paid
        $is_paid = $order->is_paid();
        $status = $order->get_status();
        
        // Prepare response
        $response = array(
            'is_paid' => $is_paid,
            'status' => $status,
            'status_label' => wc_get_order_status_name($status),
            'order_id' => $order_id
        );

        // If paid, add success message
        if ($is_paid) {
            $response['message'] = __('Pagamento confirmado com sucesso!', 'sicoob-payment');
        } else {
            $response['message'] = __('Aguardando confirmação do pagamento...', 'sicoob-payment');
        }

        wp_send_json_success($response);
    }
}
