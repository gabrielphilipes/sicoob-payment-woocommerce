<?php
/**
 * Principal classe do plugin Sicoob Payment
 *
 * @package SicoobPayment
 */

namespace SicoobPayment;

if (!defined('ABSPATH')) {
    exit;
}

class SicoobPayment {
    /**
     * @var SicoobPayment
     */
    private static $instance = null;
    
    private function __construct() {
        // Inicialização privada
    }

    /**
     * @return SicoobPayment
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
        // Carrega as traduções
        $this->load_plugin_textdomain();

        // Inicializa os hooks do plugin
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
        // Aqui serão adicionados os hooks do plugin
    }
} 