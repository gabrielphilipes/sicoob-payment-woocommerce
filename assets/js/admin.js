/**
 * Sicoob Payment Admin JavaScript
 *
 * @package SicoobPayment
 */

jQuery(document).ready(function ($) {
  "use strict";

  // Atualizar status visual do checkbox em tempo real
  $("#enable_logs").on("change", function () {
    var $checkbox = $(this);
    var $status = $(".sicoob-checkbox-status");
    var isChecked = $checkbox.is(":checked");

    if (isChecked) {
      $status.removeClass("disabled").addClass("enabled");
      $status
        .find(".dashicons")
        .removeClass("dashicons-dismiss")
        .addClass("dashicons-yes-alt");
      $status.find("span:not(.dashicons)").text("Ativado");
    } else {
      $status.removeClass("enabled").addClass("disabled");
      $status
        .find(".dashicons")
        .removeClass("dashicons-yes-alt")
        .addClass("dashicons-dismiss");
      $status.find("span:not(.dashicons)").text("Desativado");
    }
  });

  // Feedback visual ao salvar formulário
  $(".sicoob-config-form").on("submit", function () {
    var $form = $(this);
    var $submitBtn = $form.find('button[type="submit"]');

    // Adicionar estado de loading
    $submitBtn.addClass("sicoob-loading");
    $submitBtn.prop("disabled", true);

    // Remover estado de loading após 3 segundos (fallback)
    setTimeout(function () {
      $submitBtn.removeClass("sicoob-loading");
      $submitBtn.prop("disabled", false);
    }, 3000);
  });

  // Auto-hide notices após 5 segundos
  $(".sicoob-notice").each(function () {
    var $notice = $(this);
    setTimeout(function () {
      $notice.fadeOut(500, function () {
        $notice.remove();
      });
    }, 5000);
  });

  // Tooltip para informações adicionais
  $(".sicoob-config-field-description").each(function () {
    var $description = $(this);
    var $field = $description.prev();

    if ($field.length) {
      $field
        .find("label")
        .append(
          ' <span class="dashicons dashicons-editor-help" title="' +
            $description.text() +
            '"></span>'
        );
    }
  });
});
