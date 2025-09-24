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
        if ('woocommerce_page_sicoob-payment' !== $hook) {
            return;
        }

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
        ));
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
            </div>
        </div>
        <?php
    }
}
