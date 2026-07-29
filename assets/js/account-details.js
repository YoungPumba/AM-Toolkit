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

    const avatarInput = accountDetails.querySelector('[data-am-account-avatar-input]');
    const avatarPreview = accountDetails.querySelector('[data-am-account-avatar-preview]');
    const avatarFile = accountDetails.querySelector('[data-am-account-avatar-file]');
    let avatarObjectUrl = '';

    avatarInput?.addEventListener('change', () => {
        const [file] = avatarInput.files || [];

        if (!file) {
            if (avatarFile) avatarFile.textContent = 'Nie wybrano nowego pliku';
            return;
        }

        if (avatarFile) avatarFile.textContent = file.name;

        if (!(avatarPreview instanceof HTMLImageElement) || !file.type.startsWith('image/')) {
            return;
        }

        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
        }

        avatarObjectUrl = URL.createObjectURL(file);
        avatarPreview.src = avatarObjectUrl;
    });

    window.addEventListener('pagehide', () => {
        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
        }
    }, {once: true});
})();
