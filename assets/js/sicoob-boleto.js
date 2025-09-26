/**
 * Sicoob Boleto Payment Block JavaScript
 *
 * @package SicoobPayment
 */

jQuery(document).ready(function ($) {
  ("use strict");

  const $boletoBlock = $("#sicoob-boleto-payment-block");
  if ($boletoBlock.length === 0) {
    return;
  }

  /**
   * Scroll to boleto block when page loads
   */
  $("html, body").animate(
    {
      scrollTop: $boletoBlock.offset().top - 150,
    },
    800
  );

  /**
   * Initialize event handlers
   */
  $(document).on("click", ".sicoob-boleto-download-btn", function (e) {
    handleDownloadClick(e, $(this));
  });

  $(document).on("click", ".sicoob-boleto-print-btn", function (e) {
    handlePrintClick(e, $(this));
  });

  /**
   * Handle download button click
   */
  function handleDownloadClick(e, button) {
    e.preventDefault();

    const originalText = button.html();
    const url = button.attr("href");

    // Show loading state
    button.html('<span class="sicoob-boleto-btn-icon">⏳</span> Baixando...');
    button.prop("disabled", true);

    // Create temporary link for download
    const tempLink = $("<a>", {
      href: url,
      download: "",
      target: "_blank",
    });

    // Trigger download
    tempLink[0].click();

    // Reset button after delay
    setTimeout(function () {
      button.html(originalText);
      button.prop("disabled", false);
    }, 2000);
  }

  /**
   * Handle print button click
   */
  function handlePrintClick(e, button) {
    e.preventDefault();

    const originalText = button.html();

    // Show loading state
    button.html('<span class="sicoob-boleto-btn-icon">⏳</span> Preparando...');
    button.prop("disabled", true);

    // Get iframe source URL
    const iframe = document.getElementById("sicoob-boleto-iframe");
    const iframeSrc = iframe ? iframe.src : null;

    if (iframeSrc) {
      // Open PDF directly in new window for printing
      const printWindow = window.open(
        iframeSrc,
        "_blank",
        "width=800,height=600"
      );

      if (printWindow) {
        // Wait for window to load
        printWindow.onload = function () {
          // Small delay to ensure PDF is fully loaded
          setTimeout(function () {
            printWindow.focus();
            printWindow.print();

            // Show success message
            showNotification(
              sicoob_boleto_params.strings.print_success,
              "success"
            );

            // Reset button
            button.html(originalText);
            button.prop("disabled", false);

            // Don't close automatically - let user close manually
            // printWindow.close();
          }, 2000);
        };

        // Handle case where onload doesn't fire (some browsers)
        setTimeout(function () {
          if (!button.prop("disabled")) return; // Already handled

          printWindow.focus();
          printWindow.print();

          // Show success message
          showNotification(
            sicoob_boleto_params.strings.print_success,
            "success"
          );

          // Reset button
          button.html(originalText);
          button.prop("disabled", false);
        }, 3000);
      } else {
        // Fallback to regular print if popup is blocked
        printIframeFallback(button, originalText);
      }
    } else {
      // Fallback to regular print
      printIframeFallback(button, originalText);
    }
  }

  /**
   * Fallback print method
   */
  function printIframeFallback(button, originalText) {
    // Small delay to show loading state
    setTimeout(function () {
      // Open print dialog
      window.print();

      // Show success message
      showNotification(sicoob_boleto_params.strings.print_success, "success");

      // Reset button
      button.html(originalText);
      button.prop("disabled", false);
    }, 500);
  }
});
