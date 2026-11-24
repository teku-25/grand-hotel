<?php
declare(strict_types=1);

session_start();
require __DIR__ . '/supabase.php';

$confirmation = $_SESSION['booking_confirmation'] ?? null;
unset($_SESSION['booking_confirmation']);
$pageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bookingCsrfValid()) {
        $pageError = 'Your form has expired. Please refresh the page and try again.';
    } elseif (($_POST['action'] ?? '') === 'book') {
        $roomId = filter_var($_POST['room_id'] ?? null, FILTER_VALIDATE_INT);
        $checkIn = (string) ($_POST['check_in'] ?? '');
        $checkOut = (string) ($_POST['check_out'] ?? '');
        $guestCount = filter_var($_POST['guests_count'] ?? 1, FILTER_VALIDATE_INT);
        $name = trim((string) ($_POST['guest_name'] ?? ''));
        $phone = trim((string) ($_POST['guest_phone'] ?? ''));
        $checkInDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn);
        $checkOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut);
        $today = new DateTimeImmutable('today');

        if (!$roomId || !$checkInDate || !$checkOutDate || $checkInDate < $today || $checkOutDate <= $checkInDate || !$guestCount || $guestCount < 1 || $name === '' || $phone === '') {
            $pageError = 'Please enter your name, phone number, room, number of guests, and valid dates.';
        } else {
            $roomCheck = supabaseRequest('/rest/v1/rooms?select=id,room_number,room_type,capacity,price_per_night&is_active=eq.true&id=eq.' . $roomId);
            $roomInfo = $roomCheck['ok'] && is_array($roomCheck['data']) ? ($roomCheck['data'][0] ?? null) : null;
            if (!$roomInfo || $guestCount > (int) $roomInfo['capacity']) {
                $pageError = 'That room is unavailable or cannot accommodate the selected number of guests.';
            } else {
                $payload = [
                    'room_id' => $roomId,
                    'guest_id' => null,
                    'guest_name' => $name,
                    'guest_phone' => $phone,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'guests_count' => $guestCount,
                    'special_request' => trim((string) ($_POST['special_request'] ?? '')) ?: null,
                    'status' => 'pending',
                ];
                $result = supabaseRequest('/rest/v1/bookings', 'POST', $payload);
                if ($result['ok']) {
                    $savedBooking = is_array($result['data']) ? ($result['data'][0] ?? []) : [];
                    $bookingId = (int) ($savedBooking['id'] ?? 0);
                    $_SESSION['booking_confirmation'] = [
                        'reference' => $bookingId > 0 ? 'TGH-' . str_pad((string) $bookingId, 6, '0', STR_PAD_LEFT) : 'TGH-' . strtoupper(bin2hex(random_bytes(3))),
                        'guest_name' => $name,
                        'room' => (string) $roomInfo['room_type'] . ' Room #' . (string) $roomInfo['room_number'],
                        'check_in' => $checkInDate->format('F j, Y'),
                        'check_out' => $checkOutDate->format('F j, Y'),
                        'guests_count' => $guestCount,
                    ];
                    header('Location: booking.php#confirmation');
                    exit;
                }
                $pageError = str_contains(strtolower((string) $result['error']), 'exclusion')
                    ? 'This room is already reserved for those dates. Please choose different dates or another room.'
                    : 'We could not submit your booking. Please try again or contact us directly.';
            }
        }
    }
}

$roomsResult = supabaseRequest('/rest/v1/rooms?select=id,room_number,room_type,description,capacity,price_per_night&is_active=eq.true&order=room_number.asc');
$rooms = $roomsResult['ok'] && is_array($roomsResult['data']) ? $roomsResult['data'] : [];
if (!$roomsResult['ok'] && $pageError === '') {
    $pageError = 'We could not load the available rooms. Please refresh the page or contact us directly.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Book your stay at TEKU GRAND HOTEL.">
  <title>TEKU GRAND HOTEL | Book Your Stay</title>
  <style>
    :root{--ink:#14213d;--gold:#c9973b;--gold-light:#f8e7bd;--cream:#f8f6f1;--muted:#62708a;--line:#e7e3db;--success:#e5f4e9;--success-ink:#155b35;--danger:#fff0ed;--danger-ink:#a03124;--shadow:0 18px 45px rgba(20,33,61,.12)}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--cream);color:var(--ink);font-family:Arial,Helvetica,sans-serif;line-height:1.55}a{color:inherit}.container{width:min(1180px,calc(100% - 40px));margin:auto}.site-header{position:absolute;z-index:3;width:100%;color:#fff}.nav{min-height:82px;display:flex;align-items:center;justify-content:space-between;gap:24px}.brand{font-weight:800;letter-spacing:.08em;text-decoration:none;font-size:1.05rem}.brand span{display:block;color:#f2d797;font-size:.67rem;letter-spacing:.18em;font-weight:600}.nav-links{display:flex;align-items:center;gap:24px;font-size:.92rem}.nav-links a{text-decoration:none}.nav-links a:hover{color:#f2d797}.button{display:inline-flex;justify-content:center;align-items:center;gap:8px;background:var(--gold);color:#182038;border:0;border-radius:7px;padding:13px 19px;font:700 .92rem Arial,sans-serif;cursor:pointer;text-decoration:none;transition:transform .18s,background .18s}.button:hover{background:#e0b45e;transform:translateY(-2px)}.button-light{background:#fff;color:var(--ink)}.button-light:hover{background:#f7e6b9}.button-outline{background:transparent;border:1px solid #fff;color:#fff}.button-outline:hover{background:#fff;color:var(--ink)}
    .hero{min-height:650px;display:grid;align-items:end;color:#fff;background:linear-gradient(90deg,rgba(11,23,43,.88) 0%,rgba(11,23,43,.6) 44%,rgba(11,23,43,.22) 100%),url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=2000&q=85') center/cover}.hero-content{padding:170px 0 95px;max-width:710px}.eyebrow{margin:0 0 13px;color:#f0cf84;font-size:.75rem;font-weight:800;letter-spacing:.2em;text-transform:uppercase}.hero h1{font-family:Georgia,serif;font-size:clamp(2.8rem,6vw,5.3rem);line-height:1.03;margin:0 0 20px;letter-spacing:-.04em}.hero p:not(.eyebrow){font-size:1.1rem;max-width:570px;color:#eef1f7;margin:0 0 28px}.hero-actions{display:flex;flex-wrap:wrap;gap:12px}.stats{position:relative;z-index:2;margin-top:-42px;display:grid;grid-template-columns:repeat(3,1fr);background:#fff;border-radius:12px;box-shadow:var(--shadow);overflow:hidden}.stat{padding:22px 28px;border-right:1px solid var(--line)}.stat:last-child{border:0}.stat strong{display:block;font-family:Georgia,serif;color:var(--gold);font-size:1.5rem}.stat span{font-size:.85rem;color:var(--muted)}
    section{scroll-margin-top:24px}.section{padding:82px 0}.section-heading{max-width:650px;margin-bottom:32px}.section-heading .eyebrow{color:var(--gold)}h2{font-family:Georgia,serif;font-size:clamp(2rem,4vw,3.2rem);line-height:1.12;margin:0 0 13px;letter-spacing:-.025em}.section-heading p{margin:0;color:var(--muted)}.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:44px;align-items:center}.about-image{min-height:440px;border-radius:14px;background:url('https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=1200&q=85') center/cover;box-shadow:var(--shadow)}.about-copy p{color:var(--muted);font-size:1.04rem}.feature-list{display:grid;gap:16px;margin-top:26px}.feature{display:flex;gap:14px;align-items:flex-start}.feature-icon{display:grid;place-items:center;flex:none;width:42px;height:42px;border-radius:50%;background:var(--gold-light);color:#76500d;font-weight:800}.feature strong{display:block}.feature span{font-size:.92rem;color:var(--muted)}
    .rooms-section{background:#ece7dc}.rooms{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}.room-card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 9px 22px rgba(25,37,59,.07);border-top:4px solid transparent}.room-card:hover{border-top-color:var(--gold)}.room-card h3{font-family:Georgia,serif;font-size:1.55rem;margin:0 0 9px}.room-card p{min-height:50px;color:var(--muted);margin:0 0 16px}.room-meta{padding:13px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);color:var(--muted);font-size:.91rem}.price{margin-top:17px;color:var(--ink);font-weight:800;font-size:1.12rem}.price span{color:var(--muted);font-weight:400;font-size:.85rem}.empty{background:#fff;border-radius:10px;padding:25px;color:var(--muted)}
    .booking-layout{display:grid;grid-template-columns:.85fr 1.15fr;gap:26px;align-items:start}.booking-intro{min-height:100%;padding:38px;border-radius:12px;color:#fff;background:linear-gradient(rgba(15,31,57,.75),rgba(15,31,57,.88)),url('https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=85') center/cover}.booking-intro h2{font-size:2.45rem}.booking-intro p{color:#eff3fa}.contact-links{display:grid;gap:12px;margin-top:30px}.contact-link{display:block;color:#fff;text-decoration:none;border:1px solid rgba(255,255,255,.35);padding:13px;border-radius:7px}.contact-link:hover{background:rgba(255,255,255,.12)}.contact-link span{display:block;font-size:.77rem;color:#f3d694;text-transform:uppercase;letter-spacing:.1em}.booking-card{background:#fff;padding:35px;border-radius:12px;box-shadow:var(--shadow)}.booking-card h3{font-family:Georgia,serif;font-size:1.8rem;margin:0 0 5px}.booking-card>p{margin:0;color:var(--muted);font-size:.93rem}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}label{display:block;margin:17px 0 6px;font-size:.86rem;font-weight:700}input,select,textarea{width:100%;border:1px solid #d8dce3;border-radius:7px;padding:12px;font:inherit;color:var(--ink);background:#fff;transition:border-color .15s,box-shadow .15s}input:focus,select:focus,textarea:focus{outline:0;border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,151,59,.16)}textarea{min-height:85px;resize:vertical}.full{grid-column:1/-1}.form-note{margin:13px 0 0;font-size:.82rem;color:var(--muted)}.notice{padding:16px 18px;border-radius:9px;margin:28px auto -38px;position:relative;z-index:4;width:min(1140px,calc(100% - 40px));border:1px solid}.notice-error{background:var(--danger);color:var(--danger-ink);border-color:#f5cfc8}.confirmation{margin:45px auto 0;max-width:760px;background:var(--success);border:1px solid #b9dfc2;border-radius:14px;padding:32px;color:var(--success-ink);box-shadow:var(--shadow)}.confirmation h2{font-size:2rem}.confirmation p{margin:7px 0}.confirmation-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin:20px 0}.confirmation-item{border-radius:8px;background:rgba(255,255,255,.65);padding:12px}.confirmation-item span{display:block;font-size:.73rem;text-transform:uppercase;letter-spacing:.09em}.confirmation-item strong{display:block;color:#123c25}.confirmation .reference{font-size:1.1rem;font-weight:800}.gallery-credit{margin-top:16px;color:#8a8173;font-size:.76rem}.gallery-credit a{color:inherit}.site-footer{padding:55px 0 24px;background:var(--ink);color:#d7deeb}.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:28px}.site-footer h3{margin:0 0 11px;color:#fff;font-family:Georgia,serif}.site-footer p,.site-footer a{font-size:.9rem;color:#c1cada}.site-footer a{text-decoration:none;display:block;margin:7px 0}.footer-bottom{margin-top:34px;padding-top:18px;border-top:1px solid #3a4b69;color:#aab5c7;font-size:.8rem}
    @media(max-width:800px){.nav-links a:not(.button){display:none}.hero{min-height:580px}.hero-content{padding:145px 0 75px}.stats{grid-template-columns:1fr;margin-top:-18px}.stat{padding:15px;border-bottom:1px solid var(--line);border-right:0}.stat:last-child{border-bottom:0}.section{padding:62px 0}.about-grid,.booking-layout{grid-template-columns:1fr}.about-image{min-height:310px}.rooms{grid-template-columns:1fr}.booking-intro{min-height:360px}.footer-grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.container{width:min(100% - 30px,1180px)}.nav{min-height:72px}.hero h1{font-size:2.75rem}.hero-content{padding-top:128px}.form-grid,.confirmation-grid,.footer-grid{grid-template-columns:1fr}.booking-card,.booking-intro{padding:25px}.notice{width:calc(100% - 30px)}}
  </style>
</head>
<body>
  <header class="site-header">
    <div class="container nav">
      <a class="brand" href="#top">TEKU GRAND<span>HOTEL</span></a>
      <nav class="nav-links" aria-label="Main navigation">
        <a href="#about">About us</a><a href="#rooms">Rooms</a><a href="#contact">Contact</a>
        <a class="button button-light" href="#booking">Book your stay</a>
      </nav>
    </div>
  </header>

  <?php if ($pageError !== ''): ?><div class="notice notice-error" role="alert"><?= h($pageError) ?></div><?php endif; ?>

  <main id="top">
    <section class="hero">
      <div class="container hero-content">
        <p class="eyebrow">Comfort · Care · Convenience</p>
        <h1>A stay you will remember.</h1>
        <p>Welcome to TEKU GRAND HOTEL. Rest in comfort, enjoy attentive service, and make every visit feel special.</p>
        <div class="hero-actions"><a class="button" href="#booking">Reserve a room</a><a class="button button-outline" href="#rooms">Explore our rooms</a></div>
      </div>
    </section>

    <div class="container stats" aria-label="Hotel highlights"><div class="stat"><strong>3 room types</strong><span>Choose the space that suits you</span></div><div class="stat"><strong>Easy booking</strong><span>Reserve in just a few steps</span></div><div class="stat"><strong>Personal support</strong><span>Call or message us any time</span></div></div>

    <?php if (is_array($confirmation)): ?>
      <section class="confirmation" id="confirmation" aria-live="polite">
        <p class="eyebrow">Booking received</p><h2>Thank you, <?= h($confirmation['guest_name'] ?? '') ?>.</h2>
        <p>Your booking request has been registered successfully. Our team will contact you to confirm it.</p>
        <p class="reference">Booking reference: <?= h($confirmation['reference'] ?? '') ?></p>
        <div class="confirmation-grid"><div class="confirmation-item"><span>Room</span><strong><?= h($confirmation['room'] ?? '') ?></strong></div><div class="confirmation-item"><span>Guests</span><strong><?= h($confirmation['guests_count'] ?? '') ?></strong></div><div class="confirmation-item"><span>Check-in</span><strong><?= h($confirmation['check_in'] ?? '') ?></strong></div><div class="confirmation-item"><span>Check-out</span><strong><?= h($confirmation['check_out'] ?? '') ?></strong></div></div>
        <p>Please keep your booking reference. For help, call <a href="tel:+251975348494">0975348494</a> or message <a href="https://t.me/teku_25" target="_blank" rel="noopener">@teku_25 on Telegram</a>.</p>
      </section>
    <?php endif; ?>

    <section class="section" id="about"><div class="container about-grid"><div class="about-image" role="img" aria-label="A bright and comfortable hotel room"></div><div class="about-copy"><p class="eyebrow">About TEKU GRAND HOTEL</p><h2>Made for restful days and easy arrivals.</h2><p>Whether you are travelling for business, visiting family, or simply taking a break, our rooms provide a clean, comfortable space to relax.</p><div class="feature-list"><div class="feature"><div class="feature-icon">01</div><div><strong>Comfortable rooms</strong><span>Choose from Standard, Deluxe, and Family rooms.</span></div></div><div class="feature"><div class="feature-icon">02</div><div><strong>Simple reservations</strong><span>Submit your request online without creating an account.</span></div></div><div class="feature"><div class="feature-icon">03</div><div><strong>Direct assistance</strong><span>Our team is only a call or Telegram message away.</span></div></div></div></div></div></section>

    <section class="section rooms-section" id="rooms"><div class="container"><div class="section-heading"><p class="eyebrow">Stay your way</p><h2>Rooms and rates</h2><p>All prices shown are per night. Select your preferred room when completing your reservation.</p></div><?php if (!$rooms): ?><p class="empty">There are no rooms available to display at the moment. Please contact us for assistance.</p><?php else: ?><div class="rooms"><?php foreach ($rooms as $room): ?><article class="room-card"><h3><?= h($room['room_type']) ?></h3><p><?= h($room['description'] ?: 'A comfortable room prepared for your stay.') ?></p><div class="room-meta">Room <?= h($room['room_number']) ?> · Up to <?= h($room['capacity']) ?> guest<?= (int) $room['capacity'] === 1 ? '' : 's' ?></div><div class="price">ETB <?= number_format((float) $room['price_per_night'], 2) ?> <span>/ night</span></div></article><?php endforeach; ?></div><?php endif; ?></div></section>

    <section class="section" id="booking"><div class="container booking-layout"><aside class="booking-intro" id="contact"><p class="eyebrow">We are here to help</p><h2>Ready to reserve?</h2><p>Send your booking request below. We will review it and contact you to confirm the room and dates.</p><div class="contact-links"><a class="contact-link" href="tel:+251975348494"><span>Call us</span>0975348494</a><a class="contact-link" href="https://t.me/teku_25" target="_blank" rel="noopener"><span>Telegram</span>@teku_25</a></div></aside><div class="booking-card"><h3>Book your stay</h3><p>Fields marked by the browser as required must be completed.</p><?php if (!$rooms): ?><p class="empty">Please contact us directly to make a reservation.</p><?php else: ?><form method="post"><input type="hidden" name="csrf" value="<?= h(bookingCsrfToken()) ?>"><input type="hidden" name="action" value="book"><div class="form-grid"><div class="full"><label for="room_id">Preferred room</label><select id="room_id" name="room_id" required><option value="">Select a room</option><?php foreach ($rooms as $room): ?><option value="<?= h($room['id']) ?>"><?= h($room['room_type']) ?> — Room <?= h($room['room_number']) ?> · ETB <?= number_format((float) $room['price_per_night'], 2) ?>/night</option><?php endforeach; ?></select></div><div><label for="guest_name">Full name</label><input id="guest_name" name="guest_name" autocomplete="name" required></div><div><label for="guest_phone">Phone number</label><input id="guest_phone" name="guest_phone" type="tel" autocomplete="tel" required></div><div><label for="check_in">Check-in date</label><input id="check_in" type="date" name="check_in" min="<?= h(date('Y-m-d')) ?>" required></div><div><label for="check_out">Check-out date</label><input id="check_out" type="date" name="check_out" min="<?= h(date('Y-m-d', strtotime('+1 day'))) ?>" required></div><div><label for="guests_count">Number of guests</label><input id="guests_count" type="number" name="guests_count" min="1" value="1" required></div><div class="full"><label for="special_request">Special request <span style="font-weight:400;color:#62708a">(optional)</span></label><textarea id="special_request" name="special_request" placeholder="Tell us anything that will make your stay more comfortable."></textarea></div></div><button class="button" type="submit">Submit booking request</button><p class="form-note">You will see a booking receipt after successful registration. Final confirmation is provided by our hotel team.</p></form><?php endif; ?></div></div></section>
  </main>

  <footer class="site-footer"><div class="container footer-grid"><div><h3>TEKU GRAND HOTEL</h3><p>Comfortable rooms, thoughtful service, and an easy way to reserve your stay.</p></div><div><h3>Explore</h3><a href="#about">About us</a><a href="#rooms">Rooms and rates</a><a href="#booking">Book your stay</a></div><div><h3>Contact</h3><a href="tel:+251975348494">Phone: 0975348494</a><a href="https://t.me/teku_25" target="_blank" rel="noopener">Telegram: @teku_25</a></div></div><div class="container footer-bottom">© <?= date('Y') ?> TEKU GRAND HOTEL. All rights reserved. <span class="gallery-credit">Hotel imagery: Unsplash.</span></div></footer>
  <script>
    const checkIn = document.getElementById('check_in');
    const checkOut = document.getElementById('check_out');
    if (checkIn && checkOut) {
      checkIn.addEventListener('change', () => {
        const nextDay = new Date(`${checkIn.value}T00:00:00`);
        nextDay.setDate(nextDay.getDate() + 1);
        if (!Number.isNaN(nextDay.getTime())) {
          const minCheckOut = nextDay.toISOString().slice(0, 10);
          checkOut.min = minCheckOut;
          if (checkOut.value && checkOut.value < minCheckOut) checkOut.value = minCheckOut;
        }
      });
    }
  </script>
</body>
</html>
