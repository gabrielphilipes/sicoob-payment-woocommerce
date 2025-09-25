<?php
/**
 * Main Sicoob Payment Plugin Class
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Sicoob_Payment {
    /**
     * @var WC_Sicoob_Payment
     */
    private static $instance = null;
    
    private function __construct() {
        // Private initialization
    }

    /**
     * @return WC_Sicoob_Payment
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return void
     */
    public function init(): void {
        // Load translations
        $this->load_plugin_textdomain();

        // Initialize plugin hooks
        $this->init_hooks();
    }

    /**
     * @return void
     */
    private function load_plugin_textdomain(): void {
        load_plugin_textdomain(
            'sicoob-payment',
            false,
            dirname(plugin_basename(SICOOB_PAYMENT_PLUGIN_DIR)) . '/languages/'
        );
    }

    /**
     * @return void
     */
    private function init_hooks(): void {
        // Register payment gateways
        add_filter('woocommerce_payment_gateways', array($this, 'add_payment_gateways'));

        // Register email classes
        add_filter('woocommerce_email_classes', array($this, 'add_email_classes'));

        // Initialize admin functionality
        if (is_admin()) {
            new WC_Sicoob_Payment_Admin();
        }

        // Initialize webhook handler
        new WC_Sicoob_Payment_Webhook();
    }

    /**
     * Add payment gateways to WooCommerce
     *
     * @param array $gateways List of existing gateways
     * @return array
     */
    public function add_payment_gateways($gateways): array {
        $gateways[] = 'WC_Sicoob_Pix_Gateway';
        $gateways[] = 'WC_Sicoob_Boleto_Gateway';
        
        return $gateways;
    }

    /**
     * Add email classes to WooCommerce
     *
     * @param array $email_classes List of existing email classes
     * @return array
     */
    public function add_email_classes($email_classes): array {
        // Load email class
        require_once SICOOB_PAYMENT_PLUGIN_DIR . 'includes/class-wc-sicoob-boleto-email.php';
        
        $email_classes['WC_Sicoob_Boleto_Email'] = new WC_Sicoob_Boleto_Email();
        
        return $email_classes;
    }

    /**
     * Log message using WooCommerce logger
     *
     * @param string $message Log message
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    public static function log_message($message, $level = 'info'): void {
        $config = get_option('sicoob_payment_config', array());
        
        if (isset($config['enable_logs']) && $config['enable_logs'] === 'yes') {
            $logger = wc_get_logger();
            $logger->log($level, $message, array('source' => 'sicoob-payment'));
        }
    }

    /**
     * Get authentication configuration
     *
     * @return array
     */
    public static function get_auth_config(): array {
        $config = get_option('sicoob_payment_config', array());
        return array(
            'client_id' => isset($config['client_id']) ? $config['client_id'] : '',
            'certificate_path' => isset($config['certificate_path']) ? $config['certificate_path'] : '',
        );
    }
}
