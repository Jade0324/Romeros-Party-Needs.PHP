$(document).ready(function () {
  // Single page-load reveal for the hero content (title, subtitle, button).
  // Kept as one deliberate moment rather than animating every element.
  $(".hero-content").addClass("is-visible");

  // Contact form validation — only runs on contact.html, since that's the
  // only page with #contactForm on it.
  var $form = $("#contactForm");
  if ($form.length) {
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    $form.on("submit", function (e) {
      e.preventDefault();

      var isValid = true;

      $form.find("[required]").each(function () {
        var $field = $(this);
        var $row = $field.closest(".form-row");
        var value = $.trim($field.val());
        var fieldIsValid = value.length > 0;

        if (fieldIsValid && $field.attr("type") === "email") {
          fieldIsValid = emailPattern.test(value);
        }

        $row.toggleClass("has-error", !fieldIsValid);
        if (!fieldIsValid) {
          isValid = false;
        }
      });

      if (isValid) {
        $("#formSuccess").addClass("is-visible");
        $form[0].reset();
      } else {
        $("#formSuccess").removeClass("is-visible");
      }
    });

    // Clear a field's error state as soon as the person starts fixing it.
    $form.on("input", "input, textarea", function () {
      $(this).closest(".form-row").removeClass("has-error");
    });
  }
});