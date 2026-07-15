(() => {
    'use strict';

    window.AMToolkit = {
        version: '0.4.1',

        log(message) {
            console.log(`[AM Toolkit] ${message}`);
        }
    };

    AMToolkit.log(`v${AMToolkit.version} initialized.`);
})();
