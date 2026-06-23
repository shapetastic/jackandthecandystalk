<?php
/* ===========================================================
   JJ Entertainments — booking enquiry handler
   -----------------------------------------------------------
   This little script receives the booking form, checks it,
   and emails the enquiry to you. It works on Host Papa's
   shared hosting out of the box (PHP mail).

   >>> EDIT THE TWO LINES MARKED "EDIT ME" BELOW. <<<
   =========================================================== */

// ---- EDIT ME (1): where do you want enquiries sent? ----
$RECIPIENT = 'your-email@example.com';

// ---- EDIT ME (2): the "from" address.
// IMPORTANT: for best delivery on Host Papa this should be a
// real mailbox at YOUR domain, e.g. bookings@yourdomain.com.
// (Using a gmail/outlook address here often lands in spam.)
$FROM_ADDRESS = 'bookings@yourdomain.com';
$FROM_NAME    = 'JJ Entertainments Website';

// -----------------------------------------------------------
// You normally don't need to change anything below this line.
// -----------------------------------------------------------

// Only accept POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html');
    exit;
}

// --- Spam trap: the hidden "website" field must stay empty. ---
if (!empty($_POST['website'])) {
    // Looks like a bot. Pretend success so it goes away quietly.
    header('Location: thank-you.html');
    exit;
}

// --- Helper: clean a value and strip header-injection attempts. ---
function clean($value) {
    $value = isset($value) ? trim($value) : '';
    // Remove newlines so nobody can inject extra email headers.
    $value = str_replace(array("\r", "\n", "%0a", "%0d"), ' ', $value);
    return $value;
}

// --- Collect the fields. ---
$name       = clean($_POST['name']       ?? '');
$email      = clean($_POST['email']      ?? '');
$phone      = clean($_POST['phone']      ?? '');
$event_date = clean($_POST['event_date'] ?? '');
$event_type = clean($_POST['event_type'] ?? '');
$location   = clean($_POST['location']   ?? '');
// Message can keep its line breaks; just strip stray carriage returns.
$message    = trim(str_replace("\r", '', $_POST['message'] ?? ''));

// --- Validate the required fields. ---
$errors = array();
if ($name === '')                                     { $errors[] = 'name'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'email'; }
if ($event_date === '')                               { $errors[] = 'date'; }

if (!empty($errors)) {
    // Send them back to the form with an error flag.
    header('Location: index.html?error=1#book');
    exit;
}

// --- Build the email. ---
$subject = 'New candy floss booking enquiry from ' . $name;

$body  = "You have a new booking enquiry from the JJ Entertainments website:\n\n";
$body .= "Name:        $name\n";
$body .= "Email:       $email\n";
$body .= "Phone:       " . ($phone !== '' ? $phone : '(not given)') . "\n";
$body .= "Event date:  $event_date\n";
$body .= "Event type:  " . ($event_type !== '' ? $event_type : '(not given)') . "\n";
$body .= "Location:    " . ($location !== '' ? $location : '(not given)') . "\n";
$body .= "\nMessage:\n" . ($message !== '' ? $message : '(none)') . "\n";
$body .= "\n---\nSent from the booking form on your website.\n";

// --- Email headers. Reply-To is the customer so you can reply directly. ---
$headers  = 'From: ' . $FROM_NAME . ' <' . $FROM_ADDRESS . ">\r\n";
$headers .= 'Reply-To: ' . $name . ' <' . $email . ">\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= 'X-Mailer: PHP/' . phpversion();

// --- Send it. ---
$sent = @mail($RECIPIENT, $subject, $body, $headers);

if ($sent) {
    header('Location: thank-you.html');
} else {
    // Mail server refused — send them back with an error so nothing is lost.
    header('Location: index.html?error=send#book');
}
exit;
