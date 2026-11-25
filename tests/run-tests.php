<?php
// ==========================================================================
// Romero's Party Needs — automated end-to-end tests
//
// Clicks through the real site over HTTP (like a browser would) as a guest,
// a customer and an admin, and checks that every rule holds: stock limits,
// fully-booked services, blocked dates, edits, payments, roles, uploads...
//
// HOW TO RUN (Apache + MySQL must be running in XAMPP):
//   C:\xampp\php\php.exe tests\run-tests.php --reset
//   C:\xampp\php\php.exe tests\run-tests.php --reset http://localhost/Romeros-Party-Needs.PHP/
//
// What happens to your data:
//   1. The whole database is BACKED UP to tests/backups/ (the run stops if
//      the backup fails).
//   2. The tests run on a fresh copy of the demo data (db_setup/db.sql).
//   3. Your backed-up data is RESTORED exactly as it was, even if a test
//      crashes. The backup file is kept, so it can also be imported by hand
//      in phpMyAdmin if the run is interrupted with Ctrl+C.
// --reset is still required, as a reminder that the database is replaced
// while the tests run. Never run this against a live site.
// ==========================================================================

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line, not the browser.\n");
}
if (!extension_loaded('curl')) {
    exit("The PHP curl extension is required (enable extension=curl in php.ini).\n");
}

$args = array_slice($argv, 1);
if (!in_array('--reset', $args, true)) {
    exit("While these tests run, the database is replaced by the demo data (db_setup/db.sql).\n"
        . "Your data is backed up first and put back afterwards.\n"
        . "Run again with --reset to confirm:  php tests/run-tests.php --reset\n");
}
$urlArg = array_values(array_filter($args, fn($a) => str_starts_with($a, 'http')))[0] ?? null;
$base = rtrim($urlArg ?? 'http://localhost/' . basename(dirname(__DIR__)), '/') . '/';

$_SERVER['SCRIPT_NAME'] = '/tests/run-tests.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/booking-functions.php';

