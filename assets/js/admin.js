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

  // Teste de Geração PIX
  $("#test-pix-generation").on("click", function () {
    testPixGeneration();
  });

  // Teste de Geração Boleto
  $("#test-boleto-generation").on("click", function () {
    testBoletoGeneration();
  });

  // Validação e contador para descrição do PIX
  function initPixDescriptionValidation() {
    var $pixDescriptionField = $(
      'input[name="woocommerce_sicoob_pix_pix_description"]'
    );

    if ($pixDescriptionField.length === 0) {
      return;
    }

    // Criar contador de caracteres
    var $counter = $(
      '<div class="sicoob-char-counter" style="font-size: 12px; color: #666; margin-top: 5px;"></div>'
    );
    $pixDescriptionField.after($counter);

    // Função para atualizar contador
    function updateCounter() {
      var currentLength = $pixDescriptionField.val().length;
      var maxLength = 40;
      var remaining = maxLength - currentLength;

      if (remaining < 0) {
        $counter.html(
          '<span style="color: #d63638;">' +
            currentLength +
            "/" +
            maxLength +
            " caracteres (limite excedido)</span>"
        );
        $pixDescriptionField.addClass("sicoob-field-error");
      } else if (remaining <= 5) {
        $counter.html(
          '<span style="color: #dba617;">' +
            currentLength +
            "/" +
            maxLength +
            " caracteres restantes</span>"
        );
        $pixDescriptionField.removeClass("sicoob-field-error");
      } else {
        $counter.html(
          '<span style="color: #00a32a;">' +
            currentLength +
            "/" +
            maxLength +
            " caracteres</span>"
        );
        $pixDescriptionField.removeClass("sicoob-field-error");
      }
    }

    // Atualizar contador ao digitar
    $pixDescriptionField.on("input keyup paste", function () {
      var value = $(this).val();

      // Limitar a 40 caracteres
      if (value.length > 40) {
        $(this).val(value.substring(0, 40));
      }

      updateCounter();
    });

    // Validação no envio do formulário
    $('form[name="mainform"]').on("submit", function (e) {
      var pixDescriptionValue = $pixDescriptionField.val();

      if (pixDescriptionValue.length > 40) {
        e.preventDefault();
        alert("A descrição do PIX não pode ter mais de 40 caracteres.");
        $pixDescriptionField.focus();
        return false;
      }
    });

    // Inicializar contador
    updateCounter();
  }

  // Validação e contador para instruções do boleto
  function initBoletoInstructionsValidation() {
    // Função para atualizar contador (definida fora do loop)
    function updateCounter($field, $counter) {
      var currentLength = $field.val().length;
      var maxLength = 40;
      var remaining = maxLength - currentLength;

      if (remaining < 0) {
        $counter.html(
          '<span style="color: #d63638;">' +
            currentLength +
            "/" +
            maxLength +
            " caracteres (limite excedido)</span>"
        );
        $field.addClass("sicoob-field-error");
      } else if (remaining <= 5) {
        $counter.html(
          '<span style="color: #dba617;">' +
            currentLength +
            "/" +
            maxLength +
            " caracteres restantes</span>"
        );
        $field.removeClass("sicoob-field-error");
      } else {
        $counter.html(
          '<span style="color: #00a32a;">' +
            currentLength +
            "/" +
            maxLength +
            " caracteres</span>"
        );
        $field.removeClass("sicoob-field-error");
      }
    }

    // Aplicar validação para cada campo de instrução
    for (var i = 1; i <= 5; i++) {
      var $instructionField = $(
        'input[name="woocommerce_sicoob_boleto_instruction_' + i + '"]'
      );

      if ($instructionField.length === 0) {
        continue;
      }

      // Verificar se já tem contador (evitar duplicação)
      if ($instructionField.siblings(".sicoob-char-counter").length > 0) {
        continue;
      }

      // Criar contador de caracteres
      var $counter = $(
        '<div class="sicoob-char-counter" style="font-size: 12px; color: #666; margin-top: 5px;"></div>'
      );
      $instructionField.after($counter);

      // Atualizar contador ao digitar
      $instructionField.on("input keyup paste", function () {
        var value = $(this).val();

        // Limitar a 40 caracteres
        if (value.length > 40) {
          $(this).val(value.substring(0, 40));
        }

        // Encontrar o contador associado a este campo
        var $associatedCounter = $(this).siblings(".sicoob-char-counter");
        updateCounter($(this), $associatedCounter);
      });

      // Inicializar contador
      updateCounter($instructionField, $counter);
    }

    // Validação no envio do formulário
    $('form[name="mainform"]').on("submit", function (e) {
      var hasErrors = false;
      var firstErrorField = null;

      for (var i = 1; i <= 5; i++) {
        var $instructionField = $(
          'input[name="woocommerce_sicoob_boleto_instruction_' + i + '"]'
        );

        if ($instructionField.length > 0) {
          var value = $instructionField.val();

          if (value.length > 40) {
            hasErrors = true;
            if (!firstErrorField) {
              firstErrorField = $instructionField;
            }
          }
        }
      }

      if (hasErrors) {
        e.preventDefault();
        alert(
          "As instruções do boleto não podem ter mais de 40 caracteres cada."
        );
        if (firstErrorField) {
          firstErrorField.focus();
        }
        return false;
      }
    });
  }

  // Inicializar validação quando a página carregar
  initPixDescriptionValidation();
  initBoletoInstructionsValidation();

  // Reinicializar quando campos forem carregados dinamicamente
  $(document).on("DOMNodeInserted", function (e) {
    if (
      $(e.target).find('input[name="woocommerce_sicoob_pix_pix_description"]')
        .length > 0
    ) {
      setTimeout(initPixDescriptionValidation, 100);
    }

    if (
      $(e.target).find('input[name*="woocommerce_sicoob_boleto_instruction_"]')
        .length > 0
    ) {
      setTimeout(initBoletoInstructionsValidation, 100);
    }
  });

  // Função global para inserir sugestões de instruções do boleto
  window.sicoobInsertSuggestions = function () {
    var suggestions = [
      "Não receber após o vencimento",
      "Após vencimento, pagar apenas em nosso estabelecimento",
      "Não aceitar após vencimento",
      "Após vencimento, pagar apenas em nossa loja",
      "Não receber após a data de vencimento",
    ];

    // Preencher os campos de instrução com as sugestões
    for (var i = 1; i <= 5; i++) {
      var fieldName = "woocommerce_sicoob_boleto_instruction_" + i;
      var $field = $('input[name="' + fieldName + '"]');

      if ($field.length > 0 && suggestions[i - 1]) {
        $field.val(suggestions[i - 1]);

        // Disparar evento de input para atualizar contadores se existirem
        $field.trigger("input");
      }
    }

    // Mostrar feedback visual
    var $button = $('input[onclick="sicoobInsertSuggestions()"]');
    var originalText = $button.val();

    $button.val("✓ Sugestões inseridas!");
    $button.addClass("button-primary");

    setTimeout(function () {
      $button.val(originalText);
      $button.removeClass("button-primary");
    }, 2000);
  };

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

  // Função para teste de geração de PIX
  function testPixGeneration() {
    var $btn = $("#test-pix-generation");
    var $results = $("#api-test-results");
    var $content = $("#api-response-content");

    // Desabilitar botão e mostrar loading
    $btn.addClass("sicoob-loading").prop("disabled", true);
    $btn.html(
      '<span class="dashicons dashicons-update"></span> Testando Geração PIX...'
    );

    // Fazer requisição AJAX
    $.ajax({
      url: sicoob_payment_params.ajax_url,
      type: "POST",
      data: {
        action: "sicoob_test_pix_generation",
        nonce: sicoob_payment_params.test_pix_nonce,
      },
      success: function (response) {
        var resultText = "";

        if (response.success) {
          resultText += "=== TESTE DE GERAÇÃO PIX - SUCESSO ===\n\n";

          resultText += "=== DADOS DE TESTE GERADOS ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.test_data, null, 2) +
            "\n\n";

          resultText += "=== CONFIGURAÇÕES PIX ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.pix_settings, null, 2) +
            "\n\n";

          resultText += "=== ENDPOINT ===\n";
          resultText += response.data.request_info.endpoint + "\n\n";

          resultText += "=== RESULTADO DA GERAÇÃO ===\n";
          resultText += JSON.stringify(response.data.result, null, 2) + "\n\n";

          if (response.data.result.success && response.data.result.data) {
            resultText += "=== DADOS DO PIX GERADO ===\n";
            resultText +=
              "TXID: " + (response.data.result.data.txid || "N/A") + "\n";
            resultText +=
              "Status: " + (response.data.result.data.status || "N/A") + "\n";
            resultText +=
              "Revisão: " + (response.data.result.data.revisao || "N/A") + "\n";
            resultText +=
              "Location: " +
              (response.data.result.data.location || "N/A") +
              "\n";
            resultText +=
              "BR Code: " + (response.data.result.data.brcode || "N/A") + "\n";
            resultText +=
              "Criação: " +
              (response.data.result.data.calendario?.criacao || "N/A") +
              "\n";
            resultText +=
              "Expiração: " +
              (response.data.result.data.calendario?.expiracao || "N/A") +
              " segundos\n";
            resultText +=
              "Valor: R$ " +
              (response.data.result.data.valor?.original || "N/A") +
              "\n";
            resultText +=
              "Chave PIX: " + (response.data.result.data.chave || "N/A") + "\n";
            resultText +=
              "Solicitação: " +
              (response.data.result.data.solicitacaoPagador || "N/A") +
              "\n\n";
          }

          resultText += "=== RESPOSTA COMPLETA ===\n";
          resultText += JSON.stringify(response, null, 2);
        } else {
          resultText += "=== TESTE DE GERAÇÃO PIX - ERRO ===\n\n";
          resultText +=
            "Erro: " + (response.data.message || "Erro desconhecido") + "\n\n";
          resultText += "=== DADOS DE TESTE ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.test_data, null, 2) +
            "\n\n";
          resultText += "=== CONFIGURAÇÕES PIX ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.pix_settings, null, 2) +
            "\n\n";
          resultText += "=== RESPOSTA COMPLETA ===\n";
          resultText += JSON.stringify(response, null, 2);
        }

        $content.text(resultText);
        $results.show();
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
        $btn.html(
          '<span class="dashicons dashicons-money-alt"></span> Testar Geração PIX'
        );
      },
    });
  }

  /**
   * Test Boleto generation function
   *
   * Executes a test boleto generation using the Sicoob API
   * with realistic test data to validate configuration and connectivity.
   */
  function testBoletoGeneration() {
    var $btn = $("#test-boleto-generation");
    var $results = $("#api-test-results");
    var $content = $("#api-response-content");

    // Validate required parameters
    if (!sicoob_payment_params || !sicoob_payment_params.test_boleto_nonce) {
      alert("Parâmetros de teste não configurados corretamente.");
      return;
    }

    // Set loading state
    $btn.addClass("sicoob-loading").prop("disabled", true);
    $btn.html(
      '<span class="dashicons dashicons-update"></span> Testando Geração Boleto...'
    );

    // Execute AJAX request with timeout
    $.ajax({
      url: sicoob_payment_params.ajax_url,
      type: "POST",
      timeout: 30000, // 30 seconds timeout
      data: {
        action: "sicoob_test_boleto_generation",
        nonce: sicoob_payment_params.test_boleto_nonce,
      },
      success: function (response) {
        var resultText = "";

        if (response.success) {
          resultText += "=== ✅ TESTE DE GERAÇÃO BOLETO - SUCESSO ===\n\n";

          // Test summary
          if (response.data.test_summary) {
            resultText += "=== 📋 RESUMO DO TESTE ===\n";
            resultText +=
              "Tipo: " +
              (response.data.test_summary.test_type || "boleto_generation") +
              "\n";
            resultText += "Status: SUCESSO\n";
            resultText +=
              "Timestamp: " +
              (response.data.request_info.timestamp || "N/A") +
              "\n\n";
          }

          // Test data used (sanitized)
          resultText += "=== 🧪 DADOS DE TESTE UTILIZADOS ===\n";
          if (
            response.data.test_summary &&
            response.data.test_summary.test_data_used
          ) {
            var testData = response.data.test_summary.test_data_used;
            resultText +=
              "ID do Pedido: " + (testData.order_id || "N/A") + "\n";
            resultText += "CPF: " + (testData.cpf || "N/A") + "\n";
            resultText += "Nome: " + (testData.nome || "N/A") + "\n";
            resultText += "Valor: " + (testData.valor || "N/A") + "\n\n";
          } else {
            resultText +=
              JSON.stringify(response.data.request_info.test_data, null, 2) +
              "\n\n";
          }

          // Boleto settings (sanitized)
          resultText += "=== ⚙️ CONFIGURAÇÕES DO BOLETO ===\n";
          resultText +=
            JSON.stringify(
              response.data.request_info.boleto_settings,
              null,
              2
            ) + "\n\n";

          // API endpoint
          resultText += "=== 🌐 ENDPOINT UTILIZADO ===\n";
          resultText += response.data.request_info.endpoint + "\n\n";

          resultText += "=== 📄 RESULTADO DA GERAÇÃO ===\n";
          resultText += JSON.stringify(response.data.result, null, 2) + "\n\n";

          if (response.data.result.success && response.data.result.data) {
            resultText += "=== 🎫 DADOS DO BOLETO GERADO ===\n";
            var boletoData = response.data.result.data;

            resultText +=
              "Nosso Número: " + (boletoData.nosso_numero || "N/A") + "\n";
            resultText +=
              "Seu Número: " + (boletoData.seu_numero || "N/A") + "\n";
            resultText +=
              "Código de Barras: " + (boletoData.codigo_barras || "N/A") + "\n";
            resultText +=
              "Linha Digitável: " +
              (boletoData.linha_digitavel || "N/A") +
              "\n";
            resultText += "Valor: R$ " + (boletoData.valor || "N/A") + "\n";
            resultText +=
              "Data Vencimento: " +
              (boletoData.data_vencimento || "N/A") +
              "\n";
            resultText +=
              "Data Emissão: " + (boletoData.data_emissao || "N/A") + "\n";
            resultText +=
              "QR Code: " +
              (boletoData.qr_code ? "✅ Gerado" : "❌ N/A") +
              "\n";

            // PDF information
            if (boletoData.pdf_saved && boletoData.pdf_saved.success) {
              resultText += "PDF: ✅ Gerado com sucesso\n";
              resultText +=
                "URL: " + (boletoData.pdf_saved.file_url || "N/A") + "\n";
              resultText +=
                "Tamanho: " +
                (boletoData.pdf_saved.file_size || "N/A") +
                " bytes\n";
            } else {
              resultText += "PDF: ❌ Não gerado\n";
            }

            resultText += "\n=== 👤 DADOS DO PAGADOR ===\n";
            resultText += JSON.stringify(boletoData.pagador, null, 2) + "\n\n";

            resultText += "=== 📝 MENSAGENS DE INSTRUÇÃO ===\n";
            resultText +=
              JSON.stringify(boletoData.mensagens_instrucao, null, 2) + "\n\n";
          }

          resultText += "=== RESPOSTA COMPLETA ===\n";
          resultText += JSON.stringify(response, null, 2);
        } else {
          resultText += "=== ❌ TESTE DE GERAÇÃO BOLETO - ERRO ===\n\n";
          resultText +=
            "Erro: " + (response.data.message || "Erro desconhecido") + "\n\n";

          if (
            response.data.missing_fields &&
            response.data.missing_fields.length > 0
          ) {
            resultText += "=== ⚠️ CAMPOS OBRIGATÓRIOS FALTANDO ===\n";
            response.data.missing_fields.forEach(function (field) {
              resultText += "- " + field + "\n";
            });
            resultText += "\n";
          }

          resultText += "=== 🧪 DADOS DE TESTE ===\n";
          resultText +=
            JSON.stringify(response.data.request_info.test_data, null, 2) +
            "\n\n";

          resultText += "=== ⚙️ CONFIGURAÇÕES BOLETO ===\n";
          resultText +=
            JSON.stringify(
              response.data.request_info.boleto_settings,
              null,
              2
            ) + "\n\n";

          resultText += "=== 📄 RESPOSTA COMPLETA ===\n";
          resultText += JSON.stringify(response, null, 2);
        }

        $content.text(resultText);
        $results.show();
      },
      error: function (xhr, status, error) {
        var errorText = "=== 🚫 ERRO DE COMUNICAÇÃO ===\n\n";
        errorText += "Status: " + status + "\n";
        errorText += "Erro: " + error + "\n";
        errorText += "Código HTTP: " + (xhr.status || "N/A") + "\n";
        errorText += "Resposta: " + (xhr.responseText || "N/A") + "\n\n";
        errorText += "Verifique sua conexão com a internet e tente novamente.";
        $content.text(errorText);
        $results.show();
      },
      complete: function () {
        // Reset button state
        $btn.removeClass("sicoob-loading").prop("disabled", false);
        $btn.html(
          '<span class="dashicons dashicons-media-document"></span> Testar Geração Boleto'
        );
      },
    });
  }

  // Test Boleto Email
  $("#test-boleto-email").on("click", function () {
    const $btn = $(this);
    const $emailInput = $("#test-email-address");
    const $results = $("#email-test-results");
    const $content = $("#email-response-content");

    // Validate email
    const testEmail = $emailInput.val().trim();
    if (!testEmail || !isValidEmail(testEmail)) {
      alert("Por favor, digite um e-mail válido para o teste.");
      $emailInput.focus();
      return;
    }

    // Set loading state
    $btn.addClass("sicoob-loading").prop("disabled", true);
    $btn.html('<span class="dashicons dashicons-update"></span> Enviando...');

    // Hide previous results
    $results.hide();

    // Make AJAX request
    $.ajax({
      url: sicoob_payment_params.ajax_url,
      type: "POST",
      data: {
        action: "sicoob_test_boleto_email",
        nonce: sicoob_payment_params.test_boleto_email_nonce,
        test_email: testEmail,
      },
      success: function (response) {
        if (response.success) {
          const data = response.data;
          let successText = "✅ E-mail de teste enviado com sucesso!\n\n";
          successText += `📧 E-mail enviado para: ${data.test_summary.email_sent_to}\n`;
          successText += `📋 Pedido de teste: #${data.test_summary.order_id}\n`;
          successText += `📄 Nosso Número: ${data.test_summary.boleto_data.nosso_numero}\n`;
          successText += `💰 Valor: R$ ${data.test_summary.boleto_data.valor}\n`;
          successText += `📅 Vencimento: ${data.test_summary.boleto_data.data_vencimento}\n\n`;
          successText +=
            "📨 Verifique sua caixa de entrada (e spam) para ver o e-mail de teste.";

          $content.text(successText);
          $results.show();
        } else {
          let errorText = "❌ Erro ao enviar e-mail de teste:\n\n";
          errorText += `🔍 Detalhes: ${response.data.message}\n\n`;
          if (response.data.error_details) {
            errorText += `📋 Erro técnico: ${response.data.error_details}\n\n`;
          }
          errorText += "💡 Verifique:\n";
          errorText += "- Se o e-mail está correto\n";
          errorText +=
            "- Se o sistema de e-mails do WordPress está configurado\n";
          errorText += "- Se a classe de e-mail está registrada\n";
          errorText += "- Os logs do WooCommerce para mais detalhes";

          $content.text(errorText);
          $results.show();
        }
      },
      error: function (xhr, status, error) {
        let errorText = "❌ Erro de conexão ao testar e-mail:\n\n";
        errorText += `🔍 Status: ${status}\n`;
        errorText += `📋 Erro: ${error}\n\n`;
        errorText +=
          "💡 Verifique sua conexão com a internet e tente novamente.";
        $content.text(errorText);
        $results.show();
      },
      complete: function () {
        // Reset button state
        $btn.removeClass("sicoob-loading").prop("disabled", false);
        $btn.html(
          '<span class="dashicons dashicons-email-alt"></span> Enviar E-mail de Teste'
        );
      },
    });
  });

  // Email validation helper
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }
});
