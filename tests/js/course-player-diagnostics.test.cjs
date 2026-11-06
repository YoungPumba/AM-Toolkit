'use strict';

// Dependency-free behavior tests. No browser, network, or real account data.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');
const source = fs.readFileSync(path.join(__dirname, '../../assets/js/course-player.js'), 'utf8');

function target(extra = {}) {
    const listeners = new Map();
    return Object.assign({
        listeners,
        addEventListener(name, callback) {
            const callbacks = listeners.get(name) || [];
            callbacks.push(callback);
            listeners.set(name, callbacks);
        },
        fire(name, event = {isTrusted: true}) {
            (listeners.get(name) || []).forEach(callback => callback(event));
        }
    }, extra);
}

function harness(mode = 'native', diagnostic = true) {
    const calls = [];
    const emitted = [];
    const intervals = new Map();
    let nextIntervalId = 1;
    let clock = 125.5;
    let observed = 0;
    const classes = new Set();
    const classList = {
        contains: name => classes.has(name),
        add: name => classes.add(name),
        remove: name => classes.delete(name),
        toggle(name, value) { value ? classes.add(name) : classes.delete(name); }
    };
    const loader = {hidden: false};
    const button = target();
    const status = {textContent: ''};
    const playControl = {attributes: {}, setAttribute(name, value) { this.attributes[name] = value; }};
    const diagnosticPanel = {
        dataset: {},
        matches: selector => selector === '[data-am-course-diagnostics-panel]',
        querySelector: selector => selector.includes('download') ? button : status
    };
    const video = target({
        currentTime: 0, duration: 169.2, readyState: 4, networkState: 1,
        paused: true, ended: false, seeking: false, playbackRate: 1,
        buffered: {length: 0}, seekable: {length: 0}, error: null,
        currentSrc: 'https://example.test/admin-post.php?action=asset&nonce=private-nonce'
    });
    const progressPanel = {
        dataset: {course: 'course-test', lesson: 'lesson-test', resumeAt: '12'},
        classList, querySelector: () => null, querySelectorAll: () => []
    };
    const wrapper = {
        dataset: {
            course: 'course-test', lesson: 'lesson-test', amCoursePlayerMode: mode,
            amCourseDiagnosticSession: diagnostic ? 'AMD-AAAAAAAAAAAAAAAAAAAAAAAA' : ''
        },
        classList,
        nextElementSibling: diagnosticPanel,
        dispatchEvent(event) { emitted.push(event); },
        querySelector(selector) {
            if (selector === 'video') return video;
            if (selector === '[data-am-course-player-loader]') return loader;
            if (selector === '[data-am-course-player-status]') return status;
            if (selector === '.mejs-playpause-button > button') return playControl;
            return null;
        }
    };
    const document = target({
        readyState: 'complete', visibilityState: 'visible',
        documentElement: {style: {setProperty() {}}},
        body: {appendChild() {}},
        createElement: () => ({click() {}, remove() {}}),
        querySelector: () => progressPanel,
        querySelectorAll(selector) {
            if (selector === '[data-am-course-player]') return [wrapper];
            if (selector === '[data-am-course-progress]') return [progressPanel];
            return [];
        }
    });
    class TestURL extends URL {}
    TestURL.createObjectURL = () => 'blob:test';
    TestURL.revokeObjectURL = () => {};
    const resourceTimings = [{
        name: video.currentSrc,
        entryType: 'resource',
        startTime: 12.125,
        duration: 45.25,
        responseStart: 20.5,
        responseEnd: 57.375,
        responseStatus: 206,
        transferSize: 1040,
        encodedBodySize: 900,
        decodedBodySize: 900,
        initiatorType: 'video'
    }];
    const window = target({
        performance: {
            now: () => clock,
            getEntriesByName: name => resourceTimings.filter(entry => entry.name === name)
        },
        location: {href: 'https://example.test/course', origin: 'https://example.test'},
        navigator: {userAgent: 'test', onLine: true},
        amToolkitCourseMediaDiagnostics: {
            ajaxUrl: '/test-ajax', action: 'diagnostics', nonce: 'test-nonce',
            messages: {preparing: 'preparing', ready: 'ready', error: 'error'}
        },
        amToolkitCourseProgress: {
            ajaxUrl: '/test-ajax', action: 'progress', nonce: 'test-nonce',
            checkpointSeconds: 2, messages: {saved: 'saved', completed: 'completed'}
        },
        localStorage: {getItem: () => null, setItem() {}, removeItem() {}},
        URL: TestURL,
        setTimeout() {},
        setInterval(callback) {
            const id = nextIntervalId++;
            intervals.set(id, callback);
            return id;
        },
        clearInterval(id) { intervals.delete(id); },
        fetch(url, options) {
            calls.push({url, options, body: new URLSearchParams(options.body)});
            return Promise.resolve({json: async () => ({success: true, data: {report: {}, progress: {}}})});
        }
    });
    vm.runInNewContext(source, {
        window, document, URLSearchParams, Blob, Uint8Array, console,
        CustomEvent: class {
            constructor(type, options) { this.type = type; Object.assign(this, options); }
        },
        MutationObserver: class { observe() { observed++; } disconnect() {} }
    });
    return {video, wrapper, loader, button, calls, document, emitted, status, playControl, window,
        tick(ms) {
            clock += ms;
            [...intervals.values()].forEach(callback => callback());
        },
        intervalCount: () => intervals.size,
        observed: () => observed};
}

