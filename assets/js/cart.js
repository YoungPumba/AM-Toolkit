(() => {
    'use strict';

    if (!window.jQuery) {
        return;
    }

    const $ = window.jQuery;

    $(() => {
        let lastCount = readCount();

        function readCount() {
            const badge = $('.my-cart-count').first();

            return badge.length ? badge.text().trim() : '';
        }

        function animateChangedCount() {
            const badges = $('.my-cart-count');

            if (!badges.length) {
                return;
            }

            const currentCount = badges.first().text().trim();

            if (lastCount !== '' && currentCount !== lastCount) {
                badges.removeClass('bump');

                window.requestAnimationFrame(() => {
                    badges.addClass('bump');
                    window.setTimeout(() => badges.removeClass('bump'), 350);
                });
            }

            lastCount = currentCount;
        }

        function unlockButton(button) {
            if (!button || !button.length) {
                return;
            }

            button.removeData('amtCartPending');
            button.removeClass('amt-cart-pending');
            button.removeAttr('aria-disabled');
        }

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const element = event.target.closest(
                'a.add_to_cart_button, a[href*="add-to-cart="]'
            );

            if (!element) {
                return;
            }

            const button = $(element);

            if (button.data('amtCartPending')) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }

            button.data('amtCartPending', true);
            button.addClass('amt-cart-pending');
            button.attr('aria-disabled', 'true');

            window.setTimeout(() => unlockButton(button), 8000);
        }, true);

        $(document.body).on(
            'added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded updated_wc_div updated_cart_totals',
            (event, fragments, cartHash, button) => {
                window.requestAnimationFrame(animateChangedCount);

                if (event.type === 'added_to_cart') {
                    unlockButton(button);
                }
            }
        );
    });
})();
