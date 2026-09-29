<?php
/* ===========================================================
   JJ Entertainments — booking confirmation sender (admin)
   -----------------------------------------------------------
   A private page for sending a customer a smart booking
   confirmation email. Fill in the booking, check the preview,
   press Send. A copy is also emailed to you, so your inbox is
   the record of every confirmation sent.

   Protected by a password whose hash lives in config.local.php
   (server only, never in the public repo). The first time you
   open this page it helps you create that line.
   =========================================================== */

// Same server-only settings file that contact.php uses.
$config = is_file(__DIR__ . '/config.local.php')
    ? include __DIR__ . '/config.local.php'
    : array();

$RECIPIENT     = $config['recipient']           ?? 'your-email@example.com';
$FROM_ADDRESS  = $config['from']                ?? 'bookings@yourdomain.com';
$FROM_NAME     = $config['from_name']           ?? 'JJ Entertainments';
$PASSWORD_HASH = $config['admin_password_hash'] ?? '';

// Public contact details shown in the confirmation email.
$PUBLIC_EMAIL = 'bookings@jjentertainments.co.uk';
$PUBLIC_PHONE = '07818 074565';
$SITE_URL     = 'https://jjentertainments.co.uk';

$EVENT_TYPES = array('Birthday party', 'School fair / fête', 'Wedding',
                     'Community event', 'Corporate event', 'Other');

// -----------------------------------------------------------
// You normally don't need to change anything below this line.
// -----------------------------------------------------------

// Keep this page out of search engines and out of caches.
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(array(
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Strict',
));
session_name('jj_admin');
session_start();

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// --- Helpers ---------------------------------------------------------------

// Escape for HTML output.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Clean a one-line value and strip header-injection attempts (as contact.php).
function clean($value) {
    $value = isset($value) ? trim($value) : '';
    $value = str_replace(array("\r", "\n", "%0a", "%0d"), ' ', $value);
    return $value;
}

// Multi-line text: keep line breaks, drop carriage returns.
function clean_multiline($value) {
    return trim(str_replace("\r", '', (string) $value));
}

