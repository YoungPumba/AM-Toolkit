(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.amConfirm) {
            return;
        }

        if (!window.confirm(form.dataset.amConfirm)) {
            event.preventDefault();
        }
    });
}());
