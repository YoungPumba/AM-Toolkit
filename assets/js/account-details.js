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
    const avatarStatus = accountDetails.querySelector('[data-am-account-avatar-status]');
    const avatarRemove = accountDetails.querySelector('[data-am-account-avatar-remove]');
    const originalAvatarSrc = avatarPreview instanceof HTMLImageElement
        ? avatarPreview.src
        : '';
    let avatarObjectUrl = '';

    const setAvatarStatus = (message, state = 'success') => {
        if (!avatarStatus) {
            return;
        }

        avatarStatus.textContent = message;
        avatarStatus.dataset.state = state;
        avatarStatus.hidden = message === '';
    };

    const restoreOriginalPreview = () => {
        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
            avatarObjectUrl = '';
        }

        if (avatarPreview instanceof HTMLImageElement && originalAvatarSrc) {
            avatarPreview.src = originalAvatarSrc;
        }
    };

    avatarInput?.addEventListener('change', () => {
        const [file] = avatarInput.files || [];

        if (!file) {
            if (avatarFile) avatarFile.textContent = 'Nie wybrano nowego pliku';
            setAvatarStatus('');
            restoreOriginalPreview();
            return;
        }

        if (avatarFile) avatarFile.textContent = file.name;

        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            avatarInput.value = '';
            setAvatarStatus('Wybierz obraz JPG, PNG albo WebP.', 'error');
            restoreOriginalPreview();
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            avatarInput.value = '';
            setAvatarStatus('Wybrane zdjęcie przekracza limit 3 MB.', 'error');
            restoreOriginalPreview();
            return;
        }

        if (avatarRemove instanceof HTMLInputElement) avatarRemove.checked = false;
        setAvatarStatus('Nowe zdjęcie jest gotowe — zapisz zmiany, aby je wgrać.');

        if (!(avatarPreview instanceof HTMLImageElement) || !file.type.startsWith('image/')) {
            return;
        }

        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
        }

        avatarObjectUrl = URL.createObjectURL(file);
        avatarPreview.src = avatarObjectUrl;
    });

    avatarRemove?.addEventListener('change', () => {
        if (!(avatarRemove instanceof HTMLInputElement)) {
            return;
        }

        if (avatarRemove.checked && avatarInput instanceof HTMLInputElement) {
            avatarInput.value = '';
            if (avatarFile) avatarFile.textContent = 'Nie wybrano nowego pliku';
            restoreOriginalPreview();
        }

        setAvatarStatus(
            avatarRemove.checked
                ? 'Avatar zostanie usunięty po zapisaniu zmian.'
                : ''
        );
    });

    window.addEventListener('pagehide', () => {
        if (avatarObjectUrl) {
            URL.revokeObjectURL(avatarObjectUrl);
        }
    }, {once: true});
})();
