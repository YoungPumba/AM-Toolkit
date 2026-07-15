(() => {
    'use strict';

    window.AMToolkit = {
        version: '0.1.0',

        log(message) {
            console.log(`[AM Toolkit] ${message}`);
        }
    };

    AMToolkit.log(`v${AMToolkit.version} initialized.`);
})();