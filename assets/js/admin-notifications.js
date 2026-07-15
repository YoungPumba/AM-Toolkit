(() => {
    'use strict';

    const sampleProduct = 'Testowy produkt';

    function field(id) {
        return document.getElementById(id);
    }

    function value(id, fallback = '') {
        const element = field(id);

        return element ? element.value.trim() : fallback;
    }

    function duration() {
        const parsed = Number.parseInt(value('amt-duration', '4000'), 10);

        return Number.isFinite(parsed) ? Math.max(1000, Math.min(15000, parsed)) : 4000;
    }

    function format(template) {
        return template.replaceAll('{product_name}', sampleProduct);
    }

    function preview(type) {
        if (!window.AMToolkit || typeof window.AMToolkit.toast !== 'function') {
            return;
        }

        const added = type === 'added';

        window.AMToolkit.toast({
            type: added ? 'success' : 'info',
            title: value(
                added ? 'amt-added-title' : 'amt-removed-title',
                added ? 'Dodano do koszyka' : 'Usunięto z koszyka'
            ),
            message: format(value(
                added ? 'amt-added-message' : 'amt-removed-message',
                added
                    ? 'Produkt „{product_name}” został dodany do koszyka.'
                    : 'Produkt „{product_name}” został usunięty z koszyka.'
            )),
            actionText: added ? value('amt-cart-action', 'Przejdź do koszyka →') : '',
            actionUrl: added ? '#' : '',
            duration: duration()
        });
    }

    document.querySelectorAll('[data-amt-preview]').forEach((button) => {
        button.addEventListener('click', () => preview(button.dataset.amtPreview));
    });

    const resetLink = document.querySelector('[data-amt-reset]');

    if (resetLink) {
        resetLink.addEventListener('click', (event) => {
            const confirmed = window.confirm(
                'Przywrócić wszystkie ustawienia powiadomień do wartości domyślnych?'
            );

            if (!confirmed) {
                event.preventDefault();
            }
        });
    }
})();
