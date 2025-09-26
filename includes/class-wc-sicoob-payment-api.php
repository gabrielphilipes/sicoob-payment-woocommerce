<?php
/**
 * Sicoob Payment API Class
 *
 * @package SicoobPayment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sicoob Payment API Class
 *
 * Handles all API communication with Sicoob payment services.
 * Provides methods for PIX and Boleto payment processing.
 *
 * @package SicoobPayment
 * @since 1.0.0
 */
class WC_Sicoob_Payment_API {

	/**
	 * Scopes for different payment types
	 */
	const PIX_SCOPE    = 'cob.read cob.write cobv.write cobv.read lotecobv.write lotecobv.read pix.write pix.read webhook.read webhook.write payloadlocation.write payloadlocation.read';
	const BOLETO_SCOPE = 'boletos_inclusao boletos_consulta boletos_alteracao';

	/**
	 * API Endpoints
	 */
	const AUTH_ENDPOINT   = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';
	const PIX_ENDPOINT    = 'https://api.sicoob.com.br/pix/api/v2';
	const BOLETO_ENDPOINT = 'https://api.sicoob.com.br/cobranca-bancaria/v3/boletos';

	/**
	 * Get authentication configuration
	 *
	 * @return array
	 */
	public static function get_auth_config(): array {
		return WC_Sicoob_Payment::get_auth_config();
	}

	/**
	 * Make authenticated request to Sicoob API (with token)
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data Request data.
	 * @param string $method HTTP method.
	 * @param string $scope Required scope for the request.
	 * @return array
	 */
	public static function make_authenticated_request( $endpoint, $data = array(), $method = 'POST', $scope = self::PIX_SCOPE ): array {
		// Get access token.
		$token_result = self::get_access_token( $scope );
		if ( ! $token_result['success'] ) {
			return $token_result;
		}

		$access_token = $token_result['data']['access_token'];

		// Prepare headers with authorization.
		$headers = array(
			'Authorization: Bearer ' . $access_token,
			'Content-Type: application/json',
			'Accept: application/json',
		);

		// Convert data to JSON for authenticated requests.
		$post_data = ! empty( $data ) ? wp_json_encode( $data ) : array();

		// Make authenticated request using cURL.
		return self::make_request( $endpoint, $post_data, $headers, $method );
	}

