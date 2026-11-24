<?php
// This file expects $conn (from db.php) to already exist before it's included.
/** @var mysqli $conn  Tells the code editor where $conn comes from. */

$formSubmitted = false;
$formSuccess = false;
$formError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    require_csrf();
    $formSubmitted = true;

    // Basic sanitization — trim whitespace, strip stray HTML tags.
    $name = post_str('name');
    $email = post_str('email');
    $phone = post_str('phone');
    $eventDateRaw = post_str('eventDate');
    $message = post_str('message');
    $eventDate = $eventDateRaw !== '' ? clean_date($eventDateRaw) : null;

    if ($name === '' || $email === '' || $message === '') {
        $formError = "Please fill in your name, email, and message.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = "That email address doesn't look valid.";
    } elseif ($eventDateRaw !== '' && !$eventDate) {
        $formError = "That event date doesn't look valid.";
    } else {
        // Link the inquiry to the account if they're logged in, so the
        // admin can see it next to that customer's bookings.
        $userId = is_logged_in() ? current_user_id() : null;

        // Prepared statement — keeps user input out of the raw SQL string,
        // which is what protects this against SQL injection.
        db_exec(
            $conn,
            "INSERT INTO inquiries (user_id, name, email, phone, event_date, inquiry) VALUES (?, ?, ?, ?, ?, ?)",
            "isssss",
            [$userId, $name, $email, $phone ?: null, $eventDate, $message]
        );
        $formSuccess = true;
        $_POST = []; // clear the form after a successful send
    }
}
