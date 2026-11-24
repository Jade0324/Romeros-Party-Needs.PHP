// ==========================================================================
// Services page (services.php): "Check availability for a date" — shows how
// many of each item and service are still free on that date.
// ==========================================================================
$(function () {
  var $input = $("#checkDate");
  if (!$input.length) return;

  var $result = $("#dateCheckResult");

  $input.on("change", function () {
    var date = $input.val();
    if (!date) return;

    $result.removeClass("is-ok is-error").text("Checking…");
    $.getJSON(RPN.baseUrl + "api/availability.php", { date: date }, function (data) {
      if (data.ok) {
        $result.addClass("is-ok").html('Showing availability for ' + $("<b>").text(data.date_label).prop("outerHTML") + ' · <a href="book.php">Book this date &rarr;</a>');
      } else {
        $result.addClass("is-error").text(data.error);
      }

      $(".price-list li").each(function () {
        var info = data.items[$(this).data("item-id")];
        if (!info) return;
        $(this)
          .find(".price-list-stock")
          .toggleClass("is-low", info.available === 0)
          .text(info.available === 0 ? "Fully booked" : info.available + " of " + info.total + " free");
      });

      $(".offering[data-service-id]").each(function () {
        var info = data.services[$(this).data("service-id")];
        if (!info) return;
        $(this)
          .find(".service-slot")
          .toggleClass("is-low", info.remaining === 0)
          .text(info.remaining === 0 ? "Fully booked on this date" : "Available on this date");
      });
    });
  });
});
