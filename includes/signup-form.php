<?php
// Expects $conn (from db.php) to already exist before this is included.
/** @var mysqli $conn  Tells the code editor where $conn comes from. */

$signupSubmitted = false;
$signupSuccess = false;
$signupError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup_submit'])) {
    require_csrf();
    $signupSubmitted = true;

    $fullName = post_str('full_name');
    $email = strtolower(post_str('email'));
    $phone = post_str('phone');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($fullName === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
        $signupError = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signupError = "That email address doesn't look valid.";
    } elseif (!preg_match('/^[0-9+()\s-]{7,20}$/', $phone)) {
        $signupError = "Please enter a valid phone number.";
    } elseif (strlen($password) < 6) {
        $signupError = "Password must be at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $signupError = "Passwords don't match.";
    } elseif (db_value($conn, "SELECT COUNT(*) FROM users WHERE email = ?", "s", [$email]) > 0) {
        // Check whether that email is already registered before inserting.
        $signupError = "An account with that email already exists.";
    } else {
        // Never store the raw password — password_hash() one-way hashes it.
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $newId = db_exec(
            $conn,
            "INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)",
            "ssss",
            [$fullName, $email, $phone, $hashedPassword]
        );

        // Log them straight in — one less step before they can book.
        login_user(['id' => $newId, 'email' => $email, 'full_name' => $fullName, 'role' => 'customer']);
        flash('success', "Welcome, $fullName! Your account is ready.");
        $next = safe_next($_POST['next'] ?? '');
        redirect($next !== '' ? $next : 'account.php');
    }
}
