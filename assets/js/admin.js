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

  // Botão para alterar dados sensíveis
  $(".sicoob-change-data-btn").on("click", function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $input = $("#client_id");

    // Remover readonly e limpar campo
    $input.removeAttr("readonly").val("").focus();

    // Atualizar placeholder
    $input.attr("placeholder", "Digite o novo ID do Cliente");

    // Esconder botão
    $btn.hide();
  });

  // Mostrar botão quando campo estiver vazio
  $("#client_id").on("blur", function () {
    var $input = $(this);
    var $btn = $(".sicoob-change-data-btn");

    if ($input.val().length === 0 && $input.attr("readonly")) {
      $btn.show();
    }
  });

  // Validação de arquivo de certificado em tempo real
  $("#certificate_file").on("change", function () {
    var $input = $(this);
    var $validation = $("#certificate-validation");
    var $message = $validation.find(".sicoob-validation-message");
    var file = this.files[0];

    // Limpar validação anterior
    $validation.hide().removeClass("valid invalid");

    if (!file) {
      return;
    }

    // Validar extensão
    var allowedExtensions = ["pem", "crt", "key"];
    var fileExtension = file.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(fileExtension)) {
      $validation.addClass("invalid").show();
      $message.html(
        '<span class="dashicons dashicons-warning"></span> Tipo de arquivo não permitido. Use apenas arquivos .PEM, .CRT ou .KEY.'
      );
      return;
    }

    // Validar tamanho (1MB)
    if (file.size > 1024 * 1024) {
      $validation.addClass("invalid").show();
      $message.html(
        '<span class="dashicons dashicons-warning"></span> Arquivo muito grande. Tamanho máximo: 1MB.'
      );
      return;
    }

    // Validar conteúdo do certificado
    var reader = new FileReader();
    reader.onload = function (e) {
      var content = e.target.result;
      var isValid = validateCertificateContent(content);

      if (isValid) {
        $validation.addClass("valid").show();
        $message.html(
          '<span class="dashicons dashicons-yes-alt"></span> Certificado válido detectado.'
        );
      } else {
        $validation.addClass("invalid").show();
        $message.html(
          '<span class="dashicons dashicons-warning"></span> Arquivo de certificado inválido. Verifique se é um certificado válido do Sicoob.'
        );
      }
    };

    reader.readAsText(file);
  });

  // Função para validar conteúdo do certificado
  function validateCertificateContent(content) {
    var certificateHeaders = [
      "-----BEGIN CERTIFICATE-----",
      "-----BEGIN PRIVATE KEY-----",
      "-----BEGIN RSA PRIVATE KEY-----",
      "-----BEGIN ENCRYPTED PRIVATE KEY-----",
    ];

    for (var i = 0; i < certificateHeaders.length; i++) {
      if (content.indexOf(certificateHeaders[i]) !== -1) {
        return true;
      }
    }

    return false;
  }

  // Feedback visual durante upload
  $(".sicoob-config-form").on("submit", function (e) {
    var $form = $(this);
    var $fileInput = $("#certificate_file");
    var $submitBtn = $form.find('button[type="submit"]');

    // Se há arquivo selecionado, mostrar feedback
    if ($fileInput[0].files.length > 0) {
      $submitBtn.html(
        '<span class="dashicons dashicons-upload"></span> Enviando certificado...'
      );
    }
  });

  // Remoção de certificado
  $(".sicoob-remove-certificate-btn").on("click", function (e) {
    e.preventDefault();

    var $btn = $(this);
    var certificatePath = $btn.data("certificate-path");

    // Confirmação antes de remover
    if (
      !confirm(
        "Tem certeza que deseja remover o certificado? Esta ação não pode ser desfeita."
      )
    ) {
      return;
    }

    // Adicionar estado de loading
    $btn.addClass("sicoob-loading").prop("disabled", true);
    $btn.html('<span class="dashicons dashicons-update"></span> Removendo...');

    // Fazer requisição AJAX
    $.ajax({
      url: ajaxurl,
      type: "POST",
      data: {
        action: "sicoob_remove_certificate",
        nonce: sicoob_payment_params.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Recarregar a página para mostrar o estado atualizado
          window.location.reload();
        } else {
          // Mostrar erro
          alert("Erro ao remover certificado: " + response.data.message);
          $btn.removeClass("sicoob-loading").prop("disabled", false);
          $btn.html(
            '<span class="dashicons dashicons-trash"></span> Remover Certificado'
          );
        }
      },
      error: function () {
        alert("Erro na comunicação com o servidor.");
        $btn.removeClass("sicoob-loading").prop("disabled", false);
        $btn.html(
          '<span class="dashicons dashicons-trash"></span> Remover Certificado'
        );
      },
    });
  });

  // Teste da API - PIX Token
  $("#test-pix-token").on("click", function () {
    testApiToken("pix");
  });

  // Teste da API - Boleto Token
  $("#test-boleto-token").on("click", function () {
    testApiToken("boleto");
  });

  function testApiToken(scopeType) {
    var $btn = $("#test-" + scopeType + "-token");
    var $results = $("#api-test-results");
    var $content = $("#api-response-content");

    // Desabilitar botão e mostrar loading
    $btn.addClass("sicoob-loading").prop("disabled", true);
    $btn.html('<span class="dashicons dashicons-update"></span> Testando...');

    // Fazer requisição AJAX
    $.ajax({
      url: sicoob_payment_params.ajax_url,
      type: "POST",
      data: {
        action: "sicoob_test_api",
        nonce: sicoob_payment_params.test_api_nonce,
        scope_type: scopeType,
      },
      success: function (response) {
        if (response.success) {
          // Exibir resultado completo e bruto
          var resultText = "=== TESTE DA API SICOOB ===\n";
          resultText +=
            "Tipo: " + response.data.scope_type.toUpperCase() + "\n";
          resultText += "Scope utilizado: " + response.data.scope_used + "\n";
          resultText += "Timestamp: " + response.data.timestamp + "\n";
          resultText +=
            "Status da requisição: " +
            (response.data.result.success ? "SUCESSO" : "ERRO") +
            "\n";
          resultText += "Mensagem: " + response.data.result.message + "\n";

          resultText += "\n=== INFORMAÇÕES DA REQUISIÇÃO ===\n";
          resultText +=
            "Endpoint: " + response.data.request_info.endpoint + "\n";
          resultText += "Método: " + response.data.request_info.method + "\n";
          resultText +=
            "Client ID configurado: " +
            (response.data.auth_config.client_id_configured ? "SIM" : "NÃO") +
            "\n";
          resultText +=
            "Certificado existe: " +
            (response.data.auth_config.certificate_exists ? "SIM" : "NÃO") +
            "\n";
          resultText +=
            "Caminho do certificado: " +
            response.data.auth_config.ssl_certificate +
            "\n";

          resultText += "\n=== HEADERS DA REQUISIÇÃO ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.headers, null, 2) + "\n";

          resultText += "\n=== BODY DA REQUISIÇÃO ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.body, null, 2) + "\n";

          resultText += "\n=== OPÇÕES cURL ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.curl_options, null, 2) +
            "\n";

          resultText += "\n=== RESPOSTA COMPLETA DA API ===\n";
          resultText += JSON.stringify(response.data.result, null, 2);
          resultText += "\n\n=== RESPOSTA BRUTA COMPLETA ===\n";
          resultText += JSON.stringify(response, null, 2);

          $content.text(resultText);
          $results.show();
        } else {
          $content.text("Erro: " + (response.data || "Erro desconhecido"));
          $results.show();
        }
      },
      error: function (xhr, status, error) {
        var errorText = "Erro na comunicação com o servidor:\n";
        errorText += "Status: " + status + "\n";
        errorText += "Error: " + error + "\n";
        errorText += "Response: " + xhr.responseText;
        $content.text(errorText);
        $results.show();
      },
      complete: function () {
        // Reabilitar botão
        $btn.removeClass("sicoob-loading").prop("disabled", false);
        if (scopeType === "pix") {
          $btn.html(
            '<span class="dashicons dashicons-admin-network"></span> Testar Token PIX'
          );
        } else {
          $btn.html(
            '<span class="dashicons dashicons-admin-network"></span> Testar Token Boleto'
          );
        }
      },
    });
  }
});
