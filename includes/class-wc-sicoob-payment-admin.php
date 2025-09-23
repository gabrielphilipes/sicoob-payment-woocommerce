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
     * Display admin page
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="sicoob-payment-admin">
                <p><?php _e('Bem-vindo à área administrativa do Sicoob Payment.', 'sicoob-payment'); ?></p>
                <p><?php _e('Aqui você poderá configurar e gerenciar os pagamentos via PIX e Boleto do Sicoob.', 'sicoob-payment'); ?></p>
            </div>
        </div>
        <?php
    }
}
