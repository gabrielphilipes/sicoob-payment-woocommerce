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
     * Display admin page
     */
    public function admin_page() {
        $logs_enabled = $this->get_logs_config();
        $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if ($message === 'logs_saved'): ?>
                <div class="sicoob-notice success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php _e('Configurações de logs salvas com sucesso!', 'sicoob-payment'); ?>
                </div>
            <?php endif; ?>

            <div class="sicoob-payment-admin">
                <p><?php _e('Bem-vindo à área administrativa do Sicoob Payment.', 'sicoob-payment'); ?></p>
                <p><?php _e('Aqui você poderá configurar e gerenciar os pagamentos via PIX e Boleto do Sicoob.', 'sicoob-payment'); ?></p>

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
