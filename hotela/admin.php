<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/supabase.php';

function adminLoggedIn(): bool
{
    return ($_SESSION['hotel_admin'] ?? false) === true;
}

function adminCsrfToken(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function adminCsrfValid(): bool
{
    return isset($_POST['csrf'], $_SESSION['admin_csrf'])
        && hash_equals($_SESSION['admin_csrf'], (string) $_POST['csrf']);
}

function redirectAdmin(): never
{
    header('Location: admin.php');
    exit;
}

$message = (string) ($_SESSION['admin_message'] ?? '');
unset($_SESSION['admin_message']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if ($adminEmail !== '' && $adminPasswordHash !== '' && hash_equals($adminEmail, $email) && password_verify($password, $adminPasswordHash)) {
            session_regenerate_id(true);
            $_SESSION['hotel_admin'] = true;
            $_SESSION['admin_message'] = 'You are now signed in as the hotel administrator.';
            redirectAdmin();
        }
        $error = 'The email address or password is incorrect.';
    } elseif (!adminLoggedIn()) {
        $error = 'Please sign in to continue.';
    } elseif (!adminCsrfValid()) {
        $error = 'Your session form has expired. Refresh the page and try again.';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['admin_message'] = 'You have been signed out.';
        redirectAdmin();
    } elseif ($action === 'update_booking') {
        $bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        $status = (string) ($_POST['status'] ?? '');
        $allowedStatuses = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!$bookingId || !in_array($status, $allowedStatuses, true)) {
            $error = 'Choose a valid booking and status.';
        } else {
            $result = supabaseAdminRequest('/rest/v1/bookings?id=eq.' . $bookingId, 'PATCH', ['status' => $status]);
            if ($result['ok']) {
                $_SESSION['admin_message'] = 'Booking #' . $bookingId . ' was updated to ' . $status . '.';
                redirectAdmin();
            }
            $error = 'The booking could not be updated. Check the server configuration and try again.';
        }
    } elseif ($action === 'add_employee') {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['employee_email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $jobTitle = trim((string) ($_POST['job_title'] ?? ''));
        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $jobTitle === '') {
            $error = 'Enter an employee name, a valid email address, and a job title.';
        } else {
            $result = supabaseAdminRequest('/rest/v1/employees', 'POST', [
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone ?: null,
                'job_title' => $jobTitle,
                'status' => 'active',
            ]);
            if ($result['ok']) {
                $_SESSION['admin_message'] = $fullName . ' was registered as an employee.';
                redirectAdmin();
            }
            $error = 'The employee could not be registered. The email address may already be in use.';
        }
    }
}

$bookings = [];
$employees = [];
$roomsById = [];
$adminLoadError = '';
if (adminLoggedIn()) {
    $roomsResult = supabaseAdminRequest('/rest/v1/rooms?select=id,room_number,room_type&order=room_number.asc');
    $bookingsResult = supabaseAdminRequest('/rest/v1/bookings?select=id,room_id,guest_name,guest_phone,check_in,check_out,guests_count,status,special_request,created_at&order=created_at.desc');
    $employeesResult = supabaseAdminRequest('/rest/v1/employees?select=id,full_name,email,phone,job_title,status,created_at&order=created_at.desc');
    if ($roomsResult['ok'] && is_array($roomsResult['data'])) {
        foreach ($roomsResult['data'] as $room) {
            $roomsById[(int) $room['id']] = $room;
        }
    }
    $bookings = $bookingsResult['ok'] && is_array($bookingsResult['data']) ? $bookingsResult['data'] : [];
    $employees = $employeesResult['ok'] && is_array($employeesResult['data']) ? $employeesResult['data'] : [];
    if (!$roomsResult['ok'] || !$bookingsResult['ok'] || !$employeesResult['ok']) {
        $adminLoadError = 'Dashboard data could not be loaded. Run the latest Supabase SQL and confirm SUPABASE_SECRET_KEY is set in backend/.env.';
    }
}

$bookingCounts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0];
foreach ($bookings as $booking) {
    $status = (string) ($booking['status'] ?? '');
    if (array_key_exists($status, $bookingCounts)) {
        $bookingCounts[$status]++;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>TEKU GRAND HOTEL | Administration</title>
  <style>
    :root{--navy:#14213d;--navy-2:#233b66;--gold:#c9973b;--cream:#f6f5f2;--line:#e4e4e7;--muted:#64748b;--white:#fff;--green:#157247;--red:#b23b32;--shadow:0 14px 38px rgba(17,35,67,.12)}*{box-sizing:border-box}body{margin:0;background:var(--cream);color:var(--navy);font:15px/1.5 Arial,Helvetica,sans-serif}.shell{width:min(1260px,calc(100% - 34px));margin:auto}.topbar{background:var(--navy);color:#fff}.topbar-inner{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{font-weight:800;letter-spacing:.08em}.brand span{display:block;color:#edcf8c;font-size:.68rem;letter-spacing:.16em}.topbar a{color:#fff;text-decoration:none}.button{display:inline-flex;align-items:center;justify-content:center;background:var(--gold);color:#1f2937;border:0;border-radius:7px;padding:10px 15px;font-weight:700;cursor:pointer;text-decoration:none}.button:hover{background:#dfb65f}.button-secondary{background:#fff;color:var(--navy)}.button-danger{background:#a2342c;color:#fff}.page{padding:36px 0 60px}.notice{border-radius:8px;padding:13px 16px;margin-bottom:20px}.notice-success{background:#e6f6ec;color:#145b34;border:1px solid #b7e2c4}.notice-error{background:#fff0ed;color:#9f3027;border:1px solid #f3c9c2}.login-wrap{min-height:calc(100vh - 76px);display:grid;place-items:center;padding:28px 0}.login-card{width:min(440px,100%);background:#fff;border-radius:13px;box-shadow:var(--shadow);padding:34px}.login-card h1{font-family:Georgia,serif;margin:0 0 8px;font-size:2rem}.login-card p{margin:0;color:var(--muted)}label{display:block;font-size:.86rem;font-weight:700;margin:18px 0 6px}input,select{width:100%;padding:11px;border:1px solid #cfd5df;border-radius:7px;font:inherit;color:var(--navy)}input:focus,select:focus{outline:0;border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,151,59,.17)}.login-card .button{width:100%;margin-top:20px}.dashboard-head{display:flex;justify-content:space-between;align-items:end;gap:20px;margin-bottom:26px}.dashboard-head h1{font-family:Georgia,serif;font-size:2.25rem;margin:0}.dashboard-head p{color:var(--muted);margin:5px 0 0}.actions{display:flex;gap:10px;flex-wrap:wrap}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:26px}.stat{background:#fff;border-radius:10px;padding:20px;box-shadow:0 7px 18px rgba(20,33,61,.07)}.stat span{display:block;color:var(--muted);font-size:.84rem}.stat strong{display:block;margin-top:4px;font:2rem Georgia,serif;color:var(--gold)}.layout{display:grid;grid-template-columns:1.15fr .85fr;gap:22px;align-items:start}.card{background:#fff;border-radius:11px;box-shadow:0 7px 18px rgba(20,33,61,.07);overflow:hidden}.card-heading{padding:22px 23px 16px;border-bottom:1px solid var(--line)}.card-heading h2{font:1.55rem Georgia,serif;margin:0}.card-heading p{margin:4px 0 0;color:var(--muted);font-size:.88rem}.card-body{padding:22px}.table-wrap{overflow-x:auto}.bookings{width:100%;border-collapse:collapse;min-width:780px}.bookings th,.bookings td{text-align:left;padding:14px 12px;border-bottom:1px solid var(--line);vertical-align:top}.bookings th{font-size:.74rem;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);background:#fafafa}.bookings td{font-size:.89rem}.bookings small{display:block;color:var(--muted);margin-top:3px}.status{display:inline-block;padding:4px 8px;border-radius:999px;font-size:.76rem;font-weight:700;text-transform:capitalize}.status-pending{background:#fff4d8;color:#865800}.status-confirmed{background:#e5f0ff;color:#225ba4}.status-cancelled{background:#ffebe8;color:#a53229}.status-completed{background:#e2f6e9;color:#14623a}.status-active{background:#e2f6e9;color:#14623a}.status-inactive{background:#f0f1f3;color:#596273}.inline-form{display:flex;gap:6px;min-width:180px}.inline-form select{padding:7px;font-size:.8rem}.inline-form .button{padding:7px 9px;font-size:.78rem}.employee-list{display:grid;gap:11px;margin-top:20px}.employee{border:1px solid var(--line);border-radius:8px;padding:12px}.employee strong{display:block}.employee span{color:var(--muted);font-size:.84rem}.empty{color:var(--muted);padding:18px 0;margin:0}@media(max-width:920px){.stats{grid-template-columns:repeat(2,1fr)}.layout{grid-template-columns:1fr}}@media(max-width:520px){.shell{width:min(100% - 24px,1260px)}.topbar-inner{min-height:64px}.page{padding-top:25px}.dashboard-head{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr}.login-card{padding:26px}.card-body{padding:17px}}
  </style>
</head>
<body>
  <header class="topbar"><div class="shell topbar-inner"><a class="brand" href="booking.php">TEKU GRAND<span>HOTEL ADMINISTRATION</span></a><?php if (adminLoggedIn()): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(adminCsrfToken()) ?>"><input type="hidden" name="action" value="logout"><button class="button button-secondary" type="submit">Sign out</button></form><?php endif; ?></div></header>
  <?php if (!adminLoggedIn()): ?><main class="shell login-wrap"><section class="login-card"><h1>Administrator sign in</h1><p>Use the hotel administrator account to manage booking requests and employees.</p><?php if ($message !== ''): ?><div class="notice notice-success" style="margin-top:18px"><?= h($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="notice notice-error" style="margin-top:18px"><?= h($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="action" value="login"><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button class="button" type="submit">Sign in securely</button></form></section></main><?php else: ?><main class="shell page"><?php if ($message !== ''): ?><div class="notice notice-success"><?= h($message) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="notice notice-error"><?= h($error) ?></div><?php endif; ?><?php if ($adminLoadError !== ''): ?><div class="notice notice-error"><?= h($adminLoadError) ?></div><?php endif; ?><div class="dashboard-head"><div><h1>Hotel administration</h1><p>Review booking orders and register hotel employees.</p></div><div class="actions"><a class="button button-secondary" href="booking.php">View guest website</a><a class="button" href="#register-employee">Register employee</a></div></div><section class="stats"><div class="stat"><span>All bookings</span><strong><?= count($bookings) ?></strong></div><div class="stat"><span>Pending review</span><strong><?= $bookingCounts['pending'] ?></strong></div><div class="stat"><span>Confirmed</span><strong><?= $bookingCounts['confirmed'] ?></strong></div><div class="stat"><span>Active employees</span><strong><?= count(array_filter($employees, static fn(array $employee): bool => ($employee['status'] ?? '') === 'active')) ?></strong></div></section><div class="layout"><section class="card"><div class="card-heading"><h2>Booking orders</h2><p>Update each guest request after checking room availability.</p></div><div class="card-body table-wrap"><?php if (!$bookings): ?><p class="empty">No booking orders have been received yet.</p><?php else: ?><table class="bookings"><thead><tr><th>Guest</th><th>Room & dates</th><th>Guests</th><th>Request</th><th>Status</th></tr></thead><tbody><?php foreach ($bookings as $booking): $room = $roomsById[(int) $booking['room_id']] ?? null; ?><tr><td><strong><?= h($booking['guest_name']) ?></strong><small><?= h($booking['guest_phone'] ?? 'No phone number') ?></small><small>Order #<?= h($booking['id']) ?></small></td><td><?= $room ? h($room['room_type']) . ' · ' . h($room['room_number']) : 'Room unavailable' ?><small><?= h($booking['check_in']) ?> to <?= h($booking['check_out']) ?></small></td><td><?= h($booking['guests_count']) ?></td><td><?= h($booking['special_request'] ?: '—') ?></td><td><span class="status status-<?= h($booking['status']) ?>"><?= h($booking['status']) ?></span><form class="inline-form" method="post"><input type="hidden" name="csrf" value="<?= h(adminCsrfToken()) ?>"><input type="hidden" name="action" value="update_booking"><input type="hidden" name="booking_id" value="<?= h($booking['id']) ?>"><select name="status" aria-label="Booking status for order <?= h($booking['id']) ?>"><?php foreach (['pending', 'confirmed', 'cancelled', 'completed'] as $status): ?><option value="<?= h($status) ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>><?= h(ucfirst($status)) ?></option><?php endforeach; ?></select><button class="button" type="submit">Save</button></form></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div></section><aside class="card" id="register-employee"><div class="card-heading"><h2>Register employee</h2><p>Employee records can only be added by an administrator.</p></div><div class="card-body"><form method="post"><input type="hidden" name="csrf" value="<?= h(adminCsrfToken()) ?>"><input type="hidden" name="action" value="add_employee"><label for="full_name">Full name</label><input id="full_name" name="full_name" required><label for="employee_email">Email address</label><input id="employee_email" name="employee_email" type="email" required><label for="phone">Phone number <span style="font-weight:400;color:#64748b">(optional)</span></label><input id="phone" name="phone" type="tel"><label for="job_title">Job title</label><input id="job_title" name="job_title" placeholder="e.g. Receptionist" required><button class="button" style="margin-top:20px;width:100%" type="submit">Register employee</button></form><div class="employee-list"><?php foreach ($employees as $employee): ?><article class="employee"><strong><?= h($employee['full_name']) ?></strong><span><?= h($employee['job_title']) ?> · <?= h($employee['email']) ?></span><div><span class="status status-<?= h($employee['status']) ?>"><?= h($employee['status']) ?></span></div></article><?php endforeach; ?></div></div></aside></div></main><?php endif; ?>
</body>
</html>
