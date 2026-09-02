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
    let clock = 0;
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
    const diagnosticPanel = {
        dataset: {},
        matches: selector => selector === '[data-am-course-diagnostics-panel]',
        querySelector: selector => selector.includes('download') ? button : status
    };
    const video = target({
        currentTime: 0, duration: 169.2, readyState: 4, networkState: 1,
        paused: true, ended: false, seeking: false, playbackRate: 1,
        buffered: {length: 0}, seekable: {length: 0}, error: null
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
        dispatchEvent() {},
        querySelector(selector) {
            if (selector === 'video') return video;
            if (selector === '[data-am-course-player-loader]') return loader;
            if (selector === '[data-am-course-player-status]') return status;
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
    const window = target({
        performance: {now: () => clock},
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
        URL: {createObjectURL: () => 'blob:test', revokeObjectURL() {}},
        setTimeout() {},
        fetch(url, options) {
            calls.push({url, options, body: new URLSearchParams(options.body)});
            return Promise.resolve({json: async () => ({success: true, data: {report: {}, progress: {}}})});
        }
    });
    vm.runInNewContext(source, {
        window, document, URLSearchParams, Blob, Uint8Array, console,
        CustomEvent: class {},
        MutationObserver: class { observe() { observed++; } disconnect() {} }
    });
    return {video, wrapper, loader, button, calls, document,
        tick: ms => { clock += ms; }, observed: () => observed};
}

test('native controls bypass overlays and MediaElement observers but retain progress/resume', async () => {
    const h = harness();
    assert.equal(h.video.currentTime, 12, 'same stored-position logic as standard player');
    assert.equal(h.wrapper.dataset.amProgressTrackingReady, 'true');
    assert.equal(h.observed(), 0);
    assert.equal(h.loader.hidden, true);
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

test('standard mode keeps existing enhancement, fullscreen hooks and progress', () => {
    const h = harness('mediaelement');
    assert.equal(h.observed(), 1);
    assert.equal(h.document.listeners.has('fullscreenchange'), true);
    assert.equal(h.wrapper.dataset.amProgressTrackingReady, 'true');
    h.button.fire('click');
    assert.equal(JSON.parse(h.calls.at(-1).body.get('environment')).player_mode, 'mediaelement');
});

test('ordinary page does not install diagnostic export listeners or send reports', () => {
    const h = harness('mediaelement', false);
    assert.equal(h.button.listeners.has('click'), false);
    assert.equal(h.calls.length, 0);
    assert.equal(h.wrapper.dataset.amProgressTrackingReady, 'true');
});
