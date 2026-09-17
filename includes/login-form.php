<?php
// Expects $conn (from db.php) to already exist before this is included.
// Also expects session_start() to have already run — header.php handles
// that, but header.php is required AFTER this file, so we start it here
// too (guarded, so it won't double-start).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$loginSubmitted = false;
$loginError = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $loginSubmitted = true;

    $email = trim(strip_tags($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $loginError = "Please enter both your email and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, email, password FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Deliberately vague error either way — don't reveal whether the
        // email exists or the password was wrong. That distinction is
        // useful to an attacker, not to a real user.
        if (!$user || !password_verify($password, $user['password'])) {
            $loginError = "Invalid email or password.";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            header("Location: index.php");
            exit;
        }
    }
}