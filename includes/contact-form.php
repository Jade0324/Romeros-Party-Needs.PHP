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

    if ($name === '' || $email === '' || $message === '') {
        $formError = "Please fill in your name, email, and message.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $formError = "That email address doesn't look valid.";
    } else {
        // Fold phone/event date into the inquiry text since the table
        // only has a single "inquiry" column to work with.
        $inquiryText = $message;
        if ($eventDate !== '') {
            $inquiryText = "Event date: $eventDate\n" . $inquiryText;
        }
        if ($phone !== '') {
            $inquiryText = "Phone: $phone\n" . $inquiryText;
        }

        // Prepared statement — keeps user input out of the raw SQL string,
        // which is what protects this against SQL injection.
        $stmt = mysqli_prepare($conn, "INSERT INTO inquiries (name, email, inquiry) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $name, $email, $inquiryText);

        if (mysqli_stmt_execute($stmt)) {
            $formSuccess = true;
        } else {
            $formError = "Something went wrong saving your message. Please try again.";
        }
        mysqli_stmt_close($stmt);
    }
}