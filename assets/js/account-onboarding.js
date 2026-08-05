(() => {
    'use strict';

    const flow = document.querySelector('[data-amt-account-flow]');

    if (!flow) {
        return;
    }

    flow.querySelectorAll('[data-amt-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = button.closest('.amt-account-flow__password-wrap')?.querySelector('input');

            if (!input) {
                return;
            }

            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Ukryj' : 'Pokaż';
            button.setAttribute('aria-label', reveal ? 'Ukryj hasło' : 'Pokaż hasło');
        });
    });

    const password = flow.querySelector('[data-amt-password-primary]');
    const strength = flow.querySelector('[data-amt-password-strength]');

    if (!password || !strength) {
        return;
    }

    const strengthLabel = strength.querySelector('.amt-password-strength__label');
    const levels = [
        {label: 'Siła hasła', width: '0%', color: '#d9d9d9'},
        {label: 'Słabe', width: '25%', color: '#c94d4d'},
        {label: 'Podstawowe', width: '50%', color: '#c87b22'},
        {label: 'Dobre', width: '75%', color: '#3976c5'},
        {label: 'Mocne', width: '100%', color: '#31845a'},
    ];

    const updateStrength = () => {
        const value = password.value;
        let score = 0;

        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value) && /[^a-zA-Z0-9]/.test(value)) score++;

        if (value.length === 0) score = 0;

        const level = levels[score];
        strength.style.setProperty('--amt-password-strength', level.width);
        strength.style.setProperty('--amt-password-color', level.color);
        if (strengthLabel) strengthLabel.textContent = level.label;
    };

    password.addEventListener('input', updateStrength);
    updateStrength();
})();
