<?php
// ==========================================================================
// Small helpers used on every page: escaping, links, sessions, CSRF,
// flash messages, login checks, formatting.
// ==========================================================================

if (session_status() === PHP_SESSION_NONE) {
    // Session cookie can't be read by JavaScript (httponly), isn't sent by
    // other sites' forms (samesite), and is HTTPS-only when the site is.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Log people out after a long period of inactivity.
if (isset($_SESSION['user_id'], $_SESSION['last_seen']) && time() - $_SESSION['last_seen'] > SESSION_IDLE_MINUTES * 60) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['flash'][] = ['type' => 'info', 'message' => 'You were logged out after being inactive. Please log in again.'];
}
$_SESSION['last_seen'] = time();

/**
 * Re-read the logged-in user from the database on every request, so that if
 * an admin deactivates an account or changes its role, it takes effect
 * immediately instead of at the next login.
 */
function refresh_session_user(mysqli $conn): void
{
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    $user = db_one($conn, "SELECT id, full_name, email, role, is_active FROM users WHERE id = ?", "i", [(int) $_SESSION['user_id']]);
    if (!$user || !(int) $user['is_active']) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('error', 'Your account is no longer active. Please contact us if you think this is a mistake.');
        return;
    }
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

/** Friendly full-page error (404 / 403) in the site's own layout. */
function render_error_page(int $code, string $title, string $message): void
{
    global $conn;
    http_response_code($code);
    $pageTitle = "$title | " . BUSINESS_NAME;
    require __DIR__ . '/header.php';
    echo '<section class="container error-page">'
        . '<p class="error-code">' . $code . '</p>'
        . '<h1 class="error-title">' . e($title) . '</h1>'
        . '<p class="error-message">' . e($message) . '</p>'
        . '<p class="error-actions"><a class="btn-primary" href="' . e(url('index.php')) . '">Back to home</a>'
        . (is_logged_in() ? ' <a class="btn-ghost" href="' . e(url('account.php')) . '">My bookings</a>' : '')
        . '</p></section>';
    require __DIR__ . '/footer.php';
    exit;
}

/** Escape text for safe output inside HTML. Use it on anything a user typed. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Build a link from the project root, e.g. url('admin/index.php'). */
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Re-fill a form field with what the user typed before a validation error. */
function old(string $key, $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

function post_str(string $key): string
{
    return trim(strip_tags((string) ($_POST[$key] ?? '')));
}

// --------------------------------------------------------------------------
// CSRF protection — every POST form includes a hidden token that must match
// the one in the session. Stops other sites from submitting forms as you.
// --------------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '" />';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf_token']) && hash_equals(csrf_token(), (string) $_POST['csrf_token']);
}

/** Call at the top of any POST handler. Stops the request if the token is wrong. */
function require_csrf(): void
{
    if (!csrf_valid()) {
        http_response_code(400);
        die('Your session expired or the form was invalid. Please go back, refresh the page, and try again.');
    }
}

// --------------------------------------------------------------------------
// Flash messages — set before a redirect, shown once on the next page.
// --------------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// --------------------------------------------------------------------------
// Login state
// --------------------------------------------------------------------------
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function is_admin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function login_user(array $user): void
{
    // New session id on login prevents session fixation attacks.
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user_role'] = $user['role'];
}

/** Send guests to the login page, then bring them back here afterwards. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('info', 'Please log in first.');
        $here = substr($_SERVER['REQUEST_URI'] ?? '', strlen(BASE_URL));
        redirect('login.php?next=' . urlencode($here));
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        render_error_page(403, 'Admins only', 'This page is for the Romero\'s Party Needs team. If you\'re a customer, your bookings are under My bookings.');
    }
}

/** Only allow redirects to pages inside this site (no "//evil.com"). */
function safe_next(string $next): string
{
    if ($next === '' || preg_match('#^(/|\\\\|[a-z][a-z0-9+.-]*:)#i', $next)) {
        return '';
    }
    return $next;
}

// --------------------------------------------------------------------------
// Formatting
// --------------------------------------------------------------------------
function peso(mixed $amount): string
{
    return CURRENCY . number_format((float) $amount, 2);
}

function fmt_date(?string $date, string $format = 'D, M j, Y'): string
{
    return $date ? date($format, strtotime($date)) : '—';
}

function fmt_time(?string $time): string
{
    return $time ? date('g:i A', strtotime($time)) : '—';
}

function status_badge(string $status): string
{
    return '<span class="status-badge status-' . e($status) . '">' . e(ucfirst(str_replace('_', ' ', $status))) . '</span>';
}

/** Turns "2026-12-04" into a validated date string, or null if it's not a real date. */
function clean_date(string $value): ?string
{
    $d = DateTime::createFromFormat('Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : null;
}

function clean_time(string $value): ?string
{
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', substr($value, 0, 5)) ? substr($value, 0, 5) : null;
}

function today(): string
{
    return date('Y-m-d');
}

// --------------------------------------------------------------------------
// Tiny query helpers so pages don't repeat prepare/bind/execute everywhere.
//   db_all($conn, "SELECT * FROM x WHERE id = ?", "i", [$id])
// --------------------------------------------------------------------------
function db_query(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = mysqli_prepare($conn, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_query($conn, $sql, $types, $params);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $rows = db_all($conn, $sql, $types, $params);
    return $rows[0] ?? null;
}

function db_value(mysqli $conn, string $sql, string $types = '', array $params = [])
{
    $row = db_one($conn, $sql, $types, $params);
    return $row ? reset($row) : null;
}

/** Run an INSERT/UPDATE/DELETE. Returns the new id for INSERTs. */
function db_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = db_query($conn, $sql, $types, $params);
    $id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

// --------------------------------------------------------------------------
// Photo uploads (admin: categories, rental items, services)
// --------------------------------------------------------------------------

/**
 * Save an uploaded photo from <input type="file" name="$field">.
 * Returns ['path' => 'assets/uploads/abc.jpg'] on success,
 *         ['path' => null] if no file was chosen,
 *         ['error' => 'message'] if the file was rejected.
 */
function save_uploaded_image(string $field): array
{
    $file = $_FILES[$field] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['path' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'The photo could not be uploaded (it may be too large).'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['error' => 'Photos must be ' . (UPLOAD_MAX_BYTES / 1024 / 1024) . ' MB or smaller.'];
    }

    // Check what the file really is from its contents, not its name.
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
        return ['error' => 'Please upload a JPG, PNG or WebP photo.'];
    }

    $dir = __DIR__ . '/../' . UPLOAD_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['error' => 'The uploads folder is missing and could not be created.'];
    }
    // Random name: nobody can guess or overwrite another file.
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        return ['error' => 'The uploads folder is not writable.'];
    }
    return ['path' => UPLOAD_DIR . $name];
}

/** Delete a photo we uploaded earlier (never touches the built-in assets/img). */
function delete_uploaded_image(?string $path): void
{
    if ($path && strpos($path, UPLOAD_DIR) === 0 && preg_match('/^[a-f0-9]+\.(jpg|png|webp)$/', basename($path))) {
        @unlink(__DIR__ . '/../' . $path);
    }
}
