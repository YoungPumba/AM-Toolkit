(() => {
    'use strict';

    const root = document.querySelector('[data-amt-checkout-settings]');
    const preview = root?.querySelector('[data-amt-checkout-preview]');

    if (!root || !preview) {
        return;
    }

    const properties = {
        font_family: (value) => ['--preview-font-family', value === 'Poppins' ? 'Poppins, sans-serif' : 'inherit'],
        font_size: (value) => ['--preview-font-size', `${value}px`],
        font_weight: (value) => ['--preview-font-weight', value],
        link_weight: (value) => ['--preview-link-weight', value],
        text_color: (value) => ['--preview-text', value],
        link_color: (value) => ['--preview-link', value],
        icon_color: (value) => ['--preview-icon', value],
        background: (value) => ['--preview-background', value],
        border_color: (value) => ['--preview-border', value],
        border_width: (value) => ['--preview-border-width', `${value}px`],
        radius: (value) => ['--preview-radius', `${value}px`]
    };

    root.querySelectorAll('[data-amt-preview-key]').forEach((input) => {
        const update = () => {
            const resolver = properties[input.dataset.amtPreviewKey];

            if (!resolver) {
                return;
            }

            const [property, value] = resolver(input.value);
            preview.style.setProperty(property, value);

            const colorValue = input.closest('.amt-color-field')?.querySelector('[data-amt-color-value]');

            if (colorValue) {
                colorValue.textContent = input.value;
            }
        };

        input.addEventListener('input', update);
        input.addEventListener('change', update);
    });
})();
