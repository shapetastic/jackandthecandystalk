<?php
/* -----------------------------------------------------------
   JJ Entertainments — booking form settings (EXAMPLE)
   -----------------------------------------------------------
   This is a TEMPLATE. It is safe to keep in the public repo
   because it contains no real details.

   ON THE SERVER, make a copy of this file called:
       config.local.php
   and put your real email addresses in it. That file is
   git-ignored and is never uploaded by the deploy workflow,
   so your email stays private.

   contact.php automatically reads config.local.php if it exists.
   ----------------------------------------------------------- */

return array(
    // Where booking enquiries are emailed to:
    'recipient' => 'you@example.com',

    // The "from" address. For best delivery on Host Papa this should
    // be a real mailbox at your own domain (create it in cPanel).
    'from'      => 'bookings@jjentertainments.co.uk',

    'from_name' => 'JJ Entertainments Website',
);
