<?php
// This file expects $conn (from db.php) to already exist before it's included.

$formSubmitted = false;
$formSuccess = false;
$formError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $formSubmitted = true;

    // Basic sanitization — trim whitespace, strip stray HTML tags.
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $email = trim(strip_tags($_POST['email'] ?? ''));
    $phone = trim(strip_tags($_POST['phone'] ?? ''));
    $eventDate = trim(strip_tags($_POST['eventDate'] ?? ''));
    $message = trim(strip_tags($_POST['message'] ?? ''));

    if ($name === '' || $email === '' || $eventDate === '' || $message === '') {
        $formError = "Please fill in your name, email, event date, and message.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = "That email address doesn't look valid.";
    } else {
        $dateParts = explode('-', $eventDate);

        $validDateFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate);
        $validCalendarDate = false;

        if ($validDateFormat && count($dateParts) === 3) {
            $validCalendarDate = checkdate(
                (int) $dateParts[1],
                (int) $dateParts[2],
                (int) $dateParts[0]
            );
        }

        if (!$validCalendarDate) {
            $formError = "That event date doesn't look valid.";
        } else {
            // Prepared statement — keeps user input out of the raw SQL string,
            // which is what protects this against SQL injection.
            /** @var mysqli $conn */
            $stmt = mysqli_prepare($conn, "INSERT INTO inquiries (name, email, phone, event_date, inquiry) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $phone, $eventDate, $message);

            if (mysqli_stmt_execute($stmt)) {
                $formSuccess = true;
            } else {
                $formError = "Something went wrong saving your message. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}