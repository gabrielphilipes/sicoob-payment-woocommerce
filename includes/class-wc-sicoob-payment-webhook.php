<?php
/**
 * Sicoob Payment Webhook Handler
 *
 * @package SicoobPayment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Sicoob_Payment_Webhook class.
 *
 * @package SicoobPayment
 */
class WC_Sicoob_Payment_Webhook {

	/**
	 * Webhook Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_webhook_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'handle_webhook_request' ) );
	}

	/**
	 * Initialize webhook endpoint
	 */
	public function init_webhook_endpoint() {
		add_rewrite_rule(
			'^webhook/sicoob/pix/?$',
			'index.php?sicoob_webhook=pix',
			'top'
		);
	}

	/**
	 * Handle webhook request
	 */
	public function handle_webhook_request() {
		// Check if this is our webhook URL.
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		// Remove query parameters and normalize.
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$path = trim( $path, '/' );

		// Check if it matches our webhook path.
		if ( 'webhook/sicoob/pix' !== $path ) {
			return;
		}

		// Only allow POST requests.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			http_response_code( 405 );
			exit( 'Method Not Allowed' );
		}

		// Log webhook received.
		WC_Sicoob_Payment::log_message(
			__( 'Webhook PIX recebido do Sicoob', 'sicoob-payment' ),
			'info'
		);

		// Get raw POST data.
		$raw_data = file_get_contents( 'php://input' );

		if ( empty( $raw_data ) ) {
			http_response_code( 400 );
			exit( 'Bad Request - No data received' );
		}

		// Log raw data for debugging.
		WC_Sicoob_Payment::log_message(
			// translators: %s is the raw webhook data.
			sprintf( __( 'Dados recebidos do webhook: %s', 'sicoob-payment' ), $raw_data ),
			'info'
		);

		// Parse JSON data.
		$webhook_data = json_decode( $raw_data, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			WC_Sicoob_Payment::log_message(
				// translators: %s is the JSON error message.
				sprintf( __( 'Erro ao decodificar JSON do webhook: %s', 'sicoob-payment' ), json_last_error_msg() ),
				'error'
			);
			http_response_code( 400 );
			exit( 'Invalid JSON' );
		}

		// Validate webhook structure.
		if ( ! $this->validate_webhook_data( $webhook_data ) ) {
			WC_Sicoob_Payment::log_message(
				__( 'Estrutura de dados do webhook inválida', 'sicoob-payment' ),
				'error'
			);
			http_response_code( 400 );
			exit( 'Invalid webhook data structure' );
		}

		// Process webhook.
		$this->process_webhook( $webhook_data );

