<?php
/**
 * Sicoob Boleto Email Template (Plain Text)
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "= " . $email_heading . " =\n\n";

echo $email->get_custom_header_text() . "\n\n";

echo "========================================\n";
echo "DADOS DO BOLETO BANCÁRIO\n";
echo "========================================\n\n";

echo "INFORMAÇÕES DO PEDIDO:\n";
echo "Número do Pedido: #" . $order->get_order_number() . "\n";
echo "Valor Total: " . $order->get_formatted_order_total() . "\n";
echo "Data do Pedido: " . wc_format_datetime($order->get_date_created()) . "\n\n";

echo "DADOS DO BOLETO:\n";
if (isset($boleto_data['nosso_numero']) && !empty($boleto_data['nosso_numero'])) {
    echo "Nosso Número: " . $boleto_data['nosso_numero'] . "\n";
}

if (isset($boleto_data['linha_digitavel']) && !empty($boleto_data['linha_digitavel'])) {
    echo "Linha Digitável: " . $boleto_data['linha_digitavel'] . "\n";
}

if (isset($boleto_data['valor']) && !empty($boleto_data['valor'])) {
    echo "Valor do Boleto: " . wc_price($boleto_data['valor']) . "\n";
}

if (isset($boleto_data['data_vencimento']) && !empty($boleto_data['data_vencimento'])) {
    echo "Data de Vencimento: " . date('d/m/Y', strtotime($boleto_data['data_vencimento'])) . "\n";
}

if (isset($boleto_data['data_emissao']) && !empty($boleto_data['data_emissao'])) {
    echo "Data de Emissão: " . date('d/m/Y', strtotime($boleto_data['data_emissao'])) . "\n";
}

echo "\n========================================\n";
echo "COMO PAGAR\n";
echo "========================================\n\n";

echo "1. Acesse o internet banking do seu banco\n";
echo "2. Procure pela opção 'Pagamento de Boletos' ou 'Cobrança'\n";
echo "3. Digite a linha digitável ou escaneie o código de barras\n";
echo "4. Confirme os dados e efetue o pagamento\n";
echo "5. Guarde o comprovante de pagamento\n\n";

echo "========================================\n";
echo "AVISO IMPORTANTE\n";
echo "========================================\n\n";

if (isset($boleto_data['data_vencimento']) && !empty($boleto_data['data_vencimento'])) {
    echo "Este boleto vence em " . date('d/m/Y', strtotime($boleto_data['data_vencimento'])) . ".\n";
    echo "Após esta data, o boleto poderá sofrer acréscimos de juros e multa.\n";
    echo "Pague até o vencimento para evitar cobranças adicionais.\n\n";
}

if (isset($boleto_data['pdf_url']) && !empty($boleto_data['pdf_url'])) {
    echo "========================================\n";
    echo "DOWNLOAD DO BOLETO\n";
    echo "========================================\n\n";
    echo "Para baixar o boleto em PDF, acesse:\n";
    echo $boleto_data['pdf_url'] . "\n\n";
}

echo "========================================\n";
echo "PRECISA DE AJUDA?\n";
echo "========================================\n\n";
echo "Se você tiver dúvidas sobre este boleto ou precisar de ajuda com o pagamento, entre em contato conosco através dos nossos canais de atendimento.\n\n";

echo "\n" . apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
?>