test('ordinary native player retains progress/resume without diagnostics or MediaElement hooks', async () => {
    const h = harness('native', false);
    assert.equal(h.video.currentTime, 12, 'same stored-position logic as standard player');
    assert.equal(h.wrapper.dataset.amProgressTrackingReady, 'true');
    assert.equal(h.observed(), 0);
    assert.equal(h.loader.hidden, true);
    assert.equal(h.button.listeners.has('click'), false);
    assert.equal(h.intervalCount(), 0);
    assert.equal(h.video.listeners.has('progress'), false);
    assert.equal(h.video.listeners.has('webkitbeginfullscreen'), false);
    assert.equal(h.calls.length, 0);
    h.video.fire('waiting');
    assert.equal(h.loader.hidden, true);
    assert.equal(h.document.listeners.has('fullscreenchange'), false);
    h.video.paused = false;
    h.video.fire('playing');
    h.video.currentTime = 13;
    h.video.fire('timeupdate');
    h.video.currentTime = 14;
    h.video.fire('timeupdate');
    await new Promise(resolve => setImmediate(resolve));
    const progress = h.calls.find(call => call.body.get('action') === 'progress');
    assert.ok(progress, 'native player still submits real watched intervals');
    assert.equal(progress.body.get('operation'), 'video_checkpoint');
    assert.equal(progress.options.credentials, 'same-origin');
    assert.deepEqual(JSON.parse(progress.body.get('intervals')), [[12, 14]]);
    assert.equal(h.calls.some(call => call.body.get('action') === 'diagnostics'), false);
});

test('diagnostic export marks native mode, trusted/synthetic events, cap and final snapshot', () => {
    const h = harness();
    for (let i = 0; i < 270; i++) h.video.fire('progress');
    h.video.seeking = true;
    h.video.fire('seeking', {isTrusted: false});
    h.button.fire('click');
    const request = h.calls.at(-1);
    const events = JSON.parse(request.body.get('events'));
    const env = JSON.parse(request.body.get('environment'));
    assert.equal(env.player_mode, 'native');
    assert.equal(env.mediaelement_present, false);
    assert.equal(env.client_events_dropped, 23); // initial + 270 + seeking + export - 250
    assert.equal(env.playback_stall_threshold_ms, 3000);
    assert.equal(env.diagnostics_performance_origin_ms, 125.5);
    assert.deepEqual(env.media_resource_timings, [{
        start_time: 12.125,
        duration: 45.25,
        response_start: 20.5,
        response_end: 57.375,
        response_status: 206,
        transfer_size: 1040,
        encoded_body_size: 900,
        decoded_body_size: 900
    }]);
    assert.equal(JSON.stringify(env).includes('private-nonce'), false);
    assert.equal(events.length, 250);
    assert.equal(events[0].is_trusted, true);
    assert.equal(events.at(-2).is_trusted, false);
    assert.equal(events.at(-2).seeking, true);
    assert.equal(events.at(-1).event, 'diagnostics-export');
    assert.equal(events.at(-1).is_trusted, null);
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.body.get('nonce'), 'test-nonce');
    assert.equal(request.body.get('course'), 'course-test');
    assert.equal(request.body.get('lesson'), 'lesson-test');
});

test('diagnostic watchdog measures only visible active stalls and exports an open stall', () => {
    const h = harness();
    assert.equal(h.intervalCount(), 1);

    h.video.paused = false;
    h.video.fire('play');
    h.video.fire('playing');
    h.tick(2999);
    h.tick(1);
    h.video.currentTime += 0.1;
    h.tick(500);

    h.tick(3000);
    h.video.paused = true;
    h.video.fire('pause');
    h.tick(4000); // A real pause must not open another playback stall.

    h.video.paused = false;
    h.video.fire('play');
    h.video.fire('playing');
    h.tick(3000);
    h.video.seeking = true;
    h.video.fire('seeking');

    h.video.seeking = false;
    h.video.fire('seeked');
    h.tick(3000);
    h.button.fire('click');

    const openExport = h.calls.at(-1);
    const openEvents = JSON.parse(openExport.body.get('events'));
    const openSnapshot = openEvents.at(-1);
    assert.equal(openSnapshot.event, 'diagnostics-export');
    assert.equal(openSnapshot.playback_stall_open, true);
    assert.equal(openSnapshot.playback_stall_duration_ms, 3000);

    h.document.visibilityState = 'hidden';
    h.document.fire('visibilitychange');
    h.tick(5000); // Hidden time must not open another playback stall.
    h.window.fire('pagehide');
    assert.equal(h.intervalCount(), 0);
    h.button.fire('click');

    const finalEvents = JSON.parse(h.calls.at(-1).body.get('events'));
    assert.deepEqual(
        finalEvents.filter(event => event.event === 'playback-stall-start').map(event => event.playback_stall_duration_ms),
        [3000, 3000, 3000, 3000]
    );
    assert.deepEqual(
        finalEvents.filter(event => event.event === 'playback-stall-end').map(event => event.playback_stall_end_reason),
        ['advance', 'pause', 'seek', 'hidden']
    );
    assert.equal(finalEvents.at(-1).playback_stall_open, false);
});

