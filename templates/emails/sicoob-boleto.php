<?php
/**
 * Sicoob Boleto Email Template
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php echo esc_html($email->get_custom_header_text()); ?></p>

<div class="sicoob-boleto-email-content">
    <h2 style="color: #2271b1; font-size: 24px; margin-bottom: 20px;"><?php _e('Dados do Boleto Bancário', 'sicoob-payment'); ?></h2>

    <!-- Informações do Pedido -->
    <div class="order-info" style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin-bottom: 30px;">
        <h3 style="color: #2271b1; font-size: 18px; margin-bottom: 15px;"><?php _e('Informações do Pedido', 'sicoob-payment'); ?></h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #50575e;"><?php _e('Número do Pedido:', 'sicoob-payment'); ?></td>
                <td style="padding: 8px 0; color: #2271b1;">#<?php echo esc_html($order->get_order_number()); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #50575e;"><?php _e('Valor Total:', 'sicoob-payment'); ?></td>
                <td style="padding: 8px 0; color: #2271b1; font-weight: bold;"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #50575e;"><?php _e('Data do Pedido:', 'sicoob-payment'); ?></td>
                <td style="padding: 8px 0; color: #50575e;"><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
            </tr>
        </table>
    </div>

    <!-- Dados do Boleto -->
    <div class="boleto-info" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 30px;">
        <h3 style="color: #2271b1; font-size: 18px; margin-bottom: 15px;"><?php _e('Dados do Boleto', 'sicoob-payment'); ?></h3>
        
        <?php if (isset($boleto_data['nosso_numero']) && !empty($boleto_data['nosso_numero'])): ?>
        <div style="margin-bottom: 15px;">
            <strong style="color: #50575e;"><?php _e('Nosso Número:', 'sicoob-payment'); ?></strong>
            <span style="color: #2271b1; font-family: monospace; font-size: 16px;"><?php echo esc_html($boleto_data['nosso_numero']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($boleto_data['linha_digitavel']) && !empty($boleto_data['linha_digitavel'])): ?>
        <div style="margin-bottom: 15px;">
            <strong style="color: #50575e;"><?php _e('Linha Digitável:', 'sicoob-payment'); ?></strong>
            <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; margin-top: 5px;">
                <span style="color: #2271b1; font-family: monospace; font-size: 16px; letter-spacing: 1px;"><?php echo esc_html($boleto_data['linha_digitavel']); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($boleto_data['valor']) && !empty($boleto_data['valor'])): ?>
        <div style="margin-bottom: 15px;">
            <strong style="color: #50575e;"><?php _e('Valor do Boleto:', 'sicoob-payment'); ?></strong>
            <span style="color: #2271b1; font-size: 18px; font-weight: bold;"><?php echo wc_price($boleto_data['valor']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($boleto_data['data_vencimento']) && !empty($boleto_data['data_vencimento'])): ?>
        <div style="margin-bottom: 15px;">
            <strong style="color: #50575e;"><?php _e('Data de Vencimento:', 'sicoob-payment'); ?></strong>
            <span style="color: #d63638; font-weight: bold;"><?php echo esc_html(date('d/m/Y', strtotime($boleto_data['data_vencimento']))); ?></span>
        </div>
        <?php endif; ?>

        <?php if (isset($boleto_data['data_emissao']) && !empty($boleto_data['data_emissao'])): ?>
        <div style="margin-bottom: 15px;">
            <strong style="color: #50575e;"><?php _e('Data de Emissão:', 'sicoob-payment'); ?></strong>
            <span style="color: #50575e;"><?php echo esc_html(date('d/m/Y', strtotime($boleto_data['data_emissao']))); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Instruções de Pagamento -->
    <div class="payment-instructions" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 4px; margin-bottom: 30px;">
        <h3 style="color: #856404; font-size: 18px; margin-bottom: 15px;"><?php _e('Como Pagar', 'sicoob-payment'); ?></h3>
        <ul style="color: #856404; margin: 0; padding-left: 20px;">
            <li style="margin-bottom: 8px;"><?php _e('Acesse o internet banking do seu banco', 'sicoob-payment'); ?></li>
            <li style="margin-bottom: 8px;"><?php _e('Procure pela opção "Pagamento de Boletos" ou "Cobrança"', 'sicoob-payment'); ?></li>
            <li style="margin-bottom: 8px;"><?php _e('Digite a linha digitável ou escaneie o código de barras', 'sicoob-payment'); ?></li>
            <li style="margin-bottom: 8px;"><?php _e('Confirme os dados e efetue o pagamento', 'sicoob-payment'); ?></li>
            <li><?php _e('Guarde o comprovante de pagamento', 'sicoob-payment'); ?></li>
        </ul>
    </div>

    <!-- Aviso Importante -->
    <div class="important-notice" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 4px; margin-bottom: 30px;">
        <h3 style="color: #721c24; font-size: 18px; margin-bottom: 15px;"><?php _e('⚠️ Aviso Importante', 'sicoob-payment'); ?></h3>
        <p style="color: #721c24; margin: 0;">
            <?php _e('Este boleto vence em', 'sicoob-payment'); ?> 
            <strong><?php echo esc_html(date('d/m/Y', strtotime($boleto_data['data_vencimento']))); ?></strong>. 
            <?php _e('Após esta data, o boleto poderá sofrer acréscimos de juros e multa. Pague até o vencimento para evitar cobranças adicionais.', 'sicoob-payment'); ?>
        </p>
    </div>

    <!-- Botão de Download do PDF -->
    <?php if (isset($boleto_data['pdf_url']) && !empty($boleto_data['pdf_url'])): ?>
    <div class="download-section" style="text-align: center; margin: 30px 0;">
        <a href="<?php echo esc_url($boleto_data['pdf_url']); ?>" 
           style="background: #2271b1; color: #fff; padding: 15px 30px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">
            <?php _e('📄 Baixar Boleto em PDF', 'sicoob-payment'); ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Informações de Contato -->
    <div class="contact-info" style="background: #f8f9fa; padding: 20px; border-radius: 4px; margin-top: 30px;">
        <h3 style="color: #2271b1; font-size: 16px; margin-bottom: 10px;"><?php _e('Precisa de Ajuda?', 'sicoob-payment'); ?></h3>
        <p style="color: #50575e; margin: 0;">
            <?php _e('Se você tiver dúvidas sobre este boleto ou precisar de ajuda com o pagamento, entre em contato conosco através dos nossos canais de atendimento.', 'sicoob-payment'); ?>
        </p>
    </div>
</div>

<?php
do_action('woocommerce_email_footer', $email);
?>
