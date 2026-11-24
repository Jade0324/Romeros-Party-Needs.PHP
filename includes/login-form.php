<?php
// Expects $conn (from db.php) to already exist before this is included.
/** @var mysqli $conn  Tells the code editor where $conn comes from. */

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$loginSubmitted = false;
$loginError = "";

/** Has this email or IP had too many wrong passwords recently? */
function login_is_locked(mysqli $conn, string $email, string $ip): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCK_MINUTES * 60);
    $count = (int) db_value(
        $conn,
        "SELECT COUNT(*) FROM login_attempts WHERE (email = ? OR ip_address = ?) AND attempted_at >= ?",
        "sss",
        [$email, $ip, $since]
    );
    return $count >= LOGIN_MAX_ATTEMPTS;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    require_csrf();
    $loginSubmitted = true;

    $email = strtolower(post_str('email'));
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if ($email === '' || $password === '') {
        $loginError = "Please enter both your email and password.";
    } elseif (login_is_locked($conn, $email, $ip)) {
        // Slows down anyone trying to guess passwords.
        $loginError = "Too many failed attempts. Please wait " . LOGIN_LOCK_MINUTES . " minutes and try again.";
    } else {
        $user = db_one($conn, "SELECT id, full_name, email, password, role, is_active FROM users WHERE email = ?", "s", [$email]);

        // Deliberately vague error either way — don't reveal whether the
        // email exists or the password was wrong. That distinction is
        // useful to an attacker, not to a real user.
        if (!$user || !password_verify($password, $user['password'])) {
            db_exec($conn, "INSERT INTO login_attempts (email, ip_address, attempted_at) VALUES (?, ?, ?)", "sss", [$email, $ip, date('Y-m-d H:i:s')]);
            // Housekeeping: old attempts don't matter any more.
            db_exec($conn, "DELETE FROM login_attempts WHERE attempted_at < ?", "s", [date('Y-m-d H:i:s', time() - 86400)]);
            $loginError = "Invalid email or password.";
        } elseif (!(int) $user['is_active']) {
            $loginError = "This account has been deactivated. Please contact us for help.";
        } else {
            // Successful login: clear the failed attempts for this email.
            db_exec($conn, "DELETE FROM login_attempts WHERE email = ?", "s", [$email]);
            db_exec($conn, "UPDATE users SET last_login_at = ? WHERE id = ?", "si", [date('Y-m-d H:i:s'), $user['id']]);

            login_user($user);
            flash('success', 'Welcome back, ' . $user['full_name'] . '!');

            // Send them back to the page that asked them to log in.
            $next = safe_next($_POST['next'] ?? '');
            if ($next !== '') {
                redirect($next);
            }
            redirect($user['role'] === 'admin' ? 'admin/index.php' : 'account.php');
        }
    }
}
