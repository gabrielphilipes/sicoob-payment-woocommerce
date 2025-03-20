<?php
/**
 * Classe principal do plugin Sicoob Payment
 *
 * @package SicoobPayment
 */

namespace SicoobPayment;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principal do plugin
 */
class SicoobPayment {
    /**
     * Instância única da classe
     *
     * @var SicoobPayment
     */
    private static $instance = null;

    /**
     * Construtor privado para implementar o padrão Singleton
     */
    private function __construct() {
        // Inicialização privada
    }

    /**
     * Obtém a instância única da classe
     *
     * @return SicoobPayment
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializa o plugin
     *
     * @return void
     */
    public function init() {
        // Carrega as traduções
        $this->load_plugin_textdomain();

        // Inicializa os hooks do plugin
        $this->init_hooks();
    }

    /**
     * Carrega as traduções do plugin
     *
     * @return void
     */
    private function load_plugin_textdomain() {
        load_plugin_textdomain(
            'sicoob-payment',
            false,
            dirname(plugin_basename(SICOOB_PAYMENT_PLUGIN_DIR)) . '/languages/'
        );
    }

    /**
     * Inicializa os hooks do plugin
     *
     * @return void
     */
    private function init_hooks() {
        // Aqui serão adicionados os hooks do plugin
    }
} 