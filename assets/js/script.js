// ==========================================================================
// Site-wide jQuery: hero reveal, form validation, confirm prompts, flash
// messages. Page-specific code lives in booking.js, appointment.js and
// services.js (loaded only on those pages via $pageScripts).
// ==========================================================================
$(document).ready(function () {
  // Single page-load reveal for the hero content (title, subtitle, button).
  // Kept as one deliberate moment rather than animating every element.
  $(".hero-copy").addClass("is-visible");

  // ------------------------------------------------------------------------
  // Form validation for any <form class="js-validate"> (contact, signup,
  // login). Checks required fields, email format, minlength, and
  // data-match="#otherField" (for "confirm password").
  // PHP validates everything again on the server — this is just faster
  // feedback for the user.
  // ------------------------------------------------------------------------
  var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  $("form.js-validate").each(function () {
    var $form = $(this);

    $form.on("submit", function (e) {
      var isValid = true;

      $form.find("[required]").each(function () {
        var $field = $(this);
        var value = $.trim($field.val());
        var minLength = parseInt($field.attr("minlength"), 10) || 0;
        var fieldIsValid = value.length > 0 && value.length >= minLength;

        if (fieldIsValid && $field.attr("type") === "email") {
          fieldIsValid = emailPattern.test(value);
        }
        if (fieldIsValid && $field.data("match")) {
          fieldIsValid = value === $($field.data("match")).val();
        }

        $field.closest(".form-row").toggleClass("has-error", !fieldIsValid);
        if (!fieldIsValid) {
          isValid = false;
        }
      });

      // Only block the submit when something's actually wrong. If it's
      // valid, let the browser POST as normal — PHP handles saving it and
      // showing the success/error message.
      if (!isValid) {
        e.preventDefault();
        $form.find(".has-error :input").first().trigger("focus");
      }
    });

    // Clear a field's error state as soon as the person starts fixing it.
    $form.on("input", "input, textarea, select", function () {
      $(this).closest(".form-row").removeClass("has-error");
    });
  });

  // ------------------------------------------------------------------------
  // "Are you sure?" prompts. Put data-confirm="message" on a form (asks on
  // submit) or on a single submit button (asks only for that button).
  // ------------------------------------------------------------------------
  $(document).on("click", "button[data-confirm]", function (e) {
    if (!window.confirm($(this).data("confirm"))) {
      e.preventDefault();
    }
  });
  $(document).on("submit", "form[data-confirm]", function (e) {
    if (!window.confirm($(this).data("confirm"))) {
      e.preventDefault();
    }
  });

  // ------------------------------------------------------------------------
  // Flash messages: dismiss on click, success messages fade out on their own.
  // ------------------------------------------------------------------------
  $(document).on("click", ".flash-close", function () {
    $(this).closest(".flash").fadeOut(200);
  });
  setTimeout(function () {
    $(".flash-success").fadeOut(600);
  }, 6000);
});

// Admin photo fields: show a preview of the chosen file before saving.
$(document).on("change", ".photo-input", function () {
  var file = this.files && this.files[0];
  var $preview = $(this).siblings(".photo-preview");
  if (file && /^image\//.test(file.type)) {
    $preview.attr("src", URL.createObjectURL(file)).prop("hidden", false);
  } else {
    $preview.prop("hidden", true);
  }
});

// Shared helper: format a number as pesos, e.g. 16380 -> "₱16,380.00".
window.formatMoney = function (amount) {
  return (
    (window.RPN ? RPN.currency : "") +
    Number(amount).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  );
};
