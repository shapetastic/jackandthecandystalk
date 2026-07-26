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

  /* ---- Hero photo carousel ----
     Slowly crossfades between the hero photos. Pauses when the visitor
     hovers or tabs into it. If they've asked for less motion we keep the
     photos changing but cut straight over instead of fading, and give them
     a little longer to look. The dots are built here so there are none
     without JS. */
  var carousel = document.getElementById('heroCarousel');
  var dotsBox = document.getElementById('heroCarouselDots');

  if (carousel && dotsBox) {
    var slides = carousel.querySelectorAll('.hero-slide');
    var dots = [];
    var current = 0;
    var timer = null;
    var autoplay = true;   // false once the visitor picks a photo themselves
    var lessMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var delay = lessMotion ? 7000 : 5000;

    if (lessMotion) carousel.classList.add('no-fade');

    // Only the first photo has a src in the HTML, so the hero paints fast.
    // The others fill in once everything else has finished loading.
    var loadRemainingPhotos = function () {
      slides.forEach(function (slide) {
        if (slide.dataset.src) {
          slide.src = slide.dataset.src;
          slide.removeAttribute('data-src');
        }
      });
    };

    if (document.readyState === 'complete') {
      loadRemainingPhotos();
    } else {
      window.addEventListener('load', loadRemainingPhotos);
    }

    var show = function (index) {
      current = (index + slides.length) % slides.length;

      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-active', i === current);
      });
      dots.forEach(function (dot, i) {
        dot.setAttribute('aria-current', i === current ? 'true' : 'false');
      });
    };

    var stop = function () {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    };

    var start = function () {
      if (!autoplay || timer || slides.length < 2) return;
      timer = setInterval(function () { show(current + 1); }, delay);
    };

    // One dot per photo
    slides.forEach(function (slide, i) {
      var dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'hero-dot';
      dot.setAttribute('aria-label', 'Show photo ' + (i + 1) + ' of ' + slides.length);
      dot.setAttribute('aria-current', i === 0 ? 'true' : 'false');

      dot.addEventListener('click', function () {
        stop();          // they've picked one, so stop moving it for them
        autoplay = false;
        show(i);
      });

      dotsBox.appendChild(dot);
      dots.push(dot);
    });

    // Left/right arrows step through the photos
    dotsBox.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
      e.preventDefault();
      show(current + (e.key === 'ArrowRight' ? 1 : -1));
      dots[current].focus();
    });

    // Hold still while they're looking at it
    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    carousel.addEventListener('focusin', stop);
    carousel.addEventListener('focusout', start);

    // No point crossfading a tab nobody is looking at
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    });

    start();
  }

  /* ---- Auto-update the year in the footer ---- */
  var yearEl = document.getElementById('year');
  if (yearEl) {
    yearEl.textContent = new Date().getFullYear();
  }
})();
