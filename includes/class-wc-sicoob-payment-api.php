<?php
/**
 * Sicoob Payment API Class
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Sicoob_Payment_API {

    /**
     * Scopes for different payment types
     */
    const PIX_SCOPE = 'cob.read cob.write cobv.write cobv.read lotecobv.write lotecobv.read pix.write pix.read webhook.read webhook.write payloadlocation.write payloadlocation.read';
    const BOLETO_SCOPE = 'boletos_inclusao boletos_consulta boletos_alteracao';

    /**
     * API Endpoints
     */
    const AUTH_ENDPOINT = 'https://auth.sicoob.com.br/auth/realms/cooperado/protocol/openid-connect/token';
    const PIX_ENDPOINT = 'https://api.sicoob.com.br/pix/api/v2';
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
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @param string $scope Required scope for the request
     * @return array
     */
    public static function make_authenticated_request($endpoint, $data = array(), $method = 'POST', $scope = self::PIX_SCOPE): array {
        // Get access token
        $token_result = self::get_access_token($scope);
        if (!$token_result['success']) {
            return $token_result;
        }

        $access_token = $token_result['data']['access_token'];

        // Prepare headers with authorization
        $headers = array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'Accept: application/json'
        );

        // Convert data to JSON for authenticated requests
        $post_data = !empty($data) ? json_encode($data) : array();

        // Make authenticated request using cURL
        return self::make_request($endpoint, $post_data, $headers, $method);
    }

    /**
     * Make cURL request to Sicoob API
     *
     * @param string $url Request URL
     * @param array $post_data POST data (optional)
     * @param array $headers Additional headers (optional). To override Content-Type, include 'Content-Type: application/json' in headers
     * @param string $method HTTP method (GET, POST, PUT, etc.)
     * @return array
     */
    private static function make_request($url, $post_data = array(), $headers = array(), $method = 'POST'): array {
        $auth_config = self::get_auth_config();

        // Validate required configuration
        if (empty($auth_config['client_id'])) {
            return array(
                'success' => false,
                'message' => __('ID do Cliente não configurado.', 'sicoob-payment'),
                'data' => null
            );
        }

        if (empty($auth_config['certificate_path']) || !file_exists($auth_config['certificate_path'])) {
            return array(
                'success' => false,
                'message' => __('Certificado digital não configurado ou não encontrado.', 'sicoob-payment'),
                'data' => null
            );
        }

        // Initialize cURL
        $ch = curl_init();

        // Default headers
        $default_headers = array(
            'Content-Type: application/x-www-form-urlencoded'
        );

        // Check if Content-Type is being overridden
        $content_type_overridden = false;
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') === 0) {
                $content_type_overridden = true;
                break;
            }
        }

        // Merge headers - if Content-Type is overridden, don't add default
        if ($content_type_overridden) {
            $all_headers = $headers;
        } else {
            $all_headers = array_merge($default_headers, $headers);
        }

        // Basic cURL configuration
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $all_headers,
            
            // mTLS with certificates
            CURLOPT_SSLCERT => $auth_config['certificate_path'],
            CURLOPT_SSLKEY => $auth_config['certificate_path'],
        );

        // Set method-specific options
        if (strtoupper($method) === 'POST') {
            $curl_options[CURLOPT_POST] = true;
            if (!empty($post_data)) {
                $curl_options[CURLOPT_POSTFIELDS] = is_array($post_data) ? http_build_query($post_data) : $post_data;
            }
        } elseif (strtoupper($method) === 'GET') {
            if (!empty($post_data)) {
                $url_with_params = $url . '?' . http_build_query($post_data);
                $curl_options[CURLOPT_URL] = $url_with_params;
            }
        } else {
            $curl_options[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
            if (!empty($post_data)) {
                $curl_options[CURLOPT_POSTFIELDS] = is_array($post_data) ? http_build_query($post_data) : $post_data;
            }
        }

        // Apply cURL options
        curl_setopt_array($ch, $curl_options);

        // Log request
        WC_Sicoob_Payment::log_message(
            sprintf(
                __('Fazendo requisição cURL para %s: %s', 'sicoob-payment'),
                $method,
                $url
            ),
            'info'
        );

        // Execute request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            
            WC_Sicoob_Payment::log_message(
                sprintf(__('Erro cURL: %s', 'sicoob-payment'), $error),
                'error'
            );

            return array(
                'success' => false,
                'message' => sprintf(__('Erro na comunicação cURL: %s', 'sicoob-payment'), $error),
                'data' => null
            );
        }

        // Get response information
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        curl_close($ch);

        // Log response
        WC_Sicoob_Payment::log_message(
            sprintf(
                __('Resposta cURL - Status: %d, Content-Type: %s, Body: %s', 'sicoob-payment'),
                $http_code,
                $content_type,
                $response
            ),
            'info'
        );

        // Check if request was successful
        if ($http_code >= 200 && $http_code < 300) {
            // Try to decode JSON response
            $data = json_decode($response, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return array(
                    'success' => true,
                    'message' => __('Requisição realizada com sucesso.', 'sicoob-payment'),
                    'data' => $data,
                    'http_code' => $http_code,
                    'content_type' => $content_type
                );
            } else {
                return array(
                    'success' => true,
                    'message' => __('Requisição realizada com sucesso (resposta não-JSON).', 'sicoob-payment'),
                    'data' => $response,
                    'http_code' => $http_code,
                    'content_type' => $content_type
                );
            }
        } else {
            // Try to decode error response
            $error_data = json_decode($response, true);
            $error_message = isset($error_data['error_description']) ? $error_data['error_description'] : __('Erro desconhecido na API.', 'sicoob-payment');
            
            WC_Sicoob_Payment::log_message(
                sprintf(__('Erro na API - Status: %d, Mensagem: %s', 'sicoob-payment'), $http_code, $error_message),
                'error'
            );

            return array(
                'success' => false,
                'message' => sprintf(__('Erro na API (Status %d): %s', 'sicoob-payment'), $http_code, $error_message),
                'data' => $error_data,
                'http_code' => $http_code,
                'content_type' => $content_type
            );
        }
    }

    /**
     * Get access token from Sicoob OAuth
     *
     * @param string $scope Required scope
     * @return array
     */
    public static function get_access_token($scope): array {
        $auth_config = self::get_auth_config();

        // Prepare token request data
        $token_data = array(
            'grant_type' => 'client_credentials',
            'client_id' => $auth_config['client_id'],
            'scope' => $scope
        );

        // Log token request
        WC_Sicoob_Payment::log_message(
            sprintf(__('Solicitando token de acesso com scope: %s', 'sicoob-payment'), $scope),
            'info'
        );

        // Make token request using cURL
        $result = self::make_request(self::AUTH_ENDPOINT, $token_data);

        if (!$result['success']) {
            return $result;
        }

        // Check if access token is present
        if (isset($result['data']['access_token'])) {
            return array(
                'success' => true,
                'message' => __('Token de acesso obtido com sucesso.', 'sicoob-payment'),
                'data' => $result['data']
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Token de acesso não encontrado na resposta.', 'sicoob-payment'),
                'data' => $result['data']
            );
        }
    }

    /**
     * Create PIX COB (Cobrança Imediata)
     *
     * @param array $order_data Order data with customer information
     * @param string $pix_key PIX key from gateway settings
     * @param string $pix_description PIX description from gateway settings
     * @return array
     */
    public static function create_pix_cob($order_data, $pix_key, $pix_description): array {
        // Validate required parameters
        if (empty($pix_key)) {
            return array(
                'success' => false,
                'message' => __('Chave PIX não configurada.', 'sicoob-payment'),
                'data' => null
            );
        }

        if (empty($pix_description)) {
            return array(
                'success' => false,
                'message' => __('Descrição do PIX não configurada.', 'sicoob-payment'),
                'data' => null
            );
        }

        // Validate order data
        if (empty($order_data['cpf']) || empty($order_data['nome']) || empty($order_data['valor'])) {
            return array(
                'success' => false,
                'message' => __('Dados do pedido incompletos (CPF, nome ou valor).', 'sicoob-payment'),
                'data' => null
            );
        }

        // Prepare PIX COB data according to Sicoob API specification
        $pix_data = array(
            'calendario' => array(
                'expiracao' => 108000 // 30 horas em segundos
            ),
            'devedor' => array(
                'cpf' => preg_replace('/[^0-9]/', '', $order_data['cpf']), // Only numbers
                'nome' => sanitize_text_field($order_data['nome'])
            ),
            'valor' => array(
                'original' => number_format($order_data['valor'], 2, '.', '') // Format as string with 2 decimals
            ),
            'chave' => sanitize_text_field($pix_key),
            'solicitacaoPagador' => sanitize_text_field($pix_description)
        );

        // Log PIX creation attempt
        WC_Sicoob_Payment::log_message(
            sprintf(
                __('Criando PIX COB - Cliente: %s, CPF: %s, Valor: %s', 'sicoob-payment'),
                $pix_data['devedor']['nome'],
                $pix_data['devedor']['cpf'],
                $pix_data['valor']['original']
            ),
            'info'
        );

        // Make authenticated request to create PIX COB
        $endpoint = self::PIX_ENDPOINT . '/cob';
        $result = self::make_authenticated_request($endpoint, $pix_data, 'POST', self::PIX_SCOPE);

        if ($result['success']) {
            WC_Sicoob_Payment::log_message(
                __('PIX COB criado com sucesso.', 'sicoob-payment'),
                'info'
            );
        } else {
            WC_Sicoob_Payment::log_message(
                sprintf(__('Erro ao criar PIX COB: %s', 'sicoob-payment'), $result['message']),
                'error'
            );
        }

        return $result;
    }

    /**
     * Get PIX gateway settings
     *
     * @return array
     */
    public static function get_pix_gateway_settings(): array {
        $gateway = new WC_Sicoob_Pix_Gateway();
        
        return array(
            'pix_key' => $gateway->get_option('pix_key'),
            'pix_description' => $gateway->get_option('pix_description')
        );
    }
}
