(() => {
    'use strict';

    const existingToolkit = window.AMToolkit || {};
    const configuredVersion = window.AMToolkitConfig?.version || '';

    window.AMToolkit = {
        ...existingToolkit,
        version: configuredVersion,

        log(message) {
            console.log(`[AM Toolkit] ${message}`);
        }
    };

    AMToolkit.log(
        AMToolkit.version
            ? `v${AMToolkit.version} initialized.`
            : 'initialized.'
    );

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