test('diagnostic watchdog restarts once after BFCache restore and closes a stall on media error', () => {
    const h = harness();
    h.video.paused = false;
    h.video.fire('playing');
    h.tick(3000);

    h.window.fire('pagehide', {persisted: true});
    assert.equal(h.intervalCount(), 0);

    h.tick(5000); // Time spent in BFCache must not count toward a restored stall.
    h.window.fire('pageshow', {persisted: false});
    assert.equal(h.intervalCount(), 0, 'only a BFCache restore may revive this document watchdog');
    h.window.fire('pageshow', {persisted: true});
    h.window.fire('pageshow', {persisted: true});
    assert.equal(h.intervalCount(), 1, 'repeated pageshow must not create duplicate watchdog timers');

    h.tick(2999);
    h.tick(1);
    h.video.error = {code: 2};
    h.video.fire('error');
    h.button.fire('click');

    const events = JSON.parse(h.calls.at(-1).body.get('events'));
    assert.deepEqual(
        events.filter(event => event.event === 'playback-stall-start').map(event => event.playback_stall_duration_ms),
        [3000, 3000]
    );
    assert.deepEqual(
        events.filter(event => event.event === 'playback-stall-end').map(event => event.playback_stall_end_reason),
        ['pagehide', 'error']
    );
    assert.equal(events.at(-1).playback_stall_open, false);
});

test('resource timing export safely skips unsupported and cross-origin sources', () => {
    const unsupported = harness();
    unsupported.window.performance.getEntriesByName = undefined;
    unsupported.button.fire('click');
    assert.deepEqual(JSON.parse(unsupported.calls.at(-1).body.get('environment')).media_resource_timings, []);

    const crossOrigin = harness();
    crossOrigin.video.currentSrc = 'https://media.example.net/private?nonce=secret';
    crossOrigin.button.fire('click');
    const environment = JSON.parse(crossOrigin.calls.at(-1).body.get('environment'));
    assert.deepEqual(environment.media_resource_timings, []);
    assert.equal(JSON.stringify(environment).includes('media.example.net'), false);
    assert.equal(JSON.stringify(environment).includes('secret'), false);
});

test('standard mode keeps existing enhancement, fullscreen hooks and progress', () => {
    const h = harness('mediaelement');
    assert.equal(h.observed(), 1);
    assert.equal(h.document.listeners.has('fullscreenchange'), true);
    assert.equal(h.wrapper.dataset.amProgressTrackingReady, 'true');
    h.video.paused = false;
    h.video.readyState = 2;
    h.video.fire('waiting');
    assert.equal(h.loader.hidden, false);
    h.video.fire('play');
    assert.equal(h.loader.hidden, true);
    assert.equal(h.playControl.attributes['aria-label'], 'Wstrzymaj');
    h.video.paused = true;
    h.video.fire('pause');
    assert.equal(h.playControl.attributes['aria-label'], 'Odtwórz');
    h.button.fire('click');
    assert.equal(JSON.parse(h.calls.at(-1).body.get('environment')).player_mode, 'mediaelement');
});

test('missing player mode uses native controls and reports the native default', () => {
    const h = harness('');
    assert.equal(h.observed(), 0);
    assert.equal(h.document.listeners.has('fullscreenchange'), false);
    h.button.fire('click');
    assert.equal(JSON.parse(h.calls.at(-1).body.get('environment')).player_mode, 'native');
});

for (const mode of ['native', 'mediaelement']) {
    test(`${mode} emits each shared player event once and announces errors without diagnostics`, () => {
        const h = harness(mode, false);
        for (const eventName of ['play', 'pause', 'ended', 'error']) h.video.fire(eventName);
        assert.deepEqual(h.emitted.map(event => event.type), Array(4).fill('amtoolkit:course-player'));
        assert.deepEqual(h.emitted.map(event => event.detail.event), ['play', 'pause', 'ended', 'error']);
        assert.ok(h.emitted.every(event => event.bubbles
            && event.detail.course === 'course-test' && event.detail.lesson === 'lesson-test'));
        assert.equal(h.status.textContent, 'Nie udało się odtworzyć nagrania. Odśwież stronę lub spróbuj ponownie później.');
        assert.equal(h.loader.hidden, true);
        assert.equal(h.button.listeners.has('click'), false);
        assert.equal(h.calls.length, 0);
        if (mode === 'native') assert.deepEqual(h.playControl.attributes, {});
    });
}
