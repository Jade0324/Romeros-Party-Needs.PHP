// ==========================================================================
// Booking page (book.php)
//   1. Availability calendar   -> api/calendar.php
//   2. Live stock per date      -> api/availability.php
//   3. Quantity +/- controls and the running total in the summary panel
// The server re-checks everything when the form is submitted, so this is
// about giving instant feedback, not about security.
// ==========================================================================
$(function () {
  var $form = $("#bookingForm");
  if (!$form.length) return;

  var api = RPN.baseUrl + "api/";
  var $date = $("#event_date");
  var $cal = $("#bookingCalendar");
  var $status = $("#dateStatus");
  var calMonth = ($date.val() || new Date().toISOString().slice(0, 10)).slice(0, 7);
  var thisMonth = new Date().toISOString().slice(0, 7);
  var dateOk = false;
  var exclude = $form.data("exclude") || ""; // set when editing a booking

  // ---- 1. Calendar --------------------------------------------------------
  function shiftMonth(month, delta) {
    var parts = month.split("-");
    var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1 + delta, 1);
    return d.getFullYear() + "-" + ("0" + (d.getMonth() + 1)).slice(-2);
  }

  var stateLabels = {
    open: "Open",
    limited: "Almost fully booked",
    full: "Fully booked",
    blocked: "Closed",
    unavailable: "Not available",
  };

  function renderCalendar(month) {
    $.getJSON(api + "calendar.php", { month: month }, function (data) {
      calMonth = month;
      $cal.find(".cal-label").text(data.label);
      $cal.find('.cal-nav[data-dir="-1"]').prop("disabled", month <= thisMonth);

      var $grid = $cal.find(".cal-grid").empty();
      $.each(["S", "M", "T", "W", "T", "F", "S"], function (_, d) {
        $grid.append($('<span class="cal-wd">').text(d));
      });
      for (var i = 0; i < data.first_weekday; i++) {
        $grid.append('<span class="cal-day is-pad"></span>');
      }
      $.each(data.days, function (date, state) {
        var $day = $('<button type="button" class="cal-day">')
          .addClass("is-" + state)
          .text(parseInt(date.slice(8), 10))
          .attr({ "data-date": date, title: stateLabels[state] });
        var isOwnDate = exclude && date === $form.data("keep-date");
        if (!isOwnDate && (state === "unavailable" || state === "full" || state === "blocked")) {
          $day.prop("disabled", true);
        }
        if (date === $date.val()) $day.addClass("is-selected");
        $grid.append($day);
      });
    });
  }

  $cal.on("click", ".cal-nav", function () {
    renderCalendar(shiftMonth(calMonth, parseInt($(this).data("dir"), 10)));
  });

  $cal.on("click", ".cal-day:not(.is-pad)", function () {
    $date.val($(this).data("date")).trigger("change");
  });

  // ---- 2. Availability for the chosen date --------------------------------
  function loadAvailability() {
    var date = $date.val();
    $cal.find(".cal-day").removeClass("is-selected").filter('[data-date="' + date + '"]').addClass("is-selected");
    if (!date) return;
    if (date.slice(0, 7) !== calMonth) renderCalendar(date.slice(0, 7));

    $status.removeClass("is-ok is-error").text("Checking availability…");

    $.getJSON(api + "availability.php", { date: date, exclude: exclude })
      .done(function (data) {
        dateOk = data.ok;
        if (data.ok) {
          var left = data.max_bookings - data.bookings_on_date;
          $status.addClass("is-ok").text("✓ " + data.date_label + " is open (" + left + " of " + data.max_bookings + " crew slots left)");
        } else {
          $status.addClass("is-error").text(data.error);
        }
        $("#summaryDate").text(data.date_label);
        $("#itemsNote").text("");

        // Rental items: cap each quantity at what's free on this date.
        $(".item-row").each(function () {
          var $row = $(this);
          var id = $row.data("item-id");
          var info = data.items[id] || { available: 0 };
          var $input = $row.find(".qty-input");
          $input.attr("max", info.available);
          $row.toggleClass("is-out", info.available === 0);
          $row.find(".avail-badge").text(info.available === 0 ? "Fully booked" : info.available + " available");

          if (parseInt($input.val(), 10) > info.available) {
            $input.val(info.available);
            $row.addClass("was-reduced");
            setTimeout(function () { $row.removeClass("was-reduced"); }, 2500);
          }
        });

        // Services: disable any whose team is fully booked that day.
        $(".service-option").each(function () {
          var $opt = $(this);
          var info = data.services[$opt.data("service-id")] || { remaining: 0, capacity: 0 };
          var $box = $opt.find("input");
          var full = info.remaining < 1;
          $opt.toggleClass("is-full", full);
          $box.prop("disabled", full);
          if (full) $box.prop("checked", false);
          $opt.find(".service-slot").text(full ? "Fully booked on this date" : info.remaining + " of " + info.capacity + " available");
        });

        updateSummary();
      })
      .fail(function () {
        $status.addClass("is-error").text("Couldn't check availability. Is the server running?");
      });
  }

  $date.on("change", loadAvailability);

  // ---- 3. Quantities and the running total --------------------------------
  function clamp($input) {
    var max = parseInt($input.attr("max"), 10);
    var v = parseInt($input.val(), 10) || 0;
    if (v < 0) v = 0;
    if (!isNaN(max) && v > max) v = max;
    $input.val(v);
  }

  $form.on("click", ".qty-btn", function () {
    var $input = $(this).siblings(".qty-input");
    $input.val((parseInt($input.val(), 10) || 0) + parseInt($(this).data("step"), 10));
    clamp($input);
    updateSummary();
  });

  $form.on("change input", ".qty-input", function (e) {
    if (e.type === "change") clamp($(this));
    updateSummary();
  });
  $form.on("change", ".service-option input", updateSummary);

  function updateSummary() {
    var $lines = $("#summaryLines").empty();
    var itemsTotal = 0;
    var servicesTotal = 0;

    $(".qty-input").each(function () {
      var qty = parseInt($(this).val(), 10) || 0;
      if (qty > 0) {
        var subtotal = qty * parseFloat($(this).data("price"));
        itemsTotal += subtotal;
        $lines.append(
          $("<li>").append(
            $("<span>").text($(this).data("name") + " × " + qty),
            $("<span>").text(formatMoney(subtotal))
          )
        );
      }
    });
    $(".service-option input:checked").each(function () {
      var price = parseFloat($(this).data("price"));
      servicesTotal += price;
      $lines.append($("<li>").append($("<span>").text($(this).data("name")), $("<span>").text(formatMoney(price))));
    });

    if (!$lines.children().length) {
      $lines.append('<li class="summary-empty">Nothing added yet.</li>');
    }

    var delivery = itemsTotal > 0 ? RPN.deliveryFee : 0;
    var total = itemsTotal + servicesTotal + delivery;
    $("#summaryDelivery").text(formatMoney(delivery));
    $("#summaryTotal").text(formatMoney(total));
    $("#summaryDown").text(formatMoney(total * RPN.downPaymentRate));
  }

  // Friendly hint: how many chairs/tables the guest count usually needs.
  $("#guest_count").on("input", function () {
    var guests = parseInt($(this).val(), 10) || 0;
    $("#guestHint").text(
      guests > 0 ? "Tip: " + guests + " guests usually need " + guests + " chairs and " + Math.ceil(guests / 10) + " round tables." : ""
    );
  }).trigger("input");

  // ---- Client-side checks before submitting -------------------------------
  $form.on("submit", function (e) {
    var problems = [];
    if (!$date.val()) problems.push("Choose an event date.");
    else if (!dateOk) problems.push($status.text() || "That date can't be booked.");
    if (!$("#event_type").val()) problems.push("Choose the type of event.");
    if (!(parseInt($("#guest_count").val(), 10) > 0)) problems.push("Enter the number of guests.");
    if (!$.trim($("#venue_address").val())) problems.push("Enter the venue address.");

    var setup = $("#setup_time").val(), start = $("#event_start").val(), end = $("#event_end").val();
    if (!setup || !start || !end) problems.push("Fill in the setup, start and end times.");
    else if (!(setup < start && start < end)) problems.push("Setup must be before the event starts, and the event must end after it starts.");

    var hasItems = $(".qty-input").filter(function () { return parseInt($(this).val(), 10) > 0; }).length > 0;
    if (!hasItems && !$(".service-option input:checked").length) problems.push("Add at least one rental item or service.");

    $form.find(".form-errors.js-errors").remove();
    if (problems.length) {
      e.preventDefault();
      var $box = $('<div class="form-errors js-errors" role="alert"><strong>Please fix the following:</strong><ul></ul></div>');
      $.each(problems, function (_, p) { $box.find("ul").append($("<li>").text(p)); });
      $form.prepend($box);
      $("html, body").animate({ scrollTop: $box.offset().top - 90 }, 300);
    } else {
      $("#submitBooking").prop("disabled", true).text("Submitting…");
    }
  });

  // ---- Start up --------------------------------------------------------
  renderCalendar(calMonth);
  if ($date.val()) loadAvailability();
  updateSummary();
});
