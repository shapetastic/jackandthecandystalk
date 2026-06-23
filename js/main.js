/* ===========================================================
   JJ Entertainments — small bits of interactivity.
   No libraries needed. Plain, friendly JavaScript.
   =========================================================== */

(function () {
  'use strict';

  /* ---- Mobile menu toggle ---- */
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('navMenu');

  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var isOpen = menu.classList.toggle('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    // Close the menu after tapping a link (nice on mobile)
    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        menu.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* ---- Friendly client-side form check ----
     This is just a helpful first check. The real,
     trustworthy validation happens in contact.php. */
  var form = document.getElementById('bookingForm');
  var errorBox = document.getElementById('formError');

  if (form) {
    form.addEventListener('submit', function (e) {
      var required = form.querySelectorAll('[required]');
      var firstInvalid = null;

      required.forEach(function (field) {
        var empty = !field.value.trim();
        // Light email sanity check
        var badEmail = field.type === 'email' &&
          field.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value);

        if (empty || badEmail) {
          field.classList.add('invalid');
          if (!firstInvalid) firstInvalid = field;
        } else {
          field.classList.remove('invalid');
        }
      });

      if (firstInvalid) {
        e.preventDefault();
        if (errorBox) errorBox.hidden = false;
        firstInvalid.focus();
      }
    });

    // Clear the red outline as the user fixes a field
    form.querySelectorAll('[required]').forEach(function (field) {
      field.addEventListener('input', function () {
        field.classList.remove('invalid');
      });
    });
  }

  /* ---- Show a message if the server sent us back with an error ---- */
  if (errorBox && /[?&]error=/.test(window.location.search)) {
    if (/error=send/.test(window.location.search)) {
      errorBox.textContent = "Sorry — something went wrong sending your enquiry. " +
        "Please try again, or email us directly.";
    } else {
      errorBox.textContent = "Please fill in the required fields marked with *.";
    }
    errorBox.hidden = false;
  }

  /* ---- Auto-update the year in the footer ---- */
  var yearEl = document.getElementById('year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }
})();