// --------------------------------------------------------------------------
// Tiny test framework
// --------------------------------------------------------------------------
$passed = 0;
$failed = [];
function test(string $name, bool $ok, string $why = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  \033[32mPASS\033[0m  $name\n";
    } else {
        $failed[] = $name;
        echo "  \033[31mFAIL\033[0m  $name" . ($why ? "  ($why)" : '') . "\n";
    }
}
function section(string $title): void
{
    echo "\n\033[1m$title\033[0m\n";
}
function day(int $n): string
{
    return date('Y-m-d', strtotime("+$n days"));
}
function first_non_sunday(int $from): string
{
    for ($n = $from; ; $n++) {
        if (date('w', strtotime(day($n))) !== '0') return day($n);
    }
}
function reset_database(mysqli $conn): void
{
    $sql = file_get_contents(__DIR__ . '/../db_setup/db.sql');
    mysqli_multi_query($conn, $sql);
    do {
        if ($r = mysqli_store_result($conn)) mysqli_free_result($r);
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    mysqli_select_db($conn, $GLOBALS['db_name']);
}
/**
 * Save the whole database to tests/backups/before-tests-<time>.sql using
 * mysqldump, BEFORE the tests touch anything. Returns the file path, or
 * stops the run if the backup can't be made (so nothing is ever lost).
 */
function backup_database(): string
{
    global $db_host, $db_user, $db_pass, $db_name, $db_port;
    $candidates = [dirname(PHP_BINARY) . '/../mysql/bin/mysqldump.exe', 'C:/xampp/mysql/bin/mysqldump.exe', '/opt/lampp/bin/mysqldump'];
    $dump = 'mysqldump'; // fall back to whatever is on the PATH
    foreach ($candidates as $c) {
        if (is_file($c)) { $dump = $c; break; }
    }
    $dir = __DIR__ . '/backups';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . '/before-tests-' . date('Y-m-d_His') . '.sql';

    $cmd = escapeshellarg($dump)
        . ' --protocol=TCP --host=' . escapeshellarg($db_host) . ' --port=' . (int) $db_port
        . ' --user=' . escapeshellarg($db_user) . ($db_pass !== '' ? ' --password=' . escapeshellarg($db_pass) : '')
        . ' --single-transaction --routines --default-character-set=utf8mb4'
        . ' --result-file=' . escapeshellarg($file) . ' ' . escapeshellarg($db_name) . ' 2>&1';
    exec($cmd, $output, $code);
    $content = is_file($file) ? file_get_contents($file) : '';
    if ($code !== 0 || !str_contains($content, 'Dump completed')) {
        @unlink($file);
        exit("Could not back up the database, so the tests were NOT run and nothing was changed.\n"
            . "mysqldump said: " . trim(implode("\n", $output)) . "\n");
    }
    return $file;
}

/** Put the backed-up database back exactly as it was before the tests. */
function restore_database(mysqli $conn, string $file): bool
{
    mysqli_select_db($conn, $GLOBALS['db_name']);
    $ok = mysqli_multi_query($conn, file_get_contents($file));
    do {
        if ($r = mysqli_store_result($conn)) mysqli_free_result($r);
    } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    return $ok && mysqli_errno($conn) === 0;
}

function q(string $sql, string $types = '', array $params = [])
{
    return db_value($GLOBALS['conn'], $sql, $types, $params);
}

/** A browser with its own cookies (= its own login session). */
final class Browser
{
    public int $status = 0;
    public string $body = '';
    public string $location = '';
    /** Response headers of the last request, lower-cased names. */
    public array $headers = [];
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'rpn');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function get(string $path): self
    {
        return $this->send('GET', $path);
    }

    /** POST a form. Adds the CSRF token automatically unless $csrf is false. */
    public function post(string $path, array $data = [], array $files = [], bool $csrf = true): self
    {
        if ($csrf && !isset($data['csrf_token'])) {
            $data['csrf_token'] = $this->token();
        }
        return $this->send('POST', $path, $data, $files);
    }

    public function follow(): self
    {
        return $this->location ? $this->send('GET', $this->location) : $this;
    }

    public function has(string $text): bool
    {
        return str_contains($this->body, $text);
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function phpErrors(): bool
    {
        return (bool) preg_match('/(Fatal error|Warning|Notice|Deprecated|Uncaught)\s*<\/b>?:/i', $this->body)
            || (bool) preg_match('/<b>(Fatal error|Warning|Notice|Deprecated)<\/b>/i', $this->body);
    }

    public function token(): string
    {
        // The token is stored in the session, so any page with a form shows it.
        $this->send('GET', 'contact.php');
        preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $this->body, $m);
        return $m[1] ?? '';
    }

    public function login(string $email, string $password): self
    {
        return $this->post('login.php', ['login_submit' => 1, 'email' => $email, 'password' => $password]);
    }

    private function send(string $method, string $path, array $data = [], array $files = []): self
    {
        $url = preg_match('#^https?://#', $path) ? $path : $this->base . ltrim($path, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $this->headers[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
        ]);
        $this->headers = [];
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($files) {
                foreach ($files as $field => [$filePath, $mime, $name]) {
                    $data[$field] = new CURLFile($filePath, $mime, $name);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        $this->body = (string) curl_exec($ch);
        $this->status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->location = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
        curl_close($ch);
        return $this;
    }
}

// --------------------------------------------------------------------------
echo "Testing $base\n";
$probe = new Browser($base);
if ($probe->get('index.php')->status !== 200) {
    exit("Could not open {$base}index.php (HTTP {$probe->status}). Is Apache running and is the URL right?\n");
}
$backupFile = backup_database();
echo "Your database was backed up to tests/backups/" . basename($backupFile) . "\n";
$restored = false;
// Even if a test crashes half-way, put the real data back.
register_shutdown_function(function () use ($backupFile, &$restored) {
    if (!$restored) {
        $ok = restore_database($GLOBALS['conn'], $backupFile);
        echo $ok ? "\nRun stopped early; your data was restored from the backup.\n"
                 : "\nRun stopped early and the restore FAILED. Import tests/backups/" . basename($backupFile) . " in phpMyAdmin.\n";
    }
});
reset_database($conn);
echo "Tests are using a fresh copy of the demo data.\n";
// Remember which photos already exist so only the test's uploads get removed.
$uploadsBefore = glob(__DIR__ . '/../' . UPLOAD_DIR . '*') ?: [];

$D6 = day(6);          // two demo bookings share this date (stock + hosts used up)
$D15 = day(15);        // blocked in the demo data
$D30 = day(30);
$APPT = first_non_sunday(4);   // before the day-6 event it's linked to
$SUNDAY = (function () { for ($n = 2; ; $n++) if (date('w', strtotime(day($n))) === '0') return day($n); })();
$png = tempnam(sys_get_temp_dir(), 'png');
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$fake = tempnam(sys_get_temp_dir(), 'txt');
file_put_contents($fake, "<?php echo 'not an image'; ?>");

// ==========================================================================
section('Guest & access guards (WBS 1.2.2)');
$g = new Browser($base);
foreach (['index.php', 'services.php', 'about.php', 'contact.php', 'login.php', 'signup.php'] as $p) {
    $g->get($p);
    test("guest can open $p", $g->status === 200 && !$g->phpErrors());
}
$g->get('book.php');
test('booking requires login (redirects to login with ?next=)', $g->status === 302 && str_contains($g->location, 'login.php?next=book.php'));
$g->get('admin/index.php');
test('admin area requires login', $g->status === 302 && str_contains($g->location, 'login.php'));
foreach (['db_setup/db.sql', 'includes/db.php', 'includes/config.php', 'README.md', 'assets/img/'] as $p) {
    $g->get($p);
    test("direct web access blocked: $p", $g->status === 403, "HTTP {$g->status}");
}
$g->post('contact.php', ['contact_submit' => 1, 'name' => 'x', 'email' => 'x@x.com', 'message' => 'hi'], [], false);
test('form without CSRF token is rejected', $g->status === 400);

// ==========================================================================
section('Customer: catalog & booking (WBS 1.3)');
$juan = new Browser($base);
$juan->login('juan@example.com', 'customer123');
test('customer login goes to My bookings', str_contains($juan->location, 'account.php'));
foreach (['account.php', 'book.php', 'appointment.php', 'services.php', 'booking.php?ref=RPN-7K2M9Q'] as $p) {
    $juan->get($p);
    test("customer can open $p", $juan->status === 200 && !$juan->phpErrors());
}
$juan->get('booking.php?ref=RPN-4H8T2W');
test("customer can't see someone else's booking (404)", $juan->status === 404);
$juan->get('admin/index.php');
test('customer gets a friendly 403 on admin pages', $juan->status === 403 && $juan->has('Admins only'));

$avail = json_decode($g->get("api/availability.php?date=$D6")->body, true);
test('API: Tiffany chairs 20 free on shared demo date (120 − 100)', ($avail['items'][2]['available'] ?? null) === 20);
test('API: host service fully booked on shared demo date', ($avail['services'][1]['remaining'] ?? null) === 0);

$form = ['event_type' => 'Birthday', 'guest_count' => 25, 'venue_address' => 'Test Hall, Lipa',
         'setup_time' => '08:00', 'event_start' => '12:00', 'event_end' => '17:00'];
$juan->post('book.php', $form + ['event_date' => $D6, 'items' => [2 => 25]]);
test('over-booking refused (25 > 20 Tiffany chairs)', $juan->has('Only 20 × Tiffany chair left'));
$juan->post('book.php', $form + ['event_date' => $D6, 'items' => [2 => 5], 'services' => [1]]);
test('fully booked service refused', $juan->has('Event host / emcee is fully booked'));
$juan->post('book.php', $form + ['event_date' => $D15, 'items' => [1 => 10]]);
test('blocked date refused', $juan->has('not taking bookings'));
$juan->post('book.php', $form + ['event_date' => day(1), 'items' => [1 => 10]]);
test('date inside the notice period refused', $juan->has('notice'));
$juan->post('book.php', ['setup_time' => '13:00'] + $form + ['event_date' => $D6, 'items' => [1 => 10]]);
test('setup after start time refused', $juan->has('Setup must be before'));
$juan->post('book.php', $form + ['event_date' => $D6]);
test('empty booking refused', $juan->has('at least one rental item or service'));

$juan->post('book.php', $form + ['event_date' => $D6, 'items' => [2 => 20, 3 => 2], 'services' => [3]]);
preg_match('/ref=(RPN-[A-Z0-9]+)/', $juan->location, $m);
$REF = $m[1] ?? '';
test('valid booking saved, redirected to its page', $REF !== '');
$bid = (int) q("SELECT id FROM bookings WHERE reference = ?", "s", [$REF]);
test('booking total = 20×85 + 2×250 + 12,000 + 1,000 delivery = 15,200', (float) q("SELECT total_amount FROM bookings WHERE id = ?", "i", [$bid]) === 15200.0);
$avail = json_decode($g->get("api/availability.php?date=$D6")->body, true);
test('Tiffany chairs now 0 free on that date', ($avail['items'][2]['available'] ?? null) === 0);
test('party organizer now fully booked (2 of 2)', ($avail['services'][3]['remaining'] ?? null) === 0);
test('crew slots: 3 of 4 used', ($avail['bookings_on_date'] ?? null) === 3);

// ==========================================================================
section('Customer: managing a booking (WBS 1.3.3)');
$juan->get("book.php?edit=$REF");
test('edit form opens pre-filled', $juan->status === 200 && $juan->has('Edit booking ' . $REF) && $juan->has('value="Test Hall, Lipa"'));
$ex = json_decode($juan->get("api/availability.php?date=$D6&exclude=$REF")->body, true);
test("edit view doesn't count the booking's own items (20 free again)", ($ex['items'][2]['available'] ?? null) === 20);
$ex = json_decode($g->get("api/availability.php?date=$D6&exclude=$REF")->body, true);
test("a guest can't use someone else's booking to see extra stock", ($ex['items'][2]['available'] ?? null) === 0);
$juan->post("book.php?edit=$REF", $form + ['event_date' => $D6, 'items' => [2 => 21, 3 => 2], 'services' => [3]]);
test('edit still respects stock (21 > 20 refused)', $juan->has('Only 20 × Tiffany chair left'));
$juan->post("book.php?edit=$REF", $form + ['event_date' => $D6, 'items' => [2 => 10, 3 => 2], 'services' => [3]]);
test('edit saved: total now 14,350', (float) q("SELECT total_amount FROM bookings WHERE id = ?", "i", [$bid]) === 14350.0);
test('edit kept the same reference (no duplicate booking)', (int) q("SELECT COUNT(*) FROM bookings WHERE user_id = 2 AND event_date = ?", "s", [$D6]) === 2);
test('edit recorded in the timeline', (int) q("SELECT COUNT(*) FROM booking_logs WHERE booking_id = ? AND action = 'updated'", "i", [$bid]) === 1);
$juan->post("book.php?edit=$REF", $form + ['event_date' => $D15, 'items' => [2 => 10], 'services' => [3]]);
test('edit to a blocked date refused', $juan->has('not taking bookings'));
$juan->get('book.php?edit=RPN-7K2M9Q');
test("confirmed bookings can't be edited by the customer", $juan->status === 302 && str_contains($juan->location, 'booking.php?ref=RPN-7K2M9Q'));

$juan->post('appointment.php', ['purpose' => 'Ocular / venue inspection', 'appointment_date' => $APPT, 'appointment_time' => '13:00', 'booking_id' => $bid]);
test('appointment booked for a free slot', str_contains($juan->location, 'account.php'));
test('appointment is linked to the booking timeline', (int) q("SELECT COUNT(*) FROM booking_logs WHERE booking_id = ? AND action = 'appointment'", "i", [$bid]) === 1);
$juan->post('appointment.php', ['purpose' => 'Planning consultation', 'appointment_date' => $APPT, 'appointment_time' => '13:00']);
test('same slot twice refused', $juan->has('just taken') || $juan->has('available time slots'));
$juan->post('appointment.php', ['purpose' => 'Planning consultation', 'appointment_date' => first_non_sunday(7), 'appointment_time' => '14:30', 'booking_id' => 1]);
test('appointment dated after its event is refused', $juan->has('on or before the event'));
$juan->post('appointment.php', ['purpose' => 'Planning consultation', 'appointment_date' => $SUNDAY, 'appointment_time' => '10:30']);
test('Sunday appointment refused', $juan->has('closed for appointments on Sundays'));

$juan->post('account.php', ['action' => 'password', 'current_password' => 'wrong', 'new_password' => 'newpass1', 'confirm_password' => 'newpass1'])->follow();
test('change password needs the current password', $juan->has('current password is incorrect'));

$juan->post("booking.php?ref=$REF", ['action' => 'cancel']);
$avail = json_decode($g->get("api/availability.php?date=$D6")->body, true);
test('cancelling releases the items (Tiffany back to 20)', ($avail['items'][2]['available'] ?? null) === 20);
test('cancelling cancels its appointments', (int) q("SELECT COUNT(*) FROM appointments WHERE booking_id = ? AND status = 'scheduled'", "i", [$bid]) === 0);

$juan->post('contact.php', ['contact_submit' => 1, 'name' => 'Juan', 'email' => 'juan@example.com', 'message' => 'No date given']);
test('contact form requires an event date', $juan->has('event date') && !q("SELECT COUNT(*) FROM inquiries WHERE inquiry = 'No date given'"));
$juan->post('contact.php', ['contact_submit' => 1, 'name' => 'Juan', 'email' => 'juan@example.com', 'eventDate' => $D30, 'message' => 'Automated test message']);
test('contact form saves and links to the account', $juan->has('your message has been sent') && (int) q("SELECT user_id FROM inquiries WHERE inquiry = 'Automated test message'") === 2);

$new = new Browser($base);
$new->post('signup.php', ['signup_submit' => 1, 'full_name' => 'Test Person', 'email' => 'test.person@example.com', 'phone' => '0917 555 0000', 'password' => 'secret1', 'confirm_password' => 'secret1']);
test('sign up creates the account and logs in', str_contains($new->location, 'account.php'));

// ==========================================================================
section('Admin control panel (WBS 1.4)');
$admin = new Browser($base);
$admin->login('admin@romerospartyneeds.com', 'admin123');
test('admin login goes to the dashboard', str_contains($admin->location, 'admin/index.php'));
foreach (['admin/index.php', 'admin/bookings.php', 'admin/bookings.php?status=pending&q=maria', 'admin/booking.php?id=2',
          'admin/calendar.php', "admin/calendar.php?day=$D6", 'admin/inventory.php', 'admin/categories.php', 'admin/services.php',
          'admin/appointments.php?view=all', 'admin/inquiries.php', 'admin/customers.php', 'admin/users.php', 'admin/system.php', 'admin/reports.php', 'book.php'] as $p) {
    $admin->get($p);
    test("admin can open $p", $admin->status === 200 && !$admin->phpErrors());
}
$admin->get("admin/calendar.php?day=$D6");
test('day plan totals Tiffany chairs across bookings (100)', (bool) preg_match('/Tiffany chair.*?<strong>100<\/strong>/s', $admin->body));
$admin->get('admin/system.php');
test('system check reports no problems', $admin->has('>0 problems<'));

$admin->post('admin/booking.php?id=2', ['action' => 'status', 'to' => 'confirmed']);
test('admin confirms a pending booking', q("SELECT status FROM bookings WHERE id = 2") === 'confirmed');
$admin->post('admin/booking.php?id=2', ['action' => 'payment', 'amount' => 99999999, 'method' => 'cash'])->follow();
test('overpayment refused', $admin->has('Enter an amount between'));
$admin->post('admin/booking.php?id=2', ['action' => 'payment', 'amount' => 17496, 'method' => 'gcash', 'reference_no' => 'GC-1', 'paid_at' => date('Y-m-d')])->follow();
test('payment recorded → status Partial', $admin->has('status-partial'));
$sched = ['action' => 'schedule', 'setup_time' => '08:00', 'event_start' => '14:00', 'event_end' => '22:00', 'venue_address' => 'Villa', 'guest_count' => 120];
$admin->post('admin/booking.php?id=2', $sched + ['event_date' => $D6])->follow();
test('reschedule onto a date without enough stock refused', $admin->has('Cannot move to'));
$admin->post('admin/booking.php?id=2', $sched + ['event_date' => $D30]);
test('reschedule to a free date works', q("SELECT event_date FROM bookings WHERE id = 2") === $D30);
$admin->post("admin/booking.php?id=$bid", ['action' => 'status', 'to' => 'pending']);
test('restore a cancelled booking when stock is free', q("SELECT status FROM bookings WHERE id = ?", "i", [$bid]) === 'pending');

$admin->post('admin/inventory.php', ['action' => 'save', 'id' => 2, 'name' => 'Tiffany chair', 'category_id' => 1, 'unit' => 'piece', 'price' => 85, 'total_quantity' => 50]);
test("inventory can't drop below what upcoming bookings hold", $admin->has('go below 120'));
$admin->post('admin/inventory.php', ['action' => 'save', 'id' => 0, 'name' => 'Photo booth frame', 'category_id' => 3, 'unit' => 'piece', 'price' => 500, 'total_quantity' => 2], ['photo' => [$png, 'image/png', 'frame.png']]);
$img = q("SELECT image FROM inventory_items WHERE name = 'Photo booth frame'");
test('new item added with a photo', is_string($img) && str_starts_with($img, UPLOAD_DIR) && is_file(__DIR__ . '/../' . $img));
$g->get('services.php');
test('new item shows on the public Services page', $g->has('Photo booth frame'));
test('uploaded photo is served', $img && $g->get($img)->status === 200);

$admin->post('admin/categories.php', ['action' => 'save', 'id' => 0, 'name' => 'Lights & Sound', 'description' => 'Test', 'sort_order' => 5], ['photo' => [$fake, 'image/jpeg', 'evil.jpg']]);
test('a non-image disguised as .jpg is rejected', $admin->has('Please upload a JPG, PNG or WebP photo'));
$admin->post('admin/categories.php', ['action' => 'save', 'id' => 0, 'name' => 'Lights & Sound', 'description' => 'Test', 'sort_order' => 5], ['photo' => [$png, 'image/png', 'lights.png']]);
$catId = (int) q("SELECT id FROM categories WHERE slug = 'lights-sound'");
test('new category created with a slug and photo', $catId > 0 && q("SELECT image FROM categories WHERE id = ?", "i", [$catId]) !== null);
$catImg = q("SELECT image FROM categories WHERE id = ?", "i", [$catId]);
$admin->post('admin/categories.php', ['action' => 'delete', 'id' => $catId]);
test('deleting an empty category also deletes its photo', !q("SELECT COUNT(*) FROM categories WHERE id = ?", "i", [$catId]) && !is_file(__DIR__ . '/../' . $catImg));

$planted = __DIR__ . '/../' . UPLOAD_DIR . 'planted-test.php';
file_put_contents($planted, "<?php echo 'EXECUTED';");
$g->get(UPLOAD_DIR . 'planted-test.php');
test('scripts in the uploads folder never run', $g->status === 403 && !$g->has('EXECUTED'));
@unlink($planted);

$admin->post('admin/calendar.php', ['action' => 'block', 'date' => $D30, 'reason' => 'Test']);
test('blocked date shows on the public calendar', (json_decode($g->get('api/calendar.php?month=' . substr($D30, 0, 7))->body, true)['days'][$D30] ?? '') === 'blocked');
$admin->post('admin/calendar.php', ['action' => 'unblock', 'date' => $D30]);
test('unblock date', (json_decode($g->get('api/calendar.php?month=' . substr($D30, 0, 7))->body, true)['days'][$D30] ?? '') !== 'blocked');

$admin->post('book.php', array_merge($form, ['customer_id' => 3, 'event_type' => 'Reunion', 'event_date' => day(40), 'items' => [1 => 40, 7 => 2]]));
test('admin books on behalf of a customer', str_contains($admin->location, 'admin/booking.php?id=') && (int) q("SELECT user_id FROM bookings WHERE event_type = 'Reunion'") === 3);

// ==========================================================================
section('Reports, exports, speed & hardening (v1.1)');

// The faster availability query must give exactly the same numbers as the
// plain "add up every booking" way of counting, for every item and service.
$fresh = get_item_availability($conn, $D6);
$mismatch = [];
foreach ($fresh as $id => $row) {
    $plain = (int) q("SELECT COALESCE(SUM(bi.quantity), 0) FROM booking_items bi JOIN bookings b ON b.id = bi.booking_id
                      WHERE bi.item_id = ? AND b.event_date = ? AND b.status IN ('pending','confirmed')", "is", [$id, $D6]);
    if ($plain !== $row['reserved']) $mismatch[] = $row['name'];
}
test('optimized item availability matches a plain count for every item', !$mismatch && count($fresh) > 0, implode(', ', $mismatch));
$mismatch = [];
foreach (get_service_availability($conn, $D6) as $id => $row) {
    $plain = (int) q("SELECT COUNT(*) FROM booking_services bs JOIN bookings b ON b.id = bs.booking_id
                      WHERE bs.service_id = ? AND b.event_date = ? AND b.status IN ('pending','confirmed')", "is", [$id, $D6]);
    if ($plain !== $row['used']) $mismatch[] = $row['name'];
}
test('optimized service availability matches a plain count for every service', !$mismatch, implode(', ', $mismatch));
$excluded = get_item_availability($conn, $D6, 1); // booking 1 (Juan's, 40 Monobloc chairs) left out
test('excluding a booking frees exactly its items', $excluded[1]['reserved'] === $fresh[1]['reserved'] - (int) q("SELECT COALESCE(SUM(quantity), 0) FROM booking_items WHERE booking_id = 1 AND item_id = 1"));

$g->get('index.php');
test('security headers sent (nosniff, frame protection, referrer policy)',
    $g->header('X-Content-Type-Options') === 'nosniff' && $g->header('X-Frame-Options') === 'SAMEORIGIN' && $g->header('Referrer-Policy') !== '');
test('CSS/JS links carry a version so browsers can cache them safely', (bool) preg_match('/style\.css\?v=\d+/', $g->body) && (bool) preg_match('/script\.js\?v=\d+/', $g->body));
test('favicon is linked and served', $g->has('favicon.svg') && (new Browser($base))->get('assets/img/favicon.svg')->status === 200);
$g->get("api/availability.php?date=$D6");
test('availability API is never cached', str_contains($g->header('Cache-Control'), 'no-store'));
$g->post('contact.php', ['contact_submit' => 1, 'name' => 'x', 'email' => 'x@x.com', 'message' => 'hi'], [], false);
test('expired form shows a friendly page (not a bare message)', $g->status === 400 && $g->has('This form has expired') && $g->has('site-nav'));
$admin->get('admin/bookings.php?q[]=x&status[]=y');
test('hand-edited URL with array values does not crash', $admin->status === 200 && !$admin->phpErrors());
$admin->get('admin/calendar.php?month=2026-13');
test('invalid calendar month falls back to this month', $admin->status === 200 && $admin->has(date('F Y')) && !$admin->has('1970'));

// CSV export of the bookings list (respects the filters).
$admin->get('admin/bookings.php?export=csv');
$lines = array_values(array_filter(explode("\n", trim($admin->body))));
test('bookings export is a CSV download', str_starts_with($admin->header('Content-Type'), 'text/csv') && str_contains($admin->header('Content-Disposition'), 'bookings-'));
test('bookings export has a header row + one row per booking', count($lines) === 1 + (int) q("SELECT COUNT(*) FROM bookings") && str_contains($admin->body, 'RPN-7K2M9Q'));
$admin->get('admin/bookings.php?export=csv&status=pending');
test('bookings export respects the status filter', count(array_filter(explode("\n", trim($admin->body)))) === 1 + (int) q("SELECT COUNT(*) FROM bookings WHERE status = 'pending'"));
test('CSV cells that look like formulas are neutralised', csv_safe('=HYPERLINK("x")') === "'=HYPERLINK(\"x\")" && csv_safe('-500.00') === '-500.00' && csv_safe('Maria') === 'Maria');

// Pagination: add 60 extra (completed, past) bookings, then remove them.
db_exec($conn, "INSERT INTO bookings (reference, user_id, event_type, event_date, setup_time, event_start, event_end, venue_address, guest_count, items_total, services_total, delivery_fee, total_amount, status)
                SELECT CONCAT('RPN-PG', LPAD(seq, 4, '0')), 2, 'Birthday', DATE_SUB(CURDATE(), INTERVAL seq DAY), '08:00', '12:00', '17:00', 'Paging test', 10, 0, 0, 0, 0, 'completed'
                FROM (SELECT a.n * 10 + b.n + 1 AS seq FROM
                      (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) a,
                      (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b) s");
$totalB = (int) q("SELECT COUNT(*) FROM bookings");
$admin->get('admin/bookings.php');
test('bookings list shows 50 per page with a pager', substr_count($admin->body, 'booking.php?id=') === 50 && $admin->has("Showing 1–50 of $totalB"));
$admin->get('admin/bookings.php?page=2');
test('page 2 shows the rest', substr_count($admin->body, 'booking.php?id=') === $totalB - 50 && $admin->has('Page 2 of 2'));
$admin->get('admin/bookings.php?page=99');
test('a page number past the end shows the last page', $admin->status === 200 && $admin->has('Page 2 of 2'));
db_exec($conn, "DELETE FROM bookings WHERE reference LIKE 'RPN-PG%'");

// Reports page numbers come straight from the database.
$year = (int) date('Y');
$expectValue = (float) q("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status <> 'cancelled' AND YEAR(event_date) = ?", "i", [$year]);
$expectPaid = (float) q("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE YEAR(paid_at) = ?", "i", [$year]);
$admin->get("admin/reports.php?year=$year");
test('reports: booked value for the year matches the bookings', $admin->has('>' . peso($expectValue) . '<'));
test('reports: collected for the year matches the payments', $admin->has('>' . peso($expectPaid) . '<'));
test('reports: Tiffany chair listed among top items', $admin->has('Tiffany chair'));
test('reports tab in the admin menu', $admin->has('admin/reports.php'));
$admin->get("admin/reports.php?year=$year&export=monthly");
test('monthly summary CSV: header + 12 months + total', count(array_filter(explode("\n", trim($admin->body)))) === 14);
$admin->get("admin/reports.php?year=$year&export=payments");
test('payments CSV lists every payment of the year', count(array_filter(explode("\n", trim($admin->body)))) === 1 + (int) q("SELECT COUNT(*) FROM payments WHERE YEAR(paid_at) = ?", "i", [$year]));
$admin->get('admin/reports.php?year=1999');
test('reports: unknown year falls back to this year', $admin->status === 200 && $admin->has("Reports <span class=\"muted\">$year</span>"));

// Uploaded photos are scaled down (only where PHP's GD extension is on).
if (function_exists('imagecreatetruecolor')) {
    $big = tempnam(sys_get_temp_dir(), 'big') . '.jpg';
    imagejpeg(imagecreatetruecolor(2400, 1200), $big);
    $shrunk = shrink_image($big, 'image/jpeg');
    [$bw, $bh] = getimagesize($big);
    test('large photos are scaled down to 1600px (keeping the shape)', $shrunk && $bw === 1600 && $bh === 800);
    test('small photos are left alone', !shrink_image($png, 'image/png'));
    @unlink($big);
} else {
    echo "  \033[33mSKIP\033[0m  photo resizing (PHP GD extension not enabled for the CLI; run with -d extension=gd to include it)\n";
}

// ==========================================================================
section('Accounts & roles (WBS 1.4.4 / 1.2.1)');
$admin->post('admin/users.php', ['action' => 'save', 'id' => 0, 'full_name' => 'QA Tester', 'email' => 'qa@example.com', 'phone' => '0917 000 1111', 'role' => 'customer', 'password' => 'qa1234', 'is_active' => 1]);
$qaId = (int) q("SELECT id FROM users WHERE email = 'qa@example.com'");
test('admin creates an account', $qaId > 0);
$qa = new Browser($base);
$qa->login('qa@example.com', 'qa1234');
test('new account can log in', str_contains($qa->location, 'account.php'));
$admin->post('admin/users.php', ['action' => 'toggle', 'id' => $qaId]);
test('admin deactivates the account', (int) q("SELECT is_active FROM users WHERE id = ?", "i", [$qaId]) === 0);
$qa->get('account.php');
test('deactivated user is signed out immediately', $qa->status === 302 && str_contains($qa->location, 'login.php'));
$qa2 = new Browser($base);
$qa2->login('qa@example.com', 'qa1234');
test('deactivated user cannot log in', $qa2->has('deactivated'));

$admin->post('admin/users.php', ['action' => 'save', 'id' => 1, 'full_name' => 'Romero Admin', 'email' => 'admin@romerospartyneeds.com', 'role' => 'customer', 'is_active' => 1]);
test("admin can't remove their own admin role", $admin->has('remove your own admin access') && q("SELECT role FROM users WHERE id = 1") === 'admin');
$admin->post('admin/users.php', ['action' => 'toggle', 'id' => 1]);
test("admin can't deactivate themselves", (int) q("SELECT is_active FROM users WHERE id = 1") === 1);

$admin->post('admin/users.php', ['action' => 'save', 'id' => 2, 'full_name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'phone' => '0917 123 4567', 'role' => 'admin', 'is_active' => 1]);
$juan->get('admin/index.php');
test('promoting a user takes effect without logging out', $juan->status === 200);
$admin->post('admin/users.php', ['action' => 'save', 'id' => 2, 'full_name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'phone' => '0917 123 4567', 'role' => 'customer', 'is_active' => 1]);
$juan->get('admin/index.php');
test('demoting a user removes admin access immediately', $juan->status === 403);

$admin->post('admin/users.php', ['action' => 'save', 'id' => 3, 'full_name' => 'Maria Santos', 'email' => 'maria@example.com', 'phone' => '0918 765 4321', 'role' => 'customer', 'is_active' => 1, 'password' => 'reset99']);
$maria = new Browser($base);
$maria->login('maria@example.com', 'reset99');
test('admin password reset works', str_contains($maria->location, 'account.php'));

$thief = new Browser($base);
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS; $i++) {
    $thief->login('maria@example.com', 'guess' . $i);
}
$thief->login('maria@example.com', 'reset99');
test('login locked after ' . LOGIN_MAX_ATTEMPTS . ' wrong passwords (even with the right one)', $thief->has('Too many failed attempts'));
db_exec($conn, "DELETE FROM login_attempts");

// ==========================================================================
$restored = restore_database($conn, $backupFile);
@unlink($png);
@unlink($fake);
foreach (array_diff(glob(__DIR__ . '/../' . UPLOAD_DIR . '*') ?: [], $uploadsBefore) as $f) {
    @unlink($f); // photos uploaded by this test run
}

$total = $passed + count($failed);
echo "\n" . ($failed ? "\033[31m" : "\033[32m") . "$passed of $total passed\033[0m\n";
if ($failed) {
    echo "Failed:\n  - " . implode("\n  - ", $failed) . "\n";
}
echo $restored
    ? "Your data was restored exactly as it was before the tests (backup kept in tests/backups/).\n"
    : "\033[31mRestoring your data FAILED.\033[0m Import tests/backups/" . basename($backupFile) . " in phpMyAdmin to get it back.\n";
exit($failed || !$restored ? 1 : 0);
