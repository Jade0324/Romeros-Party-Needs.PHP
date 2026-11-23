<?php
// Expects $conn (from db.php) to already exist before this is included.

$signupSubmitted = false;
$signupSuccess = false;
$signupError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
    $signupSubmitted = true;

    $email = trim(strip_tags($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($email === '' || $password === '' || $confirmPassword === '') {
        $signupError = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signupError = "That email address doesn't look valid.";
    } elseif (strlen($password) < 6) {
        $signupError = "Password must be at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $signupError = "Passwords don't match.";
    } else {
        // Check whether that email is already registered before inserting.
        /** @var mysqli $conn */
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($checkStmt, "s", $email);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $signupError = "An account with that email already exists.";
        } else {
            // Never store the raw password — password_hash() one-way hashes it.
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            /** @var mysqli $conn */
            $insertStmt = mysqli_prepare($conn, "INSERT INTO users (email, password) VALUES (?, ?)");
            mysqli_stmt_bind_param($insertStmt, "ss", $email, $hashedPassword);

            if (mysqli_stmt_execute($insertStmt)) {
                $signupSuccess = true;
            } else {
                $signupError = "Something went wrong creating your account. Please try again.";
            }
            mysqli_stmt_close($insertStmt);
        }
        mysqli_stmt_close($checkStmt);
    }
}