		// Return success response.
		http_response_code( 200 );
		echo 'OK';
		exit;
	}

	/**
	 * Validate webhook data structure
	 *
	 * @param array $data Webhook data.
	 * @return bool
	 */
	private function validate_webhook_data( $data ) {
		// Check if pix array exists and is not empty.
		if ( ! isset( $data['pix'] ) || ! is_array( $data['pix'] ) || empty( $data['pix'] ) ) {
			return false;
		}

		// Validate each PIX transaction.
		foreach ( $data['pix'] as $pix_transaction ) {
			if ( ! isset( $pix_transaction['txid'] ) ||
				! isset( $pix_transaction['valor'] ) ||
				! isset( $pix_transaction['horario'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Process webhook data
	 *
	 * @param array $webhook_data Webhook data.
	 */
	private function process_webhook( $webhook_data ) {
		foreach ( $webhook_data['pix'] as $pix_transaction ) {
			$this->process_pix_transaction( $pix_transaction );
		}
	}

	/**
	 * Process individual PIX transaction
	 *
	 * @param array $pix_transaction PIX transaction data.
	 */
	private function process_pix_transaction( $pix_transaction ) {
		$txid          = sanitize_text_field( $pix_transaction['txid'] );
		$valor         = floatval( $pix_transaction['valor'] );
		$horario       = sanitize_text_field( $pix_transaction['horario'] );
		$end_to_end_id = isset( $pix_transaction['endToEndId'] ) ? sanitize_text_field( $pix_transaction['endToEndId'] ) : '';
		$info_pagador  = isset( $pix_transaction['infoPagador'] ) ? sanitize_text_field( $pix_transaction['infoPagador'] ) : '';

		// Log transaction details.
		WC_Sicoob_Payment::log_message(
			sprintf(
				// translators: %1$s is the TXID, %2$s is the amount, %3$s is the time.
				__( 'Processando transação PIX - TXID: %1$s, Valor: R$ %2$s, Horário: %3$s', 'sicoob-payment' ),
				$txid,
				number_format( $valor, 2, ',', '.' ),
				$horario
			),
			'info'
		);

		// Find order by txid.
		$order = $this->find_order_by_txid( $txid );

		if ( ! $order ) {
			WC_Sicoob_Payment::log_message(
				// translators: %s is the TXID.
				sprintf( __( 'Pedido não encontrado para TXID: %s', 'sicoob-payment' ), $txid ),
				'warning'
			);
			return;
		}

		// Check if order is already processed.
		if ( 'processing' === $order->get_status() || 'completed' === $order->get_status() ) {
			WC_Sicoob_Payment::log_message(
				// translators: %s is the order ID.
				sprintf( __( 'Pedido #%s já foi processado anteriormente', 'sicoob-payment' ), $order->get_id() ),
				'info'
			);
			return;
		}

		// Validate payment amount.
		if ( ! $this->validate_payment_amount( $order, $valor ) ) {
			WC_Sicoob_Payment::log_message(
				sprintf(
					// translators: %1$s is the order total, %2$s is the paid amount.
					__( 'Valor do pagamento não confere - Pedido: R$ %1$s, Pago: R$ %2$s', 'sicoob-payment' ),
					number_format( $order->get_total(), 2, ',', '.' ),
					number_format( $valor, 2, ',', '.' )
				),
				'error'
			);
			return;
		}

		// Process payment.
		$this->process_payment( $order, $pix_transaction );
	}

	/**
	 * Find order by txid
	 *
	 * @param string $txid Transaction ID.
	 * @return WC_Order|false
	 */
	private function find_order_by_txid( $txid ) {
		global $wpdb;

		// Search for order with matching txid in meta.
		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}wc_orders_meta 
             WHERE meta_key = '_sicoob_pix_txid' 
             AND meta_value = %s",
				$txid
			)
		);

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order || 'sicoob_pix' !== $order->get_payment_method() ) {
			return false;
		}

		return $order;
	}

	/**
	 * Validate payment amount
	 *
	 * @param WC_Order $order Order object.
	 * @param float    $paid_amount Amount paid.
	 * @return bool
	 */
	private function validate_payment_amount( $order, $paid_amount ) {
		$order_total = floatval( $order->get_total() );
		$tolerance   = 0.01; // 1 cent tolerance for floating point precision.

		return abs( $order_total - $paid_amount ) <= $tolerance;
	}

	/**
	 * Process payment for order
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $pix_transaction PIX transaction data.
	 */
	private function process_payment( $order, $pix_transaction ) {
		try {
			// Update order meta with payment details.
			$order->update_meta_data( '_sicoob_pix_paid', 'yes' );
			$order->update_meta_data( '_sicoob_pix_payment_time', $pix_transaction['horario'] );
			$order->update_meta_data( '_sicoob_pix_end_to_end_id', $pix_transaction['endToEndId'] ?? '' );
			$order->update_meta_data( '_sicoob_pix_payer_info', $pix_transaction['infoPagador'] ?? '' );
			$order->update_meta_data( '_sicoob_pix_webhook_processed', current_time( 'mysql' ) );

			// Add order note.
			$order->add_order_note(
				sprintf(
					// translators: %1$s is the TXID, %2$s is the amount.
					__( 'Pagamento PIX confirmado via webhook. TXID: %1$s, Valor: R$ %2$s', 'sicoob-payment' ),
					$pix_transaction['txid'],
					number_format( $pix_transaction['valor'], 2, ',', '.' )
				)
			);

			// Update order status to processing.
			$order->update_status( 'processing', __( 'Pagamento PIX confirmado.', 'sicoob-payment' ) );

			// Reduce stock if needed.
			if ( 'processing' === $order->get_status() ) {
				wc_reduce_stock_levels( $order->get_id() );
			}

			// Log successful processing.
			WC_Sicoob_Payment::log_message(
				sprintf(
					// translators: %1$s is the order ID, %2$s is the TXID.
					__( 'Pagamento PIX processado com sucesso - Pedido #%1$s, TXID: %2$s', 'sicoob-payment' ),
					$order->get_id(),
					$pix_transaction['txid']
				),
				'info'
			);
		} catch ( Exception $e ) {
			// Log error.
			WC_Sicoob_Payment::log_message(
				sprintf(
					// translators: %1$s is the order ID, %2$s is the error message.
					__( 'Erro ao processar pagamento PIX - Pedido #%1$s: %2$s', 'sicoob-payment' ),
					$order->get_id(),
					$e->getMessage()
				),
				'error'
			);

			// Add error note to order.
			$order->add_order_note(
				sprintf(
					// translators: %s is the error message.
					__( 'Erro ao processar pagamento PIX via webhook: %s', 'sicoob-payment' ),
					$e->getMessage()
				)
			);
		}
	}
}
