(() => {
    'use strict';

    window.AMToolkit = {
        version: '0.2.0',

        log(message) {
            console.log(`[AM Toolkit] ${message}`);
        }
    };

    AMToolkit.log(`v${AMToolkit.version} initialized.`);
})();
