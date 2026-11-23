$(document).ready(function () {
  // Single page-load reveal for the hero content (title, subtitle, button).
  // Kept as one deliberate moment rather than animating every element.
  $(".hero-copy").addClass("is-visible");

// Contact form validation — only runs on contact.php
  var $form = $("#contactForm");
  if ($form.length) {
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    $form.on("submit", function (e) {
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

      // Only block the submit when something's actually wrong. If it's
      // valid, let the browser POST to contact.php as normal — PHP
      // handles saving it and showing the success/error message.
      if (!isValid) {
        e.preventDefault();
      }
    });

    // Clear a field's error state as soon as the person starts fixing it.
    $form.on("input", "input, textarea", function () {
      $(this).closest(".form-row").removeClass("has-error");
    });
  }

  // Signup form validation — only runs on signup.php.
  var $signupForm = $("#signupForm");
  if ($signupForm.length) {
    $signupForm.on("submit", function (e) {
      var isValid = true;
      var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      var $password = $("#password");
      var $confirm = $("#confirm_password");

      $signupForm.find("[required]").each(function () {
        var $field = $(this);
        var $row = $field.closest(".form-row");
        var value = $.trim($field.val());
        var fieldIsValid = value.length >= (parseInt($field.attr("minlength"), 10) || 0) && value.length > 0;

        if (fieldIsValid && $field.attr("type") === "email") {
          fieldIsValid = emailPattern.test(value);
        }

        $row.toggleClass("has-error", !fieldIsValid);
        if (!fieldIsValid) {
          isValid = false;
        }
      });

      if ($password.val() !== $confirm.val()) {
        $confirm.closest(".form-row").addClass("has-error");
        isValid = false;
      }

      if (!isValid) {
        e.preventDefault();
      }
    });

    $signupForm.on("input", "input", function () {
      $(this).closest(".form-row").removeClass("has-error");
    });
  }

  // Login form validation — only runs on login.php.
  var $loginForm = $("#loginForm");
  if ($loginForm.length) {
    $loginForm.on("submit", function (e) {
      var isValid = true;

      $loginForm.find("[required]").each(function () {
        var $field = $(this);
        var $row = $field.closest(".form-row");
        var fieldIsValid = $.trim($field.val()).length > 0;

        $row.toggleClass("has-error", !fieldIsValid);
        if (!fieldIsValid) {
          isValid = false;
        }
      });

      if (!isValid) {
        e.preventDefault();
      }
    });

    $loginForm.on("input", "input", function () {
      $(this).closest(".form-row").removeClass("has-error");
    });
  }
});