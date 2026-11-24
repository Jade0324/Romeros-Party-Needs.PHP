// ==========================================================================
// Appointment page (appointment.php): load open time slots for the chosen
// date from api/slots.php and render them as radio buttons.
// ==========================================================================
$(function () {
  var $form = $("#appointmentForm");
  if (!$form.length) return;

  var $date = $("#appointment_date");
  var $grid = $("#slotGrid");
  var $status = $("#slotStatus");
  var $booking = $("#booking_id");

  // If the appointment is for a booking, it has to happen before the event.
  function syncMaxDate() {
    var eventDate = $booking.find(":selected").data("event-date");
    if (eventDate) {
      $date.attr("max", eventDate);
      $status.removeClass("is-error").addClass("is-ok").text("Must be on or before the event (" + eventDate + ").");
    } else {
      $date.removeAttr("max");
    }
  }

  function loadSlots() {
    var date = $date.val();
    var previouslyChosen = $grid.find("input:checked").val();
    if (!date) return;

    $status.removeClass("is-ok is-error").text("Loading times…");
    $.getJSON(RPN.baseUrl + "api/slots.php", { date: date }, function (data) {
      $grid.empty();
      if (!data.ok) {
        $status.addClass("is-error").text(data.error);
        $grid.append('<p class="muted">Pick another date.</p>');
        return;
      }
      var free = 0;
      $.each(data.slots, function (_, slot) {
        var $label = $('<label class="slot">').toggleClass("is-taken", !slot.free);
        var $radio = $('<input type="radio" name="appointment_time">').val(slot.time).prop("disabled", !slot.free);
        if (slot.free && slot.time === previouslyChosen) $radio.prop("checked", true);
        $label.append($radio, $("<span>").text(slot.free ? slot.label : slot.label + " · taken"));
        $grid.append($label);
        if (slot.free) free++;
      });
      $status
        .addClass(free ? "is-ok" : "is-error")
        .text(free ? free + " time slot(s) open" : "All slots are taken on this date — try another day.");
    });
  }

  $date.on("change", loadSlots);
  $booking.on("change", syncMaxDate);
  syncMaxDate();

  $form.on("submit", function (e) {
    if (!$date.val() || !$grid.find("input:checked").length) {
      e.preventDefault();
      $status.removeClass("is-ok").addClass("is-error").text("Please choose a date and an open time slot.");
    }
  });
});
