<?php
/**
 * Sicoob Boleto Email Class
 *
 * @package SicoobPayment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Sicoob_Boleto_Email class.
 *
 * @package SicoobPayment
 */
class WC_Sicoob_Boleto_Email extends WC_Email {

	/**
	 * Boleto data
	 *
	 * @var array
	 */
	public $boleto_data = array();

	/**
	 * Email Constructor
	 */
	public function __construct() {
		$this->id             = 'sicoob_boleto_email';
		$this->title          = __( 'E-mail do Boleto Sicoob', 'sicoob-payment' );
		$this->description    = __( 'E-mail enviado ao cliente com os dados do boleto bancário.', 'sicoob-payment' );
		$this->template_html  = 'emails/sicoob-boleto.php';
		$this->template_plain = 'emails/plain/sicoob-boleto.php';
		$this->template_base  = SICOOB_PAYMENT_PLUGIN_DIR . 'templates/';

		// Configurações padrão.
		$this->heading = __( 'Seu boleto bancário está pronto!', 'sicoob-payment' );
		$this->subject = __( 'Boleto bancário - Pedido #{order_number}', 'sicoob-payment' );

		// Triggers para este e-mail.
		add_action( 'sicoob_boleto_email_notification', array( $this, 'trigger' ), 10, 2 );

		// Chama o construtor pai.
		parent::__construct();

		// E-mail do destinatário padrão.
		$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Trigger email sending
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $boleto_data Boleto data.
	 */
	public function trigger( $order, $boleto_data = array() ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$this->object      = $order;
		$this->recipient   = $order->get_billing_email();
		$this->boleto_data = $boleto_data;

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send(
				$this->get_recipient(),
				$this->get_subject(),
				$this->get_content(),
				$this->get_headers(),
				$this->get_attachments()
			);
		}
	}

	/**
	 * Get content html
	 *
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'order'         => $this->object,
				'boleto_data'   => $this->boleto_data,
				'email_heading' => $this->get_heading(),
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Get content plain
	 *
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'order'         => $this->object,
				'boleto_data'   => $this->boleto_data,
				'email_heading' => $this->get_heading(),
				'email'         => $this,
			),
			'',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Initialize form fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Habilitar/Desabilitar', 'sicoob-payment' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar este e-mail', 'sicoob-payment' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => __( 'Assunto', 'sicoob-payment' ),
				'type'        => 'text',
				'description' => __( 'Assunto do e-mail', 'sicoob-payment' ),
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Cabeçalho', 'sicoob-payment' ),
				'type'        => 'text',
				'description' => __( 'Cabeçalho do e-mail', 'sicoob-payment' ),
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'email_type' => array(
				'title'       => __( 'Formato do E-mail', 'sicoob-payment' ),
				'type'        => 'select',
				'description' => __( 'Escolha qual formato de e-mail deve ser enviado.', 'sicoob-payment' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
			),
		);
	}

	/**
	 * Get default subject
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Boleto bancário - Pedido #{order_number}', 'sicoob-payment' );
	}

	/**
	 * Get default heading
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Seu boleto bancário está pronto!', 'sicoob-payment' );
	}

	/**
	 * Get custom header text
	 *
	 * @return string
	 */
	public function get_custom_header_text() {
		return __( 'Olá! Seu boleto bancário foi gerado com sucesso. Segue abaixo as informações para pagamento.', 'sicoob-payment' );
	}

	/**
	 * Get attachments
	 *
	 * @return array
	 */
	public function get_attachments() {
		$attachments = array();

		if ( isset( $this->boleto_data['pdf_url'] ) && ! empty( $this->boleto_data['pdf_url'] ) ) {
			$pdf_path = str_replace( home_url( '/' ), ABSPATH, $this->boleto_data['pdf_url'] );
			if ( file_exists( $pdf_path ) ) {
				$attachments[] = $pdf_path;
			}
		}

		return $attachments;
	}
}
