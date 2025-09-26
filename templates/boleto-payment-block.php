<?php
/**
 * Boleto Payment Block Template
 *
 * @package SicoobPayment
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get boleto data from order
$boleto_nosso_numero = $order->get_meta('_sicoob_boleto_nosso_numero');
$boleto_seu_numero = $order->get_meta('_sicoob_boleto_seu_numero');
$boleto_linha_digitavel = $order->get_meta('_sicoob_boleto_linha_digitavel');
$boleto_valor = $order->get_meta('_sicoob_boleto_valor');
$boleto_data_vencimento = $order->get_meta('_sicoob_boleto_data_vencimento');
$boleto_data_emissao = $order->get_meta('_sicoob_boleto_data_emissao');
$boleto_pdf_url = $order->get_meta('_sicoob_boleto_pdf_url');

// Check if boleto data exists
if (empty($boleto_pdf_url)) {
    return;
}

// Check order status - only show boleto if order is pending payment
$order_status = $order->get_status();
$is_paid = $order->is_paid();
// $is_paid = true;

// Calculate days until expiration
$due_date = $boleto_data_vencimento ? strtotime($boleto_data_vencimento) : time() + (3 * 24 * 60 * 60); // Default 3 days
$days_until_due = max(0, ceil(($due_date - time()) / (24 * 60 * 60)));

// Format values
$formatted_value = wc_price($boleto_valor);
$formatted_due_date = $boleto_data_vencimento ? date('d/m/Y', strtotime($boleto_data_vencimento)) : '';
?>

<div class="sicoob-boleto-payment-block" id="sicoob-boleto-payment-block" style="<?= $is_paid ? 'display: none;' : 'display: block;' ?>" data-order-id="<?php echo esc_attr($order->get_id()); ?>">
    <div class="sicoob-boleto-header">
        <h3><?php _e('Pague o seu pedido', 'sicoob-payment'); ?></h3>
        <p><?php _e('Visualize e imprima seu boleto bancário para realizar o pagamento', 'sicoob-payment'); ?></p>
    </div>

    <div class="sicoob-boleto-content">
        <div class="sicoob-boleto-pdf-container">
            <div class="sicoob-boleto-pdf-header">
                <h4><?php _e('Boleto Bancário', 'sicoob-payment'); ?></h4>
                <div class="sicoob-boleto-info">
                    <span class="sicoob-boleto-value"><?php echo $formatted_value; ?></span>
                    <?php if ($formatted_due_date): ?>
                        <span class="sicoob-boleto-due-date"><?php printf(__('Vencimento em %s', 'sicoob-payment'), $formatted_due_date); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="sicoob-boleto-pdf-iframe">
                <iframe src="<?php echo esc_url($boleto_pdf_url); ?>" 
                        width="100%" 
                        height="600" 
                        frameborder="0"
                        title="<?php _e('Boleto Bancário', 'sicoob-payment'); ?>"
                        id="sicoob-boleto-iframe">
                </iframe>
            </div>
        </div>

        <div class="sicoob-boleto-actions">
            <a href="<?php echo esc_url($boleto_pdf_url); ?>" 
               class="sicoob-boleto-download-btn" 
               target="_blank" 
               download>
                <span class="sicoob-boleto-btn-icon">📄</span>
                <?php _e('Baixar Boleto', 'sicoob-payment'); ?>
            </a>
            
            <button type="button" class="sicoob-boleto-print-btn" id="sicoob-boleto-print-btn">
                <span class="sicoob-boleto-btn-icon">🖨️</span>
                <?php _e('Imprimir Boleto', 'sicoob-payment'); ?>
            </button>
        </div>

        <div class="sicoob-boleto-info-message">
            <div class="sicoob-boleto-warning">
                <span class="sicoob-boleto-warning-icon">⚠️</span>
                <div class="sicoob-boleto-warning-content">
                    <p class="sicoob-boleto-warning-text">
                        <?php _e('Você também recebeu este boleto por e-mail.', 'sicoob-payment'); ?>
                    </p>
                    <p class="sicoob-boleto-warning-text">
                        <?php 
                        if ($days_until_due > 0) {
                            printf(__('O boleto vence em %d dias e deve ser pago até o vencimento.', 'sicoob-payment'), $days_until_due);
                        } else {
                            _e('O boleto vence hoje e deve ser pago até o vencimento.', 'sicoob-payment');
                        }
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bloco de Sucesso (inicialmente oculto) -->
<div class="sicoob-boleto-success-block" id="sicoob-boleto-success-block" style="<?= $is_paid ? 'display: block;' : 'display: none;' ?>">
    <div class="sicoob-boleto-success-header">
        <h3><?php _e('Pagamento Recebido!', 'sicoob-payment'); ?></h3>
    </div>

    <div class="sicoob-boleto-success-content">
        <div class="sicoob-boleto-success-message">
            <div class="sicoob-boleto-success-info">
                <p class="sicoob-boleto-success-text">
                    <?php _e('Obrigado! Seu pagamento via boleto foi processado com sucesso.', 'sicoob-payment'); ?>
                </p>
                <p class="sicoob-boleto-success-details">
                    <?php _e('Em breve, você receberá mais informações sobre seu pedido por e-mail.', 'sicoob-payment'); ?>
                </p>
            </div>
        </div>

        <div class="sicoob-boleto-success-actions">
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="sicoob-boleto-success-btn">
                <?php _e('Ver mais produtos', 'sicoob-payment'); ?>
            </a>
        </div>
    </div>
</div>
