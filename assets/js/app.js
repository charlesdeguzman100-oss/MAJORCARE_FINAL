// File: assets/js/app.js
// Responsive sidebar behaviour. No dependencies.

(function () {
  'use strict';

  var toggle = document.getElementById('menuToggle');
  var sidebar = document.getElementById('sidebar');
  var closeButton = document.getElementById('sidebarClose');
  var overlay = document.getElementById('sidebarOverlay');

  if (!toggle || !sidebar || !overlay) {
    return;
  }

  function open() {
    document.body.classList.add('sidebar-open');
    overlay.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
  }

  function close() {
    document.body.classList.remove('sidebar-open');
    overlay.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
  }

  toggle.addEventListener('click', function () {
    if (document.body.classList.contains('sidebar-open')) {
      close();
    } else {
      open();
    }
  });

  if (closeButton) {
    closeButton.addEventListener('click', close);
  }

  overlay.addEventListener('click', close);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
      close();
    }
  });

  // Following a link on a small screen should dismiss the drawer.
  sidebar.addEventListener('click', function (event) {
    if (event.target.closest('a') && window.matchMedia('(max-width: 900px)').matches) {
      close();
    }
  });

  // Reset mobile state when the viewport grows back to desktop.
  window.addEventListener('resize', function () {
    if (!window.matchMedia('(max-width: 900px)').matches) {
      close();
    }
  });
})();
