<?php
/**
 * Plugin Name: Sicoob Payment
 * Plugin URI: https://sicoob.com.br
 * Description: Integração de pagamentos via boleto e PIX para WooCommerce
 * Version: 1.0.0
 * Author: Sicoob
 * Author URI: https://sicoob.com.br
 * Text Domain: sicoob-payment
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('SICOOB_PAYMENT_VERSION', '1.0.0');
define('SICOOB_PAYMENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SICOOB_PAYMENT_PLUGIN_URL', plugin_dir_url(__FILE__));

// Verifica se o WooCommerce está ativo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        ?>
        <div class="error">
            <p><?php esc_html_e('Sicoob Payment requer o WooCommerce ativo para funcionar.', 'sicoob-payment'); ?></p>
        </div>
        <?php
    });
    return;
}

// Declara compatibilidade com o High-Performance Order Storage do WooCommerce
add_action(
    'before_woocommerce_init',
    function() {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
);

// Carrega o autoloader do Composer
if (file_exists(SICOOB_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SICOOB_PAYMENT_PLUGIN_DIR . 'vendor/autoload.php';
}

// Inicializa o plugin
function sicoob_payment_init() {
    require_once SICOOB_PAYMENT_PLUGIN_DIR . '/includes/SicoobPayment.php';

    $plugin = \SicoobPayment\SicoobPayment::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'sicoob_payment_init'); 