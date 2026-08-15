(function () {
    'use strict';

    function announce(wrapper, message) {
        var status = wrapper.querySelector('[data-am-course-player-status]');

        if (status) {
            status.textContent = message;
        }
    }

    function emit(wrapper, eventName) {
        wrapper.dispatchEvent(new CustomEvent('amtoolkit:course-player', {
            bubbles: true,
            detail: {
                event: eventName,
                course: wrapper.dataset.course || '',
                lesson: wrapper.dataset.lesson || ''
            }
        }));
    }

    document.querySelectorAll('[data-am-course-player]').forEach(function (wrapper) {
        var video = wrapper.querySelector('video');

        if (!video) {
            announce(wrapper, 'Odtwarzacz nagrania jest niedostępny.');
            return;
        }

        ['play', 'pause', 'ended'].forEach(function (eventName) {
            video.addEventListener(eventName, function () {
                emit(wrapper, eventName);
            });
        });

        video.addEventListener('error', function () {
            announce(wrapper, 'Nie udało się odtworzyć nagrania. Odśwież stronę lub spróbuj ponownie później.');
            emit(wrapper, 'error');
        });
    });
}());
