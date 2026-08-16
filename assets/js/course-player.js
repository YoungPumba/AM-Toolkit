(function () {
    'use strict';

    var svgNamespace = 'http://www.w3.org/2000/svg';
    var icons = {
        play: {
            viewBox: '0 0 384 512',
            paths: ['M73 39c-14.8-9.1-33.4-9.4-48.5-.9S0 62.6 0 80V432c0 17.4 9.4 33.4 24.5 41.9s33.7 8.1 48.5-.9L361 297c14.3-8.7 23-24.2 23-41s-8.7-32.2-23-41L73 39z']
        },
        pause: {
            viewBox: '0 0 320 512',
            paths: ['M48 64C21.5 64 0 85.5 0 112V400c0 26.5 21.5 48 48 48H80c26.5 0 48-21.5 48-48V112c0-26.5-21.5-48-48-48H48zm192 0c-26.5 0-48 21.5-48 48V400c0 26.5 21.5 48 48 48h32c26.5 0 48-21.5 48-48V112c0-26.5-21.5-48-48-48H240z']
        },
        volume: {
            viewBox: '0 0 24 24',
            paths: [
                'M18.36 19.36a1 1 0 0 1-.705-1.71C19.167 16.148 20 14.142 20 12s-.833-4.148-2.345-5.65a1 1 0 1 1 1.41-1.419C20.958 6.812 22 9.322 22 12s-1.042 5.188-2.935 7.069a.997.997 0 0 1-.705.291z',
                'M15.53 16.53a.999.999 0 0 1-.703-1.711C15.572 14.082 16 13.054 16 12s-.428-2.082-1.173-2.819a1 1 0 1 1 1.406-1.422A6 6 0 0 1 18 12a6 6 0 0 1-1.767 4.241.996.996 0 0 1-.703.289zM12 22a1 1 0 0 1-.707-.293L6.586 17H4c-1.103 0-2-.897-2-2V9c0-1.103.897-2 2-2h2.586l4.707-4.707A.998.998 0 0 1 13 3v18a1 1 0 0 1-1 1z'
            ]
        },
        mute: {
            viewBox: '0 0 576 512',
            paths: ['M301.1 34.8C312.6 40 320 51.4 320 64V448c0 12.6-7.4 24-18.9 29.2s-25 3.1-34.4-5.3L131.8 352H64c-35.3 0-64-28.7-64-64V224c0-35.3 28.7-64 64-64h67.8L266.7 40.1c9.4-8.4 22.9-10.4 34.4-5.3zM425 167l55 55 55-55c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9l-55 55 55 55c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-55-55-55 55c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l55-55-55-55c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0z']
        },
        expand: {
            viewBox: '0 0 448 512',
            paths: ['M32 32C14.3 32 0 46.3 0 64v96c0 17.7 14.3 32 32 32s32-14.3 32-32V96h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H32zM64 352c0-17.7-14.3-32-32-32s-32 14.3-32 32v96c0 17.7 14.3 32 32 32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H64V352zM320 32c-17.7 0-32 14.3-32 32s14.3 32 32 32h64v64c0 17.7 14.3 32 32 32s32-14.3 32-32V64c0-17.7-14.3-32-32-32H320zM448 352c0-17.7-14.3-32-32-32s-32 14.3-32 32v64H320c-17.7 0-32 14.3-32 32s14.3 32 32 32h96c17.7 0 32-14.3 32-32V352z']
        },
        compress: {
            viewBox: '0 0 448 512',
            paths: ['M160 64c0-17.7-14.3-32-32-32s-32 14.3-32 32v64H32c-17.7 0-32 14.3-32 32s14.3 32 32 32h96c17.7 0 32-14.3 32-32V64zM32 320c-17.7 0-32 14.3-32 32s14.3 32 32 32H96v64c0 17.7 14.3 32 32 32s32-14.3 32-32V352c0-17.7-14.3-32-32-32H32zM352 64c0-17.7-14.3-32-32-32s-32 14.3-32 32v96c0 17.7 14.3 32 32 32h96c17.7 0 32-14.3 32-32s-14.3-32-32-32H352V64zM320 320c-17.7 0-32 14.3-32 32v96c0 17.7 14.3 32 32 32s32-14.3 32-32V384h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H320z']
        }
    };

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

    function setLoading(wrapper, isLoading) {
        var loader = wrapper.querySelector('[data-am-course-player-loader]');

        wrapper.classList.toggle('is-loading', isLoading);

        if (loader) {
            loader.hidden = !isLoading;
        }
    }

    function createIcon(name) {
        var definition = icons[name];
        var svg = document.createElementNS(svgNamespace, 'svg');

        svg.setAttribute('class', 'am-course-player__control-icon am-course-player__control-icon--' + name);
        svg.setAttribute('viewBox', definition.viewBox);
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        svg.setAttribute('data-am-course-icon', name);

        definition.paths.forEach(function (pathData) {
            var path = document.createElementNS(svgNamespace, 'path');
            path.setAttribute('d', pathData);
            svg.appendChild(path);
        });

        return svg;
    }

    function addIcons(button, names) {
        if (!button || button.querySelector('[data-am-course-icon]')) {
            return;
        }

        names.forEach(function (name) {
            button.appendChild(createIcon(name));
        });
    }

    function fullscreenElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || null;
    }

    function isWrapperFullscreen(wrapper) {
        var element = fullscreenElement();

        if (wrapper.classList.contains('is-native-fullscreen')) {
            return true;
        }

        return Boolean(element && (element === wrapper || wrapper.contains(element) || element.contains(wrapper)));
    }

    function lockLandscape() {
        var orientation = window.screen && window.screen.orientation;

        if (!orientation || typeof orientation.lock !== 'function') {
            return;
        }

        try {
            var result = orientation.lock('landscape');

            if (result && typeof result.catch === 'function') {
                result.catch(function () {});
            }
        } catch (error) {
            // Orientation locking is optional and must never block fullscreen.
        }
    }

    function unlockOrientation() {
        var orientation = window.screen && window.screen.orientation;

        if (!orientation || typeof orientation.unlock !== 'function') {
            return;
        }

        try {
            orientation.unlock();
        } catch (error) {
            // Some browsers expose the API but reject unlock outside fullscreen.
        }
    }

    function updateControlStates(wrapper, video) {
        var playButton = wrapper.querySelector('.mejs-playpause-button > button');
        var volumeButton = wrapper.querySelector('.mejs-volume-button > button');
        var fullscreenButton = wrapper.querySelector('.mejs-fullscreen-button > button');
        var isFullscreen = isWrapperFullscreen(wrapper);

        if (playButton) {
            playButton.setAttribute('aria-label', video.paused ? 'Odtwórz' : 'Wstrzymaj');
            playButton.setAttribute('aria-pressed', video.paused ? 'false' : 'true');
        }

        if (volumeButton) {
            volumeButton.setAttribute('aria-label', video.muted || video.volume === 0 ? 'Włącz dźwięk' : 'Wycisz');
            volumeButton.setAttribute('aria-pressed', video.muted || video.volume === 0 ? 'true' : 'false');
        }

        if (fullscreenButton) {
            fullscreenButton.setAttribute('aria-label', isFullscreen ? 'Wyłącz pełny ekran' : 'Włącz pełny ekran');
            fullscreenButton.setAttribute('aria-pressed', isFullscreen ? 'true' : 'false');
        }
    }

    function enhanceControls(wrapper, video) {
        var player = wrapper.querySelector('.mejs-container');

        if (!player) {
            return false;
        }

        player.classList.add('am-course-player__mediaelement');
        addIcons(wrapper.querySelector('.mejs-playpause-button > button'), ['play', 'pause']);
        addIcons(wrapper.querySelector('.mejs-volume-button > button'), ['volume', 'mute']);
        addIcons(wrapper.querySelector('.mejs-fullscreen-button > button'), ['expand', 'compress']);
        updateControlStates(wrapper, video);

        return true;
    }

    function observeControls(wrapper, video) {
        if (enhanceControls(wrapper, video) || typeof MutationObserver === 'undefined') {
            return;
        }

        var observer = new MutationObserver(function () {
            if (enhanceControls(wrapper, video)) {
                observer.disconnect();
            }
        });

        observer.observe(wrapper, {childList: true, subtree: true});
    }

    function setupStickyOffset() {
        var root = document.documentElement;
        var selectors = [
            '[data-elementor-type="header"]',
            '.elementor-location-header',
            '.elementor-sticky--active:not(.elementor-sticky__spacer)',
            'header.site-header',
            '.site-header',
            '#masthead'
        ];
        var ticking = false;

        function update() {
            var headerBottom = 0;

            selectors.forEach(function (selector) {
                document.querySelectorAll(selector).forEach(function (header) {
                    var rect = header.getBoundingClientRect();
                    var style = window.getComputedStyle(header);
                    var isPinned = style.position === 'fixed' || style.position === 'sticky';
                    var touchesViewportTop = rect.top <= 1 && rect.bottom > 0;

                    if ((isPinned || touchesViewportTop) && rect.bottom <= window.innerHeight) {
                        headerBottom = Math.max(headerBottom, rect.bottom);
                    }
                });
            });

            root.style.setProperty('--am-course-sticky-top', Math.ceil(headerBottom + 24) + 'px');
            ticking = false;
        }

        function requestUpdate() {
            if (!ticking) {
                ticking = true;
                window.requestAnimationFrame(update);
            }
        }

        update();
        window.addEventListener('resize', requestUpdate, {passive: true});
        window.addEventListener('scroll', requestUpdate, {passive: true});
    }

    function init(wrapper) {
        var video = wrapper.querySelector('video');

        if (wrapper.dataset.amCoursePlayerReady === 'true') {
            return;
        }

        wrapper.dataset.amCoursePlayerReady = 'true';

        if (!video) {
            setLoading(wrapper, false);
            announce(wrapper, 'Odtwarzacz nagrania jest niedostępny.');
            return;
        }

        setLoading(wrapper, video.readyState < 2);
        observeControls(wrapper, video);

        video.addEventListener('loadstart', function () {
            setLoading(wrapper, true);
        });

        video.addEventListener('seeking', function () {
            setLoading(wrapper, true);
        });

        ['waiting', 'stalled'].forEach(function (eventName) {
            video.addEventListener(eventName, function () {
                if (!video.paused && video.readyState < 3) {
                    setLoading(wrapper, true);
                }
            });
        });

        ['loadedmetadata', 'loadeddata', 'canplay', 'canplaythrough', 'playing', 'seeked', 'suspend'].forEach(function (eventName) {
            video.addEventListener(eventName, function () {
                setLoading(wrapper, false);
            });
        });

        ['play', 'pause', 'ended'].forEach(function (eventName) {
            video.addEventListener(eventName, function () {
                setLoading(wrapper, false);
                updateControlStates(wrapper, video);
                emit(wrapper, eventName);
            });
        });

        video.addEventListener('volumechange', function () {
            updateControlStates(wrapper, video);
        });

        video.addEventListener('error', function () {
            setLoading(wrapper, false);
            announce(wrapper, 'Nie udało się odtworzyć nagrania. Odśwież stronę lub spróbuj ponownie później.');
            emit(wrapper, 'error');
        });

        setupProgressTracking(wrapper, video);

        video.addEventListener('webkitbeginfullscreen', function () {
            wrapper.classList.add('is-native-fullscreen');
            updateControlStates(wrapper, video);
            lockLandscape();
        });

        video.addEventListener('webkitendfullscreen', function () {
            wrapper.classList.remove('is-native-fullscreen');
            updateControlStates(wrapper, video);
            unlockOrientation();
        });

        ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (eventName) {
            document.addEventListener(eventName, function () {
                if (isWrapperFullscreen(wrapper)) {
                    lockLandscape();
                } else {
                    unlockOrientation();
                }

                updateControlStates(wrapper, video);
            });
        });
    }

    function requestId() {
        var now = new Date();
        var date = [
            now.getUTCFullYear(),
            String(now.getUTCMonth() + 1).padStart(2, '0'),
            String(now.getUTCDate()).padStart(2, '0')
        ].join('');
        var bytes = new Uint8Array(6);

        if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
            window.crypto.getRandomValues(bytes);
        } else {
            bytes.forEach(function (unused, index) {
                bytes[index] = Math.floor(Math.random() * 256);
            });
        }

        return 'AM-' + date + '-' + Array.from(bytes).map(function (byte) {
            return byte.toString(16).padStart(2, '0');
        }).join('').toUpperCase();
    }

    function mergeIntervals(intervals) {
        var sorted = intervals.slice().sort(function (left, right) {
            return left[0] - right[0];
        });

        return sorted.reduce(function (merged, interval) {
            var last = merged[merged.length - 1];

            if (!last || interval[0] > last[1] + 0.25) {
                merged.push([interval[0], interval[1]]);
            } else {
                last[1] = Math.max(last[1], interval[1]);
            }

            return merged;
        }, []);
    }

    function progressConfig() {
        return window.amToolkitCourseProgress || null;
    }

    function progressRequest(operation, course, lesson, extra, keepalive) {
        var config = progressConfig();

        if (!config) {
            return Promise.reject(new Error('Progress endpoint is unavailable.'));
        }

        var body = new URLSearchParams({
            action: config.action,
            nonce: config.nonce,
            operation: operation,
            course: course,
            lesson: lesson,
            request_id: extra.requestId || requestId()
        });

        if (extra.intervals) {
            body.set('intervals', JSON.stringify(extra.intervals));
        }

        return window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString(),
            keepalive: Boolean(keepalive)
        }).then(function (response) {
            return response.json();
        }).then(function (response) {
            if (!response || !response.success || !response.data || !response.data.progress) {
                var message = response && response.data && response.data.message
                    ? response.data.message
                    : 'Nie udało się zapisać postępu.';
                throw new Error(message);
            }

            return response.data.progress;
        });
    }

    function updateProgressPanel(panel, progress) {
        if (!panel) {
            return;
        }

        var watched = Math.max(0, Math.min(100, Number(progress.watched_percent) || 0));
        var required = Number(progress.video_percent_required) || 0;
        var watchedBar = panel.querySelector('[data-am-watched-bar]');
        var watchedLabel = panel.querySelector('[data-am-watched-label]');
        var progressBar = panel.querySelector('[role="progressbar"]');
        var taskButton = panel.querySelector('[data-am-progress-action="acknowledge_task"]');
        var manualButton = panel.querySelector('[data-am-progress-action="complete_manually"]');
        var title = panel.querySelector('[data-am-progress-title]');
        var badge = panel.querySelector('[data-am-progress-badge]');

        if (watchedBar) {
            watchedBar.style.width = watched + '%';
        }

        if (watchedLabel) {
            watchedLabel.textContent = watched.toLocaleString('pl-PL', {maximumFractionDigits: 1}) + '% / ' + required + '%';
        }

        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', watched);
        }

        if (progress.task_completed && taskButton) {
            taskButton.disabled = true;
            taskButton.textContent = 'Zadanie wykonane';
        }

        if (progress.lesson_completed) {
            panel.classList.add('am-lesson-progress--completed');

            if (title) {
                title.textContent = 'Lekcja ukończona';
            }

            if (badge) {
                badge.textContent = '✓';
            }

            if (taskButton) {
                taskButton.disabled = true;
                taskButton.textContent = 'Zadanie wykonane';
            }

            if (manualButton) {
                manualButton.remove();
            }
        } else if (badge) {
            badge.textContent = (Number(progress.course_progress_percent) || 0) + '%';
        }
    }

    function panelMessage(panel, message, isError) {
        var element = panel && panel.querySelector('[data-am-progress-message]');

        if (!element) {
            return;
        }

        element.textContent = message;
        element.classList.toggle('is-error', Boolean(isError));
    }

    function setupProgressActions(panel) {
        var config = progressConfig();

        if (!panel || !config || panel.dataset.amProgressActionsReady === 'true') {
            return;
        }

        panel.dataset.amProgressActionsReady = 'true';
        panel.querySelectorAll('[data-am-progress-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var operation = button.dataset.amProgressAction;

                button.disabled = true;
                panelMessage(panel, config.messages.saving, false);
                progressRequest(operation, panel.dataset.course, panel.dataset.lesson, {}).then(function (progress) {
                    updateProgressPanel(panel, progress);
                    panelMessage(panel, progress.lesson_completed ? config.messages.completed : config.messages.saved, false);

                    if (!progress.lesson_completed && !progress.task_completed) {
                        button.disabled = false;
                    }
                }).catch(function (error) {
                    button.disabled = false;
                    panelMessage(panel, error.message || config.messages.error, true);
                });
            });
        });
    }

    function setupProgressTracking(wrapper, video) {
        var config = progressConfig();
        var panel = document.querySelector('[data-am-course-progress][data-course="' + wrapper.dataset.course
            + '"][data-lesson="' + wrapper.dataset.lesson + '"]');

        setupProgressActions(panel);

        if (!config || !panel || wrapper.dataset.amProgressTrackingReady === 'true') {
            return;
        }

        wrapper.dataset.amProgressTrackingReady = 'true';
        var pending = [];
        var queue = [];
        var sending = false;
        var previousTime = null;
        var accumulated = 0;

        function enqueue(keepalive) {
            if (!pending.length) {
                return;
            }

            queue.push({
                intervals: mergeIntervals(pending),
                requestId: requestId()
            });
            pending = [];
            accumulated = 0;
            pump(keepalive);
        }

        function pump(keepalive) {
            if (sending || !queue.length) {
                return;
            }

            sending = true;
            var batch = queue[0];

            progressRequest('video_checkpoint', wrapper.dataset.course, wrapper.dataset.lesson, batch, keepalive).then(function (progress) {
                queue.shift();
                updateProgressPanel(panel, progress);
                panelMessage(panel, progress.lesson_completed ? config.messages.completed : '', false);
            }).catch(function () {
                panelMessage(panel, config.messages.error, true);
            }).finally(function () {
                sending = false;
            });
        }

        video.addEventListener('playing', function () {
            previousTime = video.currentTime;
            pump();
        });

        video.addEventListener('timeupdate', function () {
            var current = video.currentTime;

            if (
                previousTime !== null
                && !video.paused
                && !video.seeking
                && current > previousTime
                && current - previousTime <= 2.5
            ) {
                pending.push([previousTime, current]);
                accumulated += current - previousTime;

                if (accumulated >= (Number(config.checkpointSeconds) || 15)) {
                    enqueue();
                }
            }

            previousTime = current;
        });

        video.addEventListener('seeking', function () {
            previousTime = null;
        });

        video.addEventListener('seeked', function () {
            previousTime = video.currentTime;
        });

        ['pause', 'ended'].forEach(function (eventName) {
            video.addEventListener(eventName, enqueue);
        });

        window.addEventListener('pagehide', function () {
            enqueue(true);

            if (!sending) {
                pump(true);
            }
        });
    }

    function boot() {
        setupStickyOffset();
        document.querySelectorAll('[data-am-course-player]').forEach(init);
        document.querySelectorAll('[data-am-course-progress]').forEach(setupProgressActions);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