// Encode a header value (subject) so £, – and emoji survive every mail client.
function encode_header($text) {
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

// "2026-10-18" -> "Saturday 18 October 2026"
function nice_date($ymd) {
    $d = DateTime::createFromFormat('!Y-m-d', $ymd);
    return $d ? $d->format('l j F Y') : $ymd;
}

// "14:00" -> "2pm", "14:30" -> "2:30pm"
function nice_time($hm) {
    $t = DateTime::createFromFormat('H:i', $hm);
    if (!$t) { return $hm; }
    return $t->format('i') === '00' ? $t->format('ga') : $t->format('g:ia');
}

// £20 per 10 people, rounded up to the next 10.
function price_for($guests) {
    $guests = (int) $guests;
    return $guests > 0 ? (int) ceil($guests / 10) * 20 : 0;
}

function csrf_ok() {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

// --- Booking fields --------------------------------------------------------

function read_booking($src) {
    $b = array(
        'name'       => clean($src['name']       ?? ''),
        'email'      => clean($src['email']      ?? ''),
        'phone'      => clean($src['phone']      ?? ''),
        'event_date' => clean($src['event_date'] ?? ''),
        'start_time' => clean($src['start_time'] ?? ''),
        'end_time'   => clean($src['end_time']   ?? ''),
        'event_type' => clean($src['event_type'] ?? ''),
        'venue'      => clean($src['venue']      ?? ''),
        'guests'     => clean($src['guests']     ?? ''),
        'total'      => clean($src['total']      ?? ''),
        'payment'    => clean($src['payment']    ?? ''),
        'note'       => clean_multiline($src['note'] ?? ''),
    );
    // Accept "£60", "60", "60.00".
    $b['total'] = ltrim(str_replace(array('£', ','), '', $b['total']));
    if ($b['total'] === '' && $b['guests'] !== '') {
        $b['total'] = (string) price_for($b['guests']);
    }
    return $b;
}

function blank_booking() {
    $b = read_booking(array());
    $b['payment'] = 'Cash or bank transfer on the day';
    return $b;
}

function validate_booking($b) {
    $errors = array();
    if ($b['name'] === '') {
        $errors['name'] = 'Please enter the customer’s name.';
    }
    if (!filter_var($b['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (!DateTime::createFromFormat('!Y-m-d', $b['event_date'])) {
        $errors['event_date'] = 'Please choose the event date.';
    }
    foreach (array('start_time', 'end_time') as $f) {
        if ($b[$f] !== '' && !DateTime::createFromFormat('H:i', $b[$f])) {
            $errors[$f] = 'Please use a time like 14:00.';
        }
    }
    if ($b['guests'] !== '' && !ctype_digit($b['guests'])) {
        $errors['guests'] = 'Please enter a whole number of guests.';
    }
    if ($b['total'] !== '' && !is_numeric($b['total'])) {
        $errors['total'] = 'Please enter the total as a number, e.g. 60.';
    }
    return $errors;
}

function money($amount) {
    $amount = (float) $amount;
    return '£' . (floor($amount) == $amount ? number_format($amount) : number_format($amount, 2));
}

// Rows for the summary table, skipping anything not filled in.
function booking_rows($b) {
    $rows = array();
    $rows['Date'] = nice_date($b['event_date']);
    if ($b['start_time'] !== '') {
        $rows['Time'] = nice_time($b['start_time'])
            . ($b['end_time'] !== '' ? ' – ' . nice_time($b['end_time']) : '');
    }
    if ($b['venue'] !== '')      { $rows['Venue']  = $b['venue']; }
    if ($b['event_type'] !== '') { $rows['Event']  = $b['event_type']; }
    if ($b['guests'] !== '')     { $rows['Guests'] = $b['guests']; }
    if ($b['total'] !== '')      { $rows['Total']  = money($b['total']); }
    if ($b['payment'] !== '')    { $rows['Payment'] = $b['payment']; }
    return $rows;
}

function email_subject($b) {
    return 'Your candy floss booking is confirmed – ' . nice_date($b['event_date']);
}

function first_name($name) {
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] !== '' ? $parts[0] : $name;
}

// --- The confirmation email ------------------------------------------------

function email_html($b, $copy_banner = '') {
    global $PUBLIC_EMAIL, $PUBLIC_PHONE, $SITE_URL;

    $rows = '';
    foreach (booking_rows($b) as $label => $value) {
        $strong = $label === 'Total';
        $rows .= '<tr>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee4f0;color:#7a7088;font-size:14px;width:34%;vertical-align:top;">' . e($label) . '</td>'
            . '<td style="padding:10px 0;border-bottom:1px solid #eee4f0;color:#1d1d26;font-size:15px;vertical-align:top;' . ($strong ? 'font-weight:800;color:#b3186b;font-size:17px;' : 'font-weight:600;') . '">' . e($value) . '</td>'
            . '</tr>';
    }

    $note = $b['note'] !== ''
        ? '<p style="margin:0 0 18px;padding:14px 16px;background:#fdf1f8;border-left:4px solid #ec1c8e;border-radius:6px;color:#1d1d26;font-size:15px;line-height:1.6;">' . nl2br(e($b['note'])) . '</p>'
        : '';

    $banner = $copy_banner !== ''
        ? '<tr><td style="padding:10px 24px;background:#fff6dc;color:#6b4d0f;font-size:13px;font-family:Arial,Helvetica,sans-serif;">' . e($copy_banner) . '</td></tr>'
        : '';

    $tel = preg_replace('/\D/', '', $PUBLIC_PHONE);
    $tel = '+44' . ltrim($tel, '0');

    return '<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . e(email_subject($b)) . '</title></head>
<body style="margin:0;padding:0;background:#f4f0f7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f0f7;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;">
' . $banner . '
<tr><td align="center" style="background:#0a0a0c;padding:26px 24px 20px;">
  <img src="' . e($SITE_URL) . '/images/logo.png" width="120" height="120" alt="JJ Entertainments" style="display:block;border:0;width:120px;height:120px;">
</td></tr>
<tr><td style="height:6px;line-height:6px;font-size:0;background:#ec1c8e;background-image:linear-gradient(90deg,#3fa9e0,#8b63d6,#ec1c8e,#f9a826);">&nbsp;</td></tr>
<tr><td style="padding:30px 28px 8px;">
  <h1 style="margin:0 0 12px;font-size:26px;line-height:1.2;color:#1d1d26;">You’re booked in! 🍭</h1>
  <p style="margin:0 0 18px;color:#3a3446;font-size:16px;line-height:1.6;">Hi ' . e(first_name($b['name'])) . ',</p>
  <p style="margin:0 0 18px;color:#3a3446;font-size:16px;line-height:1.6;">Thank you for booking JJ Entertainments. This email confirms your candy floss booking. Here are the details:</p>
  ' . $note . '
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">' . $rows . '</table>

  <h2 style="margin:0 0 8px;font-size:18px;color:#1d1d26;">What’s included</h2>
  <ul style="margin:0 0 22px;padding-left:20px;color:#3a3446;font-size:15px;line-height:1.7;">
    <li>A friendly operator for your event</li>
    <li>A freshly spun candy floss for every guest</li>
    <li>Blue raspberry, strawberry or vanilla</li>
    <li>Full setup and cleanup</li>
  </ul>

  <h2 style="margin:0 0 8px;font-size:18px;color:#1d1d26;">What we need from you</h2>
  <ul style="margin:0 0 22px;padding-left:20px;color:#3a3446;font-size:15px;line-height:1.7;">
    <li>A standard power socket near where the machine will go</li>
    <li>If we’re outdoors, some cover from the rain (a gazebo or porch is perfect)</li>
  </ul>

  <p style="margin:0 0 18px;color:#3a3446;font-size:16px;line-height:1.6;">If anything changes, or you have any questions, just reply to this email or give us a ring.</p>
  <p style="margin:0 0 28px;color:#3a3446;font-size:16px;line-height:1.6;">We can’t wait to see you!<br><strong>JJ Entertainments</strong></p>
</td></tr>
<tr><td style="padding:20px 28px;background:#15151b;color:#c4bfd0;font-size:13px;line-height:1.7;">
  <strong style="color:#ffffff;">JJ Entertainments</strong> · Candy floss machine hire, Mumbles, Swansea<br>
  <a href="tel:' . e($tel) . '" style="color:#f9a826;text-decoration:none;">' . e($PUBLIC_PHONE) . '</a> ·
  <a href="mailto:' . e($PUBLIC_EMAIL) . '" style="color:#3fa9e0;text-decoration:none;">' . e($PUBLIC_EMAIL) . '</a> ·
  <a href="' . e($SITE_URL) . '" style="color:#ec1c8e;text-decoration:none;">jjentertainments.co.uk</a>
</td></tr>
</table>
</td></tr>
</table>
</body></html>';
}

function email_text($b, $copy_banner = '') {
    global $PUBLIC_EMAIL, $PUBLIC_PHONE, $SITE_URL;

    $t = $copy_banner !== '' ? $copy_banner . "\n\n" : '';
    $t .= 'Hi ' . first_name($b['name']) . ",\n\n";
    $t .= "Thank you for booking JJ Entertainments. This email confirms your candy floss booking.\n\n";
    if ($b['note'] !== '') {
        $t .= $b['note'] . "\n\n";
    }
    $t .= "YOUR BOOKING\n";
    foreach (booking_rows($b) as $label => $value) {
        $t .= str_pad($label . ':', 10) . $value . "\n";
    }
    $t .= "\nWHAT'S INCLUDED\n";
    $t .= "- A friendly operator for your event\n";
    $t .= "- A freshly spun candy floss for every guest\n";
    $t .= "- Blue raspberry, strawberry or vanilla\n";
    $t .= "- Full setup and cleanup\n";
    $t .= "\nWHAT WE NEED FROM YOU\n";
    $t .= "- A standard power socket near where the machine will go\n";
    $t .= "- If we're outdoors, some cover from the rain (a gazebo or porch is perfect)\n";
    $t .= "\nIf anything changes, or you have any questions, just reply to this email or give us a ring.\n\n";
    $t .= "We can't wait to see you!\nJJ Entertainments\n\n";
    $t .= "--\n$PUBLIC_PHONE | $PUBLIC_EMAIL | $SITE_URL\n";
    return $t;
}

// Send one HTML + plain-text email. Returns true if the mail server accepted it.
function send_email($to, $subject, $html, $text) {
    global $FROM_ADDRESS, $FROM_NAME;

    $boundary = 'jj-' . bin2hex(random_bytes(12));

    $headers  = 'From: ' . encode_header($FROM_NAME) . ' <' . $FROM_ADDRESS . ">\r\n";
    $headers .= 'Reply-To: ' . $FROM_ADDRESS . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--$boundary--\r\n";

    return @mail($to, encode_header($subject), $body, $headers);
}

// --- Handle the request ----------------------------------------------------

$view    = 'form';          // setup | login | form | preview | sent
$flash   = '';              // message shown at the top
$flashOk = false;
$errors  = array();
$booking = blank_booking();
$setupLine = '';

$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? '') : '';
$loggedIn = !empty($_SESSION['admin']);

if ($action !== '' && !csrf_ok()) {
    $flash  = 'That form had expired. Please try again.';
    $action = '';
}

if ($PASSWORD_HASH === '') {
    // --- First-time setup: help create the password hash line. ---
    $view = 'setup';
    if ($action === 'setup') {
        $pw  = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if (strlen($pw) < 10) {
            $flash = 'Please use at least 10 characters.';
        } elseif ($pw !== $pw2) {
            $flash = 'The two passwords don’t match.';
        } else {
            $setupLine = "    'admin_password_hash' => '" . password_hash($pw, PASSWORD_DEFAULT) . "',";
        }
    }
} elseif ($action === 'login') {
    if (password_verify((string) ($_POST['password'] ?? ''), $PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $loggedIn = true;
    } else {
        sleep(1); // slow down guessing
        $flash = 'Wrong password.';
    }
    $view = $loggedIn ? 'form' : 'login';
} elseif ($action === 'logout') {
    $_SESSION = array();
    session_destroy();
    header('Location: admin.php');
    exit;
} elseif (!$loggedIn) {
    $view = 'login';
} elseif ($action === 'preview' || $action === 'send') {
    $booking = read_booking($_POST);
    $errors  = validate_booking($booking);
    if (!empty($errors)) {
        $view  = 'form';
        $flash = 'Please check the highlighted fields.';
    } elseif ($action === 'preview') {
        $view = 'preview';
    } else {
        $subject = email_subject($booking);
        $sent = send_email($booking['email'], $subject,
            email_html($booking), email_text($booking));
        if ($sent) {
            $copy = 'Copy of the confirmation sent to ' . $booking['name'] . ' <' . $booking['email'] . '>'
                . ($booking['phone'] !== '' ? ', phone ' . $booking['phone'] : '') . '.';
            $copySent = send_email($RECIPIENT, '[Copy] ' . $subject,
                email_html($booking, $copy), email_text($booking, $copy));
            $view    = 'sent';
            $flashOk = true;
            $flash   = 'Confirmation sent to ' . $booking['email'] . '.'
                . ($copySent ? ' A copy has gone to your inbox.' : ' (The copy to your inbox could not be sent.)');
        } else {
            $view  = 'form';
            $flash = 'The email could not be sent. Nothing has gone to the customer. Your details are still below — please try again, or email them yourself.';
        }
    }
} elseif ($action === 'edit') {
    $booking = read_booking($_POST);
}

// Hidden inputs that carry a booking through the preview step.
function hidden_booking($b) {
    $out = '';
    foreach ($b as $k => $v) {
        $out .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $out;
}

function field_error($errors, $name) {
    return isset($errors[$name]) ? '<span class="err">' . e($errors[$name]) . '</span>' : '';
}

function err_class($errors, $name) {
    return isset($errors[$name]) ? ' has-error' : '';
}

$csrfInput = '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Send a booking confirmation · JJ Entertainments</title>
  <link rel="icon" type="image/png" href="images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@600;800&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --pink: #ec1c8e; --pink-deep: #b3186b; --yellow: #f9a826; --blue: #3fa9e0;
      --ink-bg: #0a0a0c; --panel: #15151b; --panel-2: #1d1d26; --line: #2c2c38;
      --text: #ffffff; --muted: #c4bfd0; --ok: #3ccf8e; --bad: #ff6b8a;
      --ring: linear-gradient(90deg, #3fa9e0, #8b63d6, #ec1c8e, #f9a826);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; font-family: 'Nunito', system-ui, sans-serif; color: var(--text);
      background: var(--ink-bg); line-height: 1.55; -webkit-font-smoothing: antialiased;
    }
    h1, h2 { font-family: 'Baloo 2', 'Nunito', sans-serif; font-weight: 800; line-height: 1.15; margin: 0 0 .4em; }
    .bar { height: 5px; background: var(--ring); }
    header { display: flex; align-items: center; gap: 12px; justify-content: space-between;
             max-width: 760px; margin: 0 auto; padding: 16px; }
    header a.brand { display: flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none;
                     font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 1.1rem; }
    header img { width: 44px; height: 44px; }
    main { max-width: 760px; margin: 0 auto; padding: 0 16px 48px; }
    .card { background: var(--panel); border: 1px solid var(--line); border-radius: 18px; padding: 22px; }
    .lead { color: var(--muted); margin: 0 0 18px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 16px; }
    .full { grid-column: 1 / -1; }
    @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } .card { padding: 18px; } }
    fieldset { border: 0; padding: 0; margin: 0 0 22px; }
    legend { font-family: 'Baloo 2', sans-serif; font-weight: 800; font-size: 1.15rem; margin-bottom: 8px; color: var(--yellow); }
    label { display: block; font-weight: 700; font-size: .92rem; margin-bottom: 5px; }
    label .opt { color: var(--muted); font-weight: 400; }
    input, select, textarea {
      width: 100%; font: inherit; color: var(--text); background: var(--panel-2);
      border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; min-height: 44px;
    }
    input[type="date"], input[type="time"] { color-scheme: dark; }
    textarea { min-height: 90px; resize: vertical; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: var(--pink); box-shadow: 0 0 0 4px rgba(236,28,142,.18); }
    .has-error input, .has-error select, .has-error textarea { border-color: var(--bad); }
    .err { display: block; color: var(--bad); font-size: .85rem; margin-top: 4px; }
    .hint { display: block; color: var(--muted); font-size: .85rem; margin-top: 4px; }
    .prefix { position: relative; }
    .prefix span { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .prefix input { padding-left: 26px; }
    .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; font: inherit; font-weight: 800;
           border-radius: 999px; padding: 11px 22px; min-height: 44px; cursor: pointer; border: 2px solid transparent;
           text-decoration: none; }
    .btn-primary { background: var(--pink); color: #fff; }
    .btn-primary:hover { background: var(--pink-deep); }
    .btn-ghost { background: transparent; color: var(--text); border-color: var(--line); }
    .btn-ghost:hover { border-color: var(--muted); }
    .btn-link { background: none; border: 0; color: var(--muted); cursor: pointer; font: inherit; text-decoration: underline; padding: 6px; }
    .flash { border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; font-weight: 700;
             background: rgba(255,107,138,.12); border: 1px solid rgba(255,107,138,.45); color: #ffd1dc; }
    .flash.ok { background: rgba(60,207,142,.12); border-color: rgba(60,207,142,.45); color: #c9f5e1; }
    .meta { display: grid; grid-template-columns: auto 1fr; gap: 4px 14px; margin: 0 0 16px; font-size: .95rem; }
    .meta dt { color: var(--muted); }
    .meta dd { margin: 0; word-break: break-word; }
    iframe.preview { width: 100%; height: 900px; border: 1px solid var(--line); border-radius: 14px; background: #f4f0f7; display: block; margin-bottom: 16px; }
    pre.line { white-space: pre-wrap; word-break: break-all; background: var(--panel-2); border: 1px solid var(--line);
               border-radius: 10px; padding: 12px; font-size: .85rem; user-select: all; }
    ol { padding-left: 20px; }
    ol li { margin-bottom: 6px; }
    code { background: var(--panel-2); padding: 1px 6px; border-radius: 6px; }
  </style>
</head>
<body>
<div class="bar"></div>
<header>
  <a class="brand" href="index.html"><img src="images/logo.png" alt="">JJ Entertainments</a>
  <?php if ($loggedIn && $view !== 'setup'): ?>
    <form method="post"><?= $csrfInput ?><button class="btn-link" name="action" value="logout">Log out</button></form>
  <?php endif; ?>
</header>

<main>
<?php if ($flash !== ''): ?>
  <div class="flash<?= $flashOk ? ' ok' : '' ?>" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<?php if ($view === 'setup'): ?>
  <div class="card">
    <h1>Set up your admin password</h1>
    <?php if ($setupLine === ''): ?>
      <p class="lead">This page needs a password before it can be used. Choose one below (at least 10 characters). You’ll get a line to paste into <code>config.local.php</code> on the server. Your actual password is never stored.</p>
      <form method="post" autocomplete="off">
        <?= $csrfInput ?>
        <div class="grid">
          <div><label for="password">New password</label><input type="password" id="password" name="password" required minlength="10" autocomplete="new-password"></div>
          <div><label for="password2">Type it again</label><input type="password" id="password2" name="password2" required minlength="10" autocomplete="new-password"></div>
        </div>
        <div class="actions"><button class="btn btn-primary" name="action" value="setup">Create my line</button></div>
      </form>
    <?php else: ?>
      <p class="lead">Nearly there. Copy this line:</p>
      <pre class="line"><?= e($setupLine) ?></pre>
      <ol>
        <li>In cPanel, open <strong>File Manager</strong> → the <code>jjentertainments.co.uk</code> folder → <strong>config.local.php</strong> → <strong>Edit</strong>.</li>
        <li>Paste the line on a new line just above the closing <code>);</code>, then save.</li>
        <li>Reload this page and log in with your password.</li>
      </ol>
    <?php endif; ?>
  </div>

<?php elseif ($view === 'login'): ?>
  <div class="card" style="max-width:420px;margin:24px auto 0;">
    <h1>Admin log in</h1>
    <p class="lead">Send booking confirmations to customers.</p>
    <form method="post">
      <?= $csrfInput ?>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
      <div class="actions"><button class="btn btn-primary" name="action" value="login">Log in</button></div>
    </form>
  </div>

<?php elseif ($view === 'preview'): ?>
  <h1>Check the email</h1>
  <p class="lead">This is exactly what the customer will receive. Nothing has been sent yet.</p>
  <div class="card" style="margin-bottom:16px;">
    <dl class="meta">
      <dt>To</dt><dd><?= e($booking['name']) ?> &lt;<?= e($booking['email']) ?>&gt;</dd>
      <dt>Subject</dt><dd><?= e(email_subject($booking)) ?></dd>
      <dt>Copy to</dt><dd>You (<?= e($RECIPIENT) ?>)</dd>
    </dl>
    <form method="post" class="actions">
      <?= $csrfInput ?>
      <?= hidden_booking($booking) ?>
      <button class="btn btn-primary" name="action" value="send">Send confirmation ✉️</button>
      <button class="btn btn-ghost" name="action" value="edit">← Edit details</button>
    </form>
  </div>
  <iframe class="preview" id="preview" title="Email preview" sandbox="allow-same-origin" srcdoc="<?= e(email_html($booking)) ?>"></iframe>
  <script>
    // Grow the preview to the full height of the email.
    (function () {
      var f = document.getElementById('preview');
      function fit() { try { f.style.height = f.contentDocument.documentElement.scrollHeight + 'px'; } catch (err) {} }
      f.addEventListener('load', fit);
      window.addEventListener('resize', fit);
    })();
  </script>

<?php elseif ($view === 'sent'): ?>
  <div class="card">
    <h1>Sent! 🎉</h1>
    <p class="lead"><?= e($booking['name']) ?>’s booking for <?= e(nice_date($booking['event_date'])) ?> has been confirmed by email.</p>
    <div class="actions"><a class="btn btn-primary" href="admin.php">Send another</a></div>
  </div>

<?php else: /* form */ ?>
  <h1>Send a booking confirmation</h1>
  <p class="lead">Fill in the booking, then preview the email before it goes.</p>
  <form method="post" class="card" novalidate>
    <?= $csrfInput ?>
    <fieldset>
      <legend>Customer</legend>
      <div class="grid">
        <div class="<?= err_class($errors, 'name') ?>">
          <label for="name">Name</label>
          <input id="name" name="name" value="<?= e($booking['name']) ?>" required autocomplete="off">
          <?= field_error($errors, 'name') ?>
        </div>
        <div class="<?= err_class($errors, 'email') ?>">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?= e($booking['email']) ?>" required autocomplete="off">
          <?= field_error($errors, 'email') ?>
        </div>
        <div>
          <label for="phone">Phone <span class="opt">(optional, for your copy only)</span></label>
          <input type="tel" id="phone" name="phone" value="<?= e($booking['phone']) ?>" autocomplete="off">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Event</legend>
      <div class="grid">
        <div class="<?= err_class($errors, 'event_date') ?>">
          <label for="event_date">Date</label>
          <input type="date" id="event_date" name="event_date" value="<?= e($booking['event_date']) ?>" required>
          <?= field_error($errors, 'event_date') ?>
        </div>
        <div>
          <label for="event_type">Type of event <span class="opt">(optional)</span></label>
          <select id="event_type" name="event_type">
            <option value="">—</option>
            <?php foreach ($EVENT_TYPES as $t): ?>
              <option<?= $booking['event_type'] === $t ? ' selected' : '' ?>><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="<?= err_class($errors, 'start_time') ?>">
          <label for="start_time">Start time <span class="opt">(optional)</span></label>
          <input type="time" id="start_time" name="start_time" value="<?= e($booking['start_time']) ?>">
          <?= field_error($errors, 'start_time') ?>
        </div>
        <div class="<?= err_class($errors, 'end_time') ?>">
          <label for="end_time">Finish time <span class="opt">(optional)</span></label>
          <input type="time" id="end_time" name="end_time" value="<?= e($booking['end_time']) ?>">
          <?= field_error($errors, 'end_time') ?>
        </div>
        <div class="full">
          <label for="venue">Venue / address <span class="opt">(optional)</span></label>
          <input id="venue" name="venue" value="<?= e($booking['venue']) ?>" placeholder="e.g. Mumbles Community Hall, SA3 4EN">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Price</legend>
      <div class="grid">
        <div class="<?= err_class($errors, 'guests') ?>">
          <label for="guests">Number of guests <span class="opt">(optional)</span></label>
          <input type="number" id="guests" name="guests" min="1" step="1" inputmode="numeric" value="<?= e($booking['guests']) ?>">
          <?= field_error($errors, 'guests') ?>
        </div>
        <div class="<?= err_class($errors, 'total') ?>">
          <label for="total">Total <span class="opt">(optional)</span></label>
          <div class="prefix"><span>£</span><input id="total" name="total" inputmode="decimal" value="<?= e($booking['total']) ?>"></div>
          <span class="hint" id="totalHint">Worked out at £20 per 10 guests. You can change it.</span>
          <?= field_error($errors, 'total') ?>
        </div>
        <div class="full">
          <label for="payment">Payment <span class="opt">(optional)</span></label>
          <input id="payment" name="payment" value="<?= e($booking['payment']) ?>">
        </div>
      </div>
    </fieldset>

    <fieldset>
      <legend>Personal note <span class="opt" style="font-family:Nunito,sans-serif;font-weight:400;font-size:.9rem;color:var(--muted)">(optional)</span></legend>
      <textarea id="note" name="note" aria-label="Personal note" placeholder="e.g. Happy 6th birthday to Lily! We'll arrive about 30 minutes early to set up."><?= e($booking['note']) ?></textarea>
    </fieldset>

    <div class="actions"><button class="btn btn-primary" name="action" value="preview">Preview email →</button></div>
  </form>

  <script>
    // Fill in the total from the guest count (£20 per 10), unless it's been typed by hand.
    (function () {
      var guests = document.getElementById('guests');
      var total = document.getElementById('total');
      var auto = total.value === '' || total.value === String(price(guests.value));
      function price(n) { n = parseInt(n, 10); return n > 0 ? Math.ceil(n / 10) * 20 : ''; }
      guests.addEventListener('input', function () { if (auto) { total.value = price(guests.value); } });
      total.addEventListener('input', function () { auto = total.value === ''; });

      // Clear a field's error message as soon as it's changed.
      document.querySelectorAll('.has-error').forEach(function (box) {
        box.addEventListener('input', function () {
          box.classList.remove('has-error');
          var err = box.querySelector('.err');
          if (err) { err.remove(); }
        });
      });
    })();
  </script>
<?php endif; ?>
</main>
</body>
</html>
