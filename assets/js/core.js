(() => {
    'use strict';

    window.AMToolkit = {
        version: '0.8.0',

        log(message) {
            console.log(`[AM Toolkit] ${message}`);
        }
    };

    AMToolkit.log(`v${AMToolkit.version} initialized.`);

    const initializeProductSummaryLinks = () => {
        document.querySelectorAll('.am-account-products-summary__link').forEach((link) => {
            const card = link.closest('.e-con');

            if (card) {
                card.classList.add('am-account-products-summary-card');
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeProductSummaryLinks);
    } else {
        initializeProductSummaryLinks();
    }
})();
