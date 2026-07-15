(() => {
    'use strict';

    const config = window.AMToolkitWooCommerce || {};
    const labels = config.labels || {};

    function show(notification) {
        if (!window.AMToolkit || typeof window.AMToolkit.toast !== 'function') {
            return null;
        }

        return window.AMToolkit.toast(notification);
    }

    function productNameFromButton(button) {
        if (!button || !button.length) {
            return '';
        }

        const explicitName = button.attr('data-amt-product-name')
            || button.attr('data-product_name');

        if (explicitName) {
            return String(explicitName).trim();
        }

        const product = button.closest('.product, .woocommerce-cart-form__cart-item, .mini_cart_item');
        const title = product.find(
            '.woocommerce-loop-product__title, .product-title, .product-name a, .product-name'
        ).first().text();

        return String(title || '').trim();
    }

    function formatMessage(template, productName) {
        return String(template || '').replaceAll(
            '{product_name}',
            productName || 'Produkt'
        );
    }

    function addedMessage(productName, count = 1) {
        const message = formatMessage(
            labels.addedMessage || 'Produkt „{product_name}” został dodany do koszyka.',
            productName
        );

        return count > 1 ? `${message} ×${count}` : message;
    }

    function removedMessage(productName) {
        return formatMessage(
            labels.removedMessage || 'Produkt „{product_name}” został usunięty z koszyka.',
            productName
        );
    }

    function showPendingNotifications() {
        const pending = Array.isArray(config.pending) ? config.pending : [];

        pending.forEach((notification) => show(notification));
    }

    function bindWooCommerceEvents() {
        if (!window.jQuery) {
            return;
        }

        const $ = window.jQuery;
        let activeAddedToast = null;
        let lastAddedProduct = '';
        let addedCount = 0;
        let lastRemovalKey = '';
        let lastRemovalProduct = '';
        let lastRemovalTime = 0;

        function removalKey(button, productName) {
            const href = button && button.length ? button.attr('href') : '';

            if (href) {
                try {
                    return new URL(href, window.location.href).searchParams.get('remove_item')
                        || productName;
                } catch (error) {
                    return productName;
                }
            }

            return productName;
        }

        function showRemoved(productName, key = productName) {
            if (Number(config.removedEnabled) !== 1) {
                return;
            }

            const now = Date.now();

            const isRecentDuplicate = (now - lastRemovalTime) < 2000
                && (
                    (key && key === lastRemovalKey)
                    || (productName && productName === lastRemovalProduct)
                );

            if (isRecentDuplicate) {
                return;
            }

            lastRemovalKey = key;
            lastRemovalProduct = productName;
            lastRemovalTime = now;

            show({
                type: 'info',
                title: labels.removedTitle || 'Usunięto z koszyka',
                message: removedMessage(productName),
                duration: Number(config.duration) || 4000
            });
        }

        $(document.body).on('added_to_cart', (event, fragments, cartHash, button) => {
            if (Number(config.addedEnabled) !== 1) {
                return;
            }

            const productName = productNameFromButton(button);
            const sameVisibleProduct = productName
                && productName === lastAddedProduct
                && activeAddedToast
                && activeAddedToast.element
                && activeAddedToast.element.isConnected;

            if (sameVisibleProduct) {
                addedCount += 1;
                activeAddedToast.close();
            } else {
                lastAddedProduct = productName;
                addedCount = 1;
            }

            activeAddedToast = show({
                type: 'success',
                title: labels.addedTitle || 'Dodano do koszyka',
                message: addedMessage(productName, addedCount),
                actionText: labels.cartAction || 'Przejdź do koszyka →',
                actionUrl: config.cartUrl || '/koszyk/',
                duration: Number(config.duration) || 4000
            });
        });

        $(document.body).on('removed_from_cart', (event, fragments, cartHash, button) => {
            const productName = productNameFromButton(button);
            showRemoved(productName, removalKey(button, productName));
        });

        document.addEventListener('click', (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const link = event.target.closest(
                'a.remove, a.remove_from_cart_button, a[href*="remove_item="]'
            );

            if (!link) {
                return;
            }

            const row = link.closest('.cart_item, .mini_cart_item');

            if (!row) {
                return;
            }

            const button = $(link);
            const productName = productNameFromButton(button);
            const key = removalKey(button, productName);
            const observer = new MutationObserver(() => {
                if (row.isConnected) {
                    return;
                }

                observer.disconnect();
                window.clearTimeout(timeout);
                showRemoved(productName, key);
            });
            const timeout = window.setTimeout(() => observer.disconnect(), 10000);

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }, true);
    }

    function init() {
        showPendingNotifications();
        bindWooCommerceEvents();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
