/**
 * Sicoob PIX Payment JavaScript
 *
 * @package SicoobPayment
 */

jQuery(document).ready(function ($) {
  "use strict";

  /**
   * Initialize PIX payment functionality
   */
  function initSicoobPix() {
    // Auto scroll to PIX block
    scrollToPixBlock();

    // Initialize copy functionality
    initCopyFunctionality();

    // Initialize QR code generation
    initQRCodeGeneration();

    // Initialize payment status checking
    initPaymentStatusChecking();
  }

  /**
   * Scroll to PIX payment block
   */
  function scrollToPixBlock() {
    var $pixBlock = $(".sicoob-pix-payment-block");

    if ($pixBlock.length) {
      $("html, body").animate(
        {
          scrollTop: $pixBlock.offset().top - 150,
        },
        800
      );
    }
  }

  /**
   * Initialize copy to clipboard functionality
   */
  function initCopyFunctionality() {
    $(document).on("click", ".sicoob-pix-copy-btn", function (e) {
      e.preventDefault();

      var $button = $(this);
      var $input = $button.siblings(".sicoob-pix-code-input");
      var $textarea = $button.siblings(".sicoob-pix-code-textarea");
      var pixCode = "";

      // Get code from visible input/textarea
      if ($input.is(":visible") && $input.val()) {
        pixCode = $input.val();
      } else if ($textarea.is(":visible") && $textarea.val()) {
        pixCode = $textarea.val();
      }

      if (!pixCode) {
        return;
      }

      // Try to copy to clipboard
      if (navigator.clipboard && window.isSecureContext) {
        // Modern clipboard API
        navigator.clipboard
          .writeText(pixCode)
          .then(function () {
            showCopySuccess($button);
          })
          .catch(function () {
            fallbackCopyTextToClipboard(pixCode, $button);
          });
      } else {
        // Fallback for older browsers
        fallbackCopyTextToClipboard(pixCode, $button);
      }
    });
  }

  /**
   * Fallback copy to clipboard for older browsers
   */
  function fallbackCopyTextToClipboard(text, $button) {
    var textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
      var successful = document.execCommand("copy");
      if (successful) {
        showCopySuccess($button);
      }
    } catch (err) {
      console.error("Erro ao copiar código PIX:", err);
    }

    document.body.removeChild(textArea);
  }

  /**
   * Show copy success feedback
   */
  function showCopySuccess($button) {
    var originalText = $button.text();

    $button.addClass("copied");
    $button.text("Copiado!");

    setTimeout(function () {
      $button.removeClass("copied");
      $button.text(originalText);
    }, 2000);
  }

  /**
   * Initialize QR code generation
   */
  function initQRCodeGeneration() {
    var $qrContainer = $(".sicoob-pix-qr-code");

    if ($qrContainer.length && $qrContainer.attr("data-qr-code")) {
      var qrCodeData = $qrContainer.attr("data-qr-code");
      generateQRCode(qrCodeData, $qrContainer);
    }
  }

  /**
   * Generate QR code using QRCode.js library
   */
  function generateQRCode(text, $container) {
    try {
      // Clear container
      $container.empty();

      // Check if QRCode library is available
      if (typeof QRCode === "undefined") {
        $container.html(
          '<p style="color: #dc3545; text-align: center;">Biblioteca QR Code não carregada</p>'
        );
        return;
      }

      // Create QR code using QRCode.js library
      var qr = new QRCode($container[0], {
        text: text,
        width: 200,
        height: 200,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M,
      });
    } catch (error) {
      console.error("Erro ao gerar QR Code:", error);
      $container.html(
        '<p style="color: #dc3545; text-align: center;">Erro ao gerar QR Code</p>'
      );
    }
  }

  /**
   * Check if device is mobile
   */
  function isMobile() {
    return window.innerWidth <= 768;
  }

  /**
   * Handle responsive behavior
   */
  function handleResponsive() {
    var $pixBlock = $(".sicoob-pix-payment-block");

    if ($pixBlock.length) {
      if (isMobile()) {
        $pixBlock.addClass("mobile-layout");
        // Show textarea, hide input on mobile
        $(".sicoob-pix-code-input").hide();
        $(".sicoob-pix-code-textarea").show();
      } else {
        $pixBlock.removeClass("mobile-layout");
        // Show input, hide textarea on desktop
        $(".sicoob-pix-code-input").show();
        $(".sicoob-pix-code-textarea").hide();
      }
    }
  }

  // Initialize on page load
  initSicoobPix();

  // Handle window resize
  $(window).on("resize", function () {
    handleResponsive();
  });

  // Handle responsive on load
  handleResponsive();

  /**
   * Initialize payment status checking
   */
  function initPaymentStatusChecking() {
    var $pixBlock = $(".sicoob-pix-payment-block");

    if ($pixBlock.length) {
      // Get order ID from URL or data attribute
      var orderId = getOrderIdFromUrl();

      if (orderId) {
        // Start checking payment status
        startPaymentStatusCheck(orderId);
      }
    }
  }

  /**
   * Get order ID from current URL
   */
  function getOrderIdFromUrl() {
    var urlParams = new URLSearchParams(window.location.search);
    var orderId = urlParams.get("order");

    if (!orderId) {
      // Try to get from data attribute
      var $pixBlock = $(".sicoob-pix-payment-block");
      if ($pixBlock.length) {
        orderId = $pixBlock.data("order-id");
      }
    }

    return orderId;
  }

  /**
   * Start checking payment status periodically
   */
  function startPaymentStatusCheck(orderId) {
    var checkInterval = 5000; // Check every 5 seconds
    var maxChecks = 120; // Maximum 10 minutes of checking
    var checkCount = 0;

    var statusCheckInterval = setInterval(function () {
      checkCount++;

      // Stop checking after max attempts
      if (checkCount > maxChecks) {
        clearInterval(statusCheckInterval);
        return;
      }

      checkPaymentStatus(orderId, function (isPaid) {
        if (isPaid) {
          clearInterval(statusCheckInterval);
          showSuccessBlock();
        }
      });
    }, checkInterval);
  }

  /**
   * Check payment status via AJAX
   */
  function checkPaymentStatus(orderId, callback) {
    $.ajax({
      url: sicoob_pix_params.ajax_url,
      type: "POST",
      data: {
        action: "sicoob_check_payment_status",
        order_id: orderId,
        nonce: sicoob_pix_params.nonce,
      },
      success: function (response) {
        if (response.success && response.data) {
          var isPaid = response.data.is_paid;
          callback(isPaid);
        }
      },
      error: function (xhr, status, error) {
        console.error("Erro ao verificar status do pagamento:", error);
      },
    });
  }

  /**
   * Show success block when payment is confirmed
   */
  function showSuccessBlock() {
    var $pixBlock = $(".sicoob-pix-payment-block");
    var $successBlock = $("#sicoob-pix-success-block");

    if ($pixBlock.length && $successBlock.length) {
      // Hide PIX payment block
      $pixBlock.fadeOut(500, function () {
        // Show success block
        $successBlock.fadeIn(500);

        // Scroll to success block
        $("html, body").animate(
          {
            scrollTop: $successBlock.offset().top - 100,
          },
          800
        );
      });
    }
  }
});
