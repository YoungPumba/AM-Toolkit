(() => {
    'use strict';

    const config = window.AMToolkitCart || {};

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

        function inferProductName(element) {
            const explicit = element.getAttribute('data-amt-product-name')
                || element.getAttribute('data-product_name');

            if (explicit) {
                return explicit.trim();
            }

            const product = element.closest('.product');
            const productTitle = product?.querySelector(
                '.woocommerce-loop-product__title, .product-title, h2'
            )?.textContent;

            if (productTitle) {
                return productTitle.trim();
            }

            const pageTitle = document.querySelector(
                'h1.product_title, .elementor-widget-woocommerce-product-title h1, main h1'
            )?.textContent;

            return pageTitle ? pageTitle.trim() : '';
        }

        function replaceFragments(fragments) {
            if (!fragments || typeof fragments !== 'object') {
                return;
            }

            Object.entries(fragments).forEach(([selector, html]) => {
                document.querySelectorAll(selector).forEach((currentElement) => {
                    const template = document.createElement('template');
                    template.innerHTML = String(html).trim();
                    const replacement = template.content.firstElementChild;

                    if (replacement) {
                        currentElement.replaceWith(replacement.cloneNode(true));
                    }
                });
            });
        }

        async function addToCartWithAjax(element, url) {
            const button = $(element);
            const productId = url.searchParams.get('add-to-cart');
            const quantity = url.searchParams.get('quantity') || '1';

            if (!productId || !config.addToCartUrl) {
                window.location.assign(url.href);
                return;
            }

            const productName = inferProductName(element);

            if (productName) {
                button.attr('data-amt-product-name', productName);
            }

            const body = new URLSearchParams({
                product_id: productId,
                quantity
            });

            try {
                const response = await window.fetch(config.addToCartUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: body.toString()
                });
                const data = await response.json();

                if (!response.ok || data.error) {
                    window.location.assign(data.product_url || url.href);
                    return;
                }

                replaceFragments(data.fragments);
                animateChangedCount();

                $(document.body).trigger('added_to_cart', [
                    data.fragments,
                    data.cart_hash,
                    button
                ]);
            } catch (error) {
                window.location.assign(url.href);
            } finally {
                unlockButton(button);
            }
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

            const shouldUseCustomAjax = !element.classList.contains('ajax_add_to_cart')
                && !event.metaKey
                && !event.ctrlKey
                && !event.shiftKey
                && !event.altKey
                && element.target !== '_blank';

            if (!shouldUseCustomAjax) {
                return;
            }

            const url = new URL(element.href, window.location.href);

            if (!url.searchParams.has('add-to-cart')) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            addToCartWithAjax(element, url);
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
