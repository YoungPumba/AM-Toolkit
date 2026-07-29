(() => {
    'use strict';

    const accountDetails = document.querySelector('[data-am-account-details]');

    if (!accountDetails) {
        return;
    }

    accountDetails.querySelectorAll('[data-am-account-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const inputId = button.getAttribute('aria-controls');
            const input = inputId ? document.getElementById(inputId) : null;

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Ukryj' : 'Pokaż';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? 'Ukryj hasło' : 'Pokaż hasło');
        });
    });
})();
