// File: assets/js/password.js
// Adds a Show/Hide button to every password field, and a live match check on confirmation fields.
// This is a convenience for the person typing. The server still checks every password.

(function () {
  'use strict';

  // Wraps the field and adds the button that switches it between hidden and visible
  function addToggle(input) {
    var wrap = document.createElement('div');
    wrap.className = 'password-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var button = document.createElement('button');
    button.type = 'button'; // type="button" stops the button from submitting the form
    button.className = 'toggle-password';
    button.textContent = 'Show';
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('aria-pressed', 'false');

    button.addEventListener('click', function () {
      var currentlyShown = input.type === 'text';
      input.type = currentlyShown ? 'password' : 'text';
      button.textContent = currentlyShown ? 'Show' : 'Hide';
      button.setAttribute('aria-label', currentlyShown ? 'Show password' : 'Hide password');
      button.setAttribute('aria-pressed', currentlyShown ? 'false' : 'true');
    });

    wrap.appendChild(button);
  }

  // Adds a message under the confirmation field that says whether the passwords match
  function addMatchCheck(confirmInput, form) {
    var source = form.querySelector('input[name="password"], input[name="new_password"]');
    if (source === null) {
      return;
    }

    var hint = document.createElement('p');
    hint.className = 'match-hint';
    hint.setAttribute('aria-live', 'polite');

    var anchor = confirmInput.parentNode;
    anchor.parentNode.insertBefore(hint, anchor.nextSibling);

    function update() {
      if (confirmInput.value === '') {
        hint.textContent = '';
        hint.className = 'match-hint';
      } else if (confirmInput.value === source.value) {
        hint.textContent = 'Passwords match.';
        hint.className = 'match-hint match-ok';
      } else {
        hint.textContent = 'Passwords do not match.';
        hint.className = 'match-hint match-bad';
      }
    }

    confirmInput.addEventListener('input', update);
    source.addEventListener('input', update);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
      addToggle(input);
    });

    document.querySelectorAll('input[name="confirm_password"]').forEach(function (confirmInput) {
      addMatchCheck(confirmInput, confirmInput.form);
    });
  });
})();