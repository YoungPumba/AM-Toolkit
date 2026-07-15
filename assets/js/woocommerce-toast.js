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
            if (Number(config.removedEnabled) !== 1) {
                return;
            }

            show({
                type: 'info',
                title: labels.removedTitle || 'Usunięto z koszyka',
                message: removedMessage(productNameFromButton(button)),
                duration: Number(config.duration) || 4000
            });
        });
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