	/**
	 * Make cURL request to Sicoob API
	 *
	 * @param string       $url Request URL.
	 * @param array|string $post_data POST data (optional).
	 * @param array        $headers Additional headers (optional). To override Content-Type, include 'Content-Type: application/json' in headers.
	 * @param string       $method HTTP method (GET, POST, PUT, etc.).
	 * @return array
	 */
	private static function make_request( $url, $post_data = array(), $headers = array(), $method = 'POST' ): array {
		$auth_config = self::get_auth_config();

		// Validate required configuration.
		if ( empty( $auth_config['client_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'ID do Cliente não configurado.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		if ( empty( $auth_config['certificate_path'] ) || ! file_exists( $auth_config['certificate_path'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Certificado digital não configurado ou não encontrado.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Initialize cURL.
		// Note: Using cURL instead of wp_remote_request() because we need mTLS certificate authentication
		// which is not supported by WordPress HTTP API.
		$ch = curl_init();

		// Default headers.
		$default_headers = array(
			'Content-Type: application/x-www-form-urlencoded',
		);

		// Check if Content-Type is being overridden.
		$content_type_overridden = false;
		foreach ( $headers as $header ) {
			if ( stripos( $header, 'Content-Type:' ) === 0 ) {
				$content_type_overridden = true;
				break;
			}
		}

		// Merge headers - if Content-Type is overridden, don't add default.
		if ( $content_type_overridden ) {
			$all_headers = $headers;
		} else {
			$all_headers = array_merge( $default_headers, $headers );
		}

		// Basic cURL configuration.
		$curl_options = array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER     => $all_headers,

			// mTLS with certificates.
			CURLOPT_SSLCERT        => $auth_config['certificate_path'],
			CURLOPT_SSLKEY         => $auth_config['certificate_path'],
		);

		// Set method-specific options.
		if ( strtoupper( $method ) === 'POST' ) {
			$curl_options[ CURLOPT_POST ] = true;
			if ( ! empty( $post_data ) ) {
				$curl_options[ CURLOPT_POSTFIELDS ] = is_array( $post_data ) ? http_build_query( $post_data ) : $post_data;
			}
		} elseif ( strtoupper( $method ) === 'GET' ) {
			if ( ! empty( $post_data ) ) {
				$url_with_params             = $url . '?' . http_build_query( $post_data );
				$curl_options[ CURLOPT_URL ] = $url_with_params;
			}
		} else {
			$curl_options[ CURLOPT_CUSTOMREQUEST ] = strtoupper( $method );
			if ( ! empty( $post_data ) ) {
				$curl_options[ CURLOPT_POSTFIELDS ] = is_array( $post_data ) ? http_build_query( $post_data ) : $post_data;
			}
		}

		// Apply cURL options.
		curl_setopt_array( $ch, $curl_options );

		// Log request.
		WC_Sicoob_Payment::log_message(
			sprintf(
				/* translators: %1$s: HTTP method, %2$s: URL */
				__( 'Fazendo requisição cURL para %1$s: %2$s', 'sicoob-payment' ),
				$method,
				$url
			),
			'info'
		);

		// Execute request.
		$response = curl_exec( $ch );

		// Check for cURL errors.
		if ( curl_errno( $ch ) ) {
			$error = curl_error( $ch );
			curl_close( $ch );

			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: cURL error message */
					__( 'Erro cURL: %s', 'sicoob-payment' ),
					$error
				),
				'error'
			);

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: cURL error message */
					__( 'Erro na comunicação cURL: %s', 'sicoob-payment' ),
					$error
				),
				'data'    => null,
			);
		}

		// Get response information.
		$http_code    = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$content_type = curl_getinfo( $ch, CURLINFO_CONTENT_TYPE );

		curl_close( $ch );

		// Log response.
		WC_Sicoob_Payment::log_message(
			sprintf(
				/* translators: %1$d: HTTP status code, %2$s: content type, %3$s: response body */
				__( 'Resposta cURL - Status: %1$d, Content-Type: %2$s, Body: %3$s', 'sicoob-payment' ),
				$http_code,
				$content_type,
				$response
			),
			'info'
		);

		// Check if request was successful.
		if ( $http_code >= 200 && $http_code < 300 ) {
			// Try to decode JSON response.
			$data = json_decode( $response, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				return array(
					'success'      => true,
					'message'      => __( 'Requisição realizada com sucesso.', 'sicoob-payment' ),
					'data'         => $data,
					'http_code'    => $http_code,
					'content_type' => $content_type,
				);
			} else {
				return array(
					'success'      => true,
					'message'      => __( 'Requisição realizada com sucesso (resposta não-JSON).', 'sicoob-payment' ),
					'data'         => $response,
					'http_code'    => $http_code,
					'content_type' => $content_type,
				);
			}
		} else {
			// Try to decode error response.
			$error_data    = json_decode( $response, true );
			$error_message = isset( $error_data['error_description'] ) ? $error_data['error_description'] : __( 'Erro desconhecido na API.', 'sicoob-payment' );

			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %1$d: HTTP status code, %2$s: error message */
					__( 'Erro na API - Status: %1$d, Mensagem: %2$s', 'sicoob-payment' ),
					$http_code,
					$error_message
				),
				'error'
			);

			return array(
				'success'      => false,
				'message'      => sprintf(
					/* translators: %1$d: HTTP status code, %2$s: error message */
					__( 'Erro na API (Status %1$d): %2$s', 'sicoob-payment' ),
					$http_code,
					$error_message
				),
				'data'         => $error_data,
				'http_code'    => $http_code,
				'content_type' => $content_type,
			);
		}
	}

	/**
	 * Get access token from Sicoob OAuth
	 *
	 * @param string $scope Required scope.
	 * @return array
	 */
	public static function get_access_token( $scope ): array {
		$auth_config = self::get_auth_config();

		// Prepare token request data.
		$token_data = array(
			'grant_type' => 'client_credentials',
			'client_id'  => $auth_config['client_id'],
			'scope'      => $scope,
		);

		// Log token request.
		WC_Sicoob_Payment::log_message(
			sprintf(
				/* translators: %s: scope value */
				__( 'Solicitando token de acesso com scope: %s', 'sicoob-payment' ),
				$scope
			),
			'info'
		);

		// Make token request using cURL.
		$result = self::make_request( self::AUTH_ENDPOINT, $token_data );

		if ( ! $result['success'] ) {
			return $result;
		}

		// Check if access token is present.
		if ( isset( $result['data']['access_token'] ) ) {
			return array(
				'success' => true,
				'message' => __( 'Token de acesso obtido com sucesso.', 'sicoob-payment' ),
				'data'    => $result['data'],
			);
		} else {
			return array(
				'success' => false,
				'message' => __( 'Token de acesso não encontrado na resposta.', 'sicoob-payment' ),
				'data'    => $result['data'],
			);
		}
	}

	/**
	 * Create PIX COB (Cobrança Imediata)
	 *
	 * @param array  $order_data Order data with customer information.
	 * @param string $pix_key PIX key from gateway settings.
	 * @param string $pix_description PIX description from gateway settings.
	 * @return array
	 */
	public static function create_pix_cob( $order_data, $pix_key, $pix_description ): array {
		// Validate required parameters.
		if ( empty( $pix_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Chave PIX não configurada.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		if ( empty( $pix_description ) ) {
			return array(
				'success' => false,
				'message' => __( 'Descrição do PIX não configurada.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Validate order data.
		if ( empty( $order_data['cpf'] ) || empty( $order_data['nome'] ) || empty( $order_data['valor'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Dados do pedido incompletos (CPF, nome ou valor).', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Prepare PIX COB data according to Sicoob API specification.
		$pix_data = array(
			'calendario'         => array(
				'expiracao' => 108000, // 30 horas em segundos.
			),
			'devedor'            => array(
				'cpf'  => preg_replace( '/[^0-9]/', '', $order_data['cpf'] ), // Only numbers.
				'nome' => sanitize_text_field( $order_data['nome'] ),
			),
			'valor'              => array(
				'original' => number_format( $order_data['valor'], 2, '.', '' ), // Format as string with 2 decimals.
			),
			'chave'              => sanitize_text_field( $pix_key ),
			'solicitacaoPagador' => sanitize_text_field( $pix_description ),
		);

		// Log PIX creation attempt.
		WC_Sicoob_Payment::log_message(
			sprintf(
				/* translators: %1$s: customer name, %2$s: CPF, %3$s: amount */
				__( 'Criando PIX COB - Cliente: %1$s, CPF: %2$s, Valor: %3$s', 'sicoob-payment' ),
				$pix_data['devedor']['nome'],
				$pix_data['devedor']['cpf'],
				$pix_data['valor']['original']
			),
			'info'
		);

		// Make authenticated request to create PIX COB.
		$endpoint = self::PIX_ENDPOINT . '/cob';
		$result   = self::make_authenticated_request( $endpoint, $pix_data, 'POST', self::PIX_SCOPE );

		if ( $result['success'] ) {
			WC_Sicoob_Payment::log_message(
				__( 'PIX COB criado com sucesso.', 'sicoob-payment' ),
				'info'
			);
		} else {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: error message */
					__( 'Erro ao criar PIX COB: %s', 'sicoob-payment' ),
					$result['message']
				),
				'error'
			);
		}

		return $result;
	}

	/**
	 * Create Boleto
	 *
	 * @param array $order_data Order data with customer information.
	 * @return array
	 */
	public static function create_boleto( $order_data ): array {
		// Get boleto gateway settings.
		$boleto_settings = self::get_boleto_gateway_settings();

		// Validate required settings.
		if ( empty( $boleto_settings['account_number'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Número da conta corrente não configurado.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		if ( empty( $boleto_settings['contract_number'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Número do contrato não configurado.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Validate order data.
		if ( empty( $order_data['cpf'] ) || empty( $order_data['nome'] ) || empty( $order_data['valor'] ) || empty( $order_data['order_id'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Dados do pedido incompletos (CPF, nome, valor ou ID do pedido).', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Calculate dates.
		$current_date = gmdate( 'Y-m-d' );
		$due_date     = gmdate( 'Y-m-d', strtotime( '+' . $boleto_settings['due_days'] . ' days' ) );

		// Prepare boleto data according to Sicoob API specification.
		$boleto_data = array(
			'numeroCliente'                   => intval( $boleto_settings['contract_number'] ),
			'codigoModalidade'                => 1,
			'numeroContaCorrente'             => intval( $boleto_settings['account_number'] ),
			'codigoEspecieDocumento'          => 'DM',
			'dataEmissao'                     => $current_date,
			'seuNumero'                       => strval( $order_data['order_id'] ),
			'identificacaoEmissaoBoleto'      => 1,
			'identificacaoDistribuicaoBoleto' => 1,
			'valor'                           => floatval( $order_data['valor'] ),
			'dataVencimento'                  => $due_date,
			'dataLimitePagamento'             => $due_date,
			'tipoDesconto'                    => 0,
			'tipoMulta'                       => 0,
			'tipoJurosMora'                   => 3,
			'numeroParcela'                   => 1,
			'pagador'                         => array(
				'numeroCpfCnpj' => preg_replace( '/[^0-9]/', '', $order_data['cpf'] ), // Only numbers.
				'nome'          => sanitize_text_field( $order_data['nome'] ),
				'endereco'      => sanitize_text_field( $order_data['endereco'] ?? '' ),
				'bairro'        => sanitize_text_field( $order_data['bairro'] ?? '' ),
				'cidade'        => sanitize_text_field( $order_data['cidade'] ?? '' ),
				'cep'           => preg_replace( '/[^0-9]/', '', $order_data['cep'] ?? '' ),
				'uf'            => sanitize_text_field( $order_data['uf'] ?? '' ),
				'email'         => sanitize_email( $order_data['email'] ?? '' ),
			),
			'mensagensInstrucao'              => array_filter(
				array(
					sanitize_text_field( $boleto_settings['instruction_1'] ?? '' ),
					sanitize_text_field( $boleto_settings['instruction_2'] ?? '' ),
					sanitize_text_field( $boleto_settings['instruction_3'] ?? '' ),
					sanitize_text_field( $boleto_settings['instruction_4'] ?? '' ),
					sanitize_text_field( $boleto_settings['instruction_5'] ?? '' ),
				)
			),
			'gerarPdf'                        => true,
			'codigoCadastrarPIX'              => 1,
		);

		// Log boleto creation attempt.
		WC_Sicoob_Payment::log_message(
			sprintf(
				/* translators: %1$s: customer name, %2$s: CPF, %3$s: amount, %4$s: order number */
				__( 'Criando Boleto - Cliente: %1$s, CPF: %2$s, Valor: %3$s, Pedido: %4$s', 'sicoob-payment' ),
				$boleto_data['pagador']['nome'],
				$boleto_data['pagador']['numeroCpfCnpj'],
				$boleto_data['valor'],
				$boleto_data['seuNumero']
			),
			'info'
		);

		// Make authenticated request to create boleto.
		$endpoint = self::BOLETO_ENDPOINT;
		$result   = self::make_authenticated_request( $endpoint, $boleto_data, 'POST', self::BOLETO_SCOPE );

		if ( $result['success'] ) {
			// Process successful response.
			$processed_result = self::process_boleto_response( $result['data'], $order_data['order_id'] );

			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: boleto number */
					__( 'Boleto criado com sucesso - Nosso Número: %s', 'sicoob-payment' ),
					$processed_result['data']['nosso_numero'] ?? 'N/A'
				),
				'info'
			);

			return $processed_result;
		} else {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: error message */
					__( 'Erro ao criar boleto: %s', 'sicoob-payment' ),
					$result['message']
				),
				'error'
			);
		}

		return $result;
	}

	/**
	 * Process boleto response from Sicoob API
	 *
	 * @param array  $api_response Raw API response.
	 * @param string $order_id Order ID.
	 * @return array
	 */
	public static function process_boleto_response( $api_response, $order_id ): array {
		if ( ! isset( $api_response['resultado'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Resposta da API inválida.', 'sicoob-payment' ),
				'data'    => $api_response,
			);
		}

		$boleto_data = $api_response['resultado'];

		// Extract important data.
		$processed_data = array(
			'order_id'            => $order_id,
			'nosso_numero'        => $boleto_data['nossoNumero'] ?? '',
			'seu_numero'          => $boleto_data['seuNumero'] ?? '',
			'codigo_barras'       => $boleto_data['codigoBarras'] ?? '',
			'linha_digitavel'     => $boleto_data['linhaDigitavel'] ?? '',
			'valor'               => $boleto_data['valor'] ?? 0,
			'data_vencimento'     => $boleto_data['dataVencimento'] ?? '',
			'data_emissao'        => $boleto_data['dataEmissao'] ?? '',
			'pdf_base64'          => $boleto_data['pdfBoleto'] ?? '',
			'qr_code'             => $boleto_data['qrCode'] ?? '',
			'pagador'             => $boleto_data['pagador'] ?? array(),
			'mensagens_instrucao' => $boleto_data['mensagensInstrucao'] ?? array(),
			'raw_response'        => $boleto_data,
		);

		// Save PDF to file if base64 is provided.
		if ( ! empty( $processed_data['pdf_base64'] ) ) {
			$pdf_saved                   = self::save_boleto_pdf( $processed_data['pdf_base64'], $order_id );
			$processed_data['pdf_saved'] = $pdf_saved;
		}

		return array(
			'success' => true,
			'message' => __( 'Boleto criado com sucesso.', 'sicoob-payment' ),
			'data'    => $processed_data,
		);
	}

	/**
	 * Save boleto PDF from base64
	 *
	 * @param string $pdf_base64 Base64 encoded PDF.
	 * @param string $order_id Order ID.
	 * @return array
	 */
	public static function save_boleto_pdf( $pdf_base64, $order_id ): array {
		try {
			// Decode base64.
			$pdf_content = base64_decode( $pdf_base64 );

			if ( false === $pdf_content ) {
				return array(
					'success' => false,
					'message' => __( 'Erro ao decodificar PDF do boleto.', 'sicoob-payment' ),
				);
			}

			// Create uploads directory for boleto PDFs.
			$upload_dir = wp_upload_dir();
			$boleto_dir = $upload_dir['basedir'] . '/sicoob-boletos';

			if ( ! file_exists( $boleto_dir ) ) {
				wp_mkdir_p( $boleto_dir );
			}

			// Generate filename.
			$filename  = 'boleto-' . $order_id . '-' . gmdate( 'Y-m-d-H-i-s' ) . '.pdf';
			$file_path = $boleto_dir . '/' . $filename;

			// Save file.
			// Note: Using file_put_contents() instead of WP_Filesystem for PDF binary data
			// as WP_Filesystem may corrupt binary content during encoding/decoding.
			$saved = file_put_contents( $file_path, $pdf_content );

			if ( false === $saved ) {
				return array(
					'success' => false,
					'message' => __( 'Erro ao salvar PDF do boleto.', 'sicoob-payment' ),
				);
			}

			// Get file URL.
			$file_url = $upload_dir['baseurl'] . '/sicoob-boletos/' . $filename;

			return array(
				'success'   => true,
				'message'   => __( 'PDF do boleto salvo com sucesso.', 'sicoob-payment' ),
				'file_path' => $file_path,
				'file_url'  => $file_url,
				'file_size' => $saved,
			);

		} catch ( Exception $e ) {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: error message */
					__( 'Erro ao salvar PDF do boleto: %s', 'sicoob-payment' ),
					$e->getMessage()
				),
				'error'
			);

			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Erro ao salvar PDF: %s', 'sicoob-payment' ),
					$e->getMessage()
				),
			);
		}
	}

	/**
	 * Get PIX gateway settings
	 *
	 * @return array
	 */
	public static function get_pix_gateway_settings(): array {
		$gateway = new WC_Sicoob_Pix_Gateway();

		return array(
			'pix_key'         => $gateway->get_option( 'pix_key' ),
			'pix_description' => $gateway->get_option( 'pix_description' ),
		);
	}

	/**
	 * Get Boleto gateway settings
	 *
	 * @return array
	 */
	public static function get_boleto_gateway_settings(): array {
		$gateway = new WC_Sicoob_Boleto_Gateway();

		return array(
			'account_number'  => $gateway->get_option( 'account_number' ),
			'contract_number' => $gateway->get_option( 'contract_number' ),
			'due_days'        => $gateway->get_option( 'due_days', 3 ),
			'instruction_1'   => $gateway->get_option( 'instruction_1' ),
			'instruction_2'   => $gateway->get_option( 'instruction_2' ),
			'instruction_3'   => $gateway->get_option( 'instruction_3' ),
			'instruction_4'   => $gateway->get_option( 'instruction_4' ),
			'instruction_5'   => $gateway->get_option( 'instruction_5' ),
		);
	}

	/**
	 * Register PIX webhook
	 *
	 * @param string $pix_key PIX key..
	 * @param string $webhook_url Webhook URL.
	 * @return array
	 */
	public static function register_pix_webhook( $pix_key, $webhook_url ): array {
		// Validate parameters.
		if ( empty( $pix_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Chave PIX não fornecida.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		if ( empty( $webhook_url ) || ! filter_var( $webhook_url, FILTER_VALIDATE_URL ) ) {
			return array(
				'success' => false,
				'message' => __( 'URL do webhook inválida.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Prepare webhook data.
		$webhook_data = array(
			'webhookUrl' => $webhook_url,
		);

		// Make authenticated PUT request to register webhook.
		$endpoint = self::PIX_ENDPOINT . '/webhook/' . rawurlencode( $pix_key );
		$result   = self::make_authenticated_request( $endpoint, $webhook_data, 'PUT', self::PIX_SCOPE );

		if ( $result['success'] ) {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %1$s: PIX key, %2$s: webhook URL */
					__( 'Webhook PIX registrado com sucesso - Chave: %1$s, URL: %2$s', 'sicoob-payment' ),
					$pix_key,
					$webhook_url
				),
				'info'
			);
		} else {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: error message */
					__( 'Erro ao registrar webhook PIX: %s', 'sicoob-payment' ),
					$result['message']
				),
				'error'
			);
		}

		return $result;
	}

	/**
	 * Unregister PIX webhook
	 *
	 * @param string $pix_key PIX key.
	 * @return array
	 */
	public static function unregister_pix_webhook( $pix_key ): array {
		// Validate parameters.
		if ( empty( $pix_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Chave PIX não fornecida.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Make authenticated DELETE request to unregister webhook.
		$endpoint = self::PIX_ENDPOINT . '/webhook/' . rawurlencode( $pix_key );
		$result   = self::make_authenticated_request( $endpoint, array(), 'DELETE', self::PIX_SCOPE );

		if ( $result['success'] ) {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: PIX key */
					__( 'Webhook PIX removido com sucesso - Chave: %s', 'sicoob-payment' ),
					$pix_key
				),
				'info'
			);
		} else {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: error message */
					__( 'Erro ao remover webhook PIX: %s', 'sicoob-payment' ),
					$result['message']
				),
				'error'
			);
		}

		return $result;
	}

	/**
	 * Get PIX webhook status
	 *
	 * @param string $pix_key PIX key.
	 * @return array
	 */
	public static function get_pix_webhook_status( $pix_key ): array {
		// Validate parameters.
		if ( empty( $pix_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Chave PIX não fornecida.', 'sicoob-payment' ),
				'data'    => null,
			);
		}

		// Make authenticated GET request to check webhook status.
		$endpoint = self::PIX_ENDPOINT . '/webhook/' . rawurlencode( $pix_key );
		$result   = self::make_authenticated_request( $endpoint, array(), 'GET', self::PIX_SCOPE );

		if ( $result['success'] ) {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: PIX key */
					__( 'Status do webhook PIX consultado - Chave: %s', 'sicoob-payment' ),
					$pix_key
				),
				'info'
			);
		} else {
			WC_Sicoob_Payment::log_message(
				sprintf(
					/* translators: %s: error message */
					__( 'Erro ao consultar status do webhook PIX: %s', 'sicoob-payment' ),
					$result['message']
				),
				'error'
			);
		}

		return $result;
	}
}
