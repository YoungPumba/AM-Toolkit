(() => {
    'use strict';

    const toolkit = window.AMToolkit = window.AMToolkit || {};
    const recentToasts = toolkit._recentToasts = toolkit._recentToasts || new Map();
    const DEFAULT_DURATION = 4000;
    const VALID_TYPES = new Set(['success', 'info', 'warning', 'error']);

    const icons = {
        success: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.25 4.25L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M12 11v6M12 7.25v.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 4 3.5 19h17L12 4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 9v4.5M12 17v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        error: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="m9 9 6 6m0-6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        close: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
    };

    function getRegion() {
        let region = document.querySelector('.amt-toast-region');

        if (!region) {
            region = document.createElement('div');
            region.className = 'amt-toast-region';
            region.setAttribute('aria-live', 'polite');
            region.setAttribute('aria-relevant', 'additions');
            region.setAttribute('aria-label', 'Powiadomienia');
            document.body.append(region);
        }

        return region;
    }

    function normalizeOptions(options) {
        const input = typeof options === 'string' ? { message: options } : (options || {});
        const type = VALID_TYPES.has(input.type) ? input.type : 'success';
        const suppliedDuration = Number(input.duration);

        return {
            type,
            duration: Number.isFinite(suppliedDuration)
                ? Math.max(1000, suppliedDuration)
                : DEFAULT_DURATION,
            title: String(input.title || 'Powiadomienie'),
            message: String(input.message || ''),
            actionText: input.actionText ? String(input.actionText) : '',
            actionUrl: input.actionUrl ? String(input.actionUrl) : ''
        };
    }

    function buildToast(config) {
        const element = document.createElement('article');
        element.className = `amt-toast amt-toast--${config.type} amt-toast--entering`;
        element.setAttribute('role', config.type === 'error' ? 'alert' : 'status');
        element.setAttribute('aria-atomic', 'true');

        const progressTrack = document.createElement('div');
        progressTrack.className = 'amt-toast__progress-track';
        progressTrack.setAttribute('aria-hidden', 'true');

        const progress = document.createElement('div');
        progress.className = 'amt-toast__progress';
        progressTrack.append(progress);

        const body = document.createElement('div');
        body.className = 'amt-toast__body';

        const icon = document.createElement('div');
        icon.className = 'amt-toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = icons[config.type];

        const content = document.createElement('div');
        content.className = 'amt-toast__content';

        const title = document.createElement('h3');
        title.className = 'amt-toast__title';
        title.textContent = config.title;
        content.append(title);

        if (config.message) {
            const message = document.createElement('p');
            message.className = 'amt-toast__message';
            message.textContent = config.message;
            content.append(message);
        }

        if (config.actionText && config.actionUrl) {
            const action = document.createElement('a');
            action.className = 'amt-toast__action';
            action.href = config.actionUrl;
            action.textContent = config.actionText;
            content.append(action);
        }

        const closeButton = document.createElement('button');
        closeButton.className = 'amt-toast__close';
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', 'Zamknij powiadomienie');
        closeButton.innerHTML = icons.close;

        body.append(icon, content, closeButton);
        element.append(progressTrack, body);

        return { element, progress, closeButton };
    }

    function showToast(options) {
        const config = normalizeOptions(options);
        const signature = JSON.stringify([
            config.type,
            config.title,
            config.message,
            config.actionText,
            config.actionUrl
        ]);
        const duplicate = recentToasts.get(signature);

        if (duplicate?.api?.element?.isConnected) {
            return duplicate.api;
        }

        const toast = buildToast(config);
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let dismissed = false;
        let hovered = false;
        let focused = false;
        let dragging = false;
        let startX = 0;
        let deltaX = 0;
        let api = null;

        getRegion().append(toast.element);

        const progressAnimation = toast.progress.animate(
            [{ transform: 'scaleX(1)' }, { transform: 'scaleX(0)' }],
            { duration: config.duration, easing: 'linear', fill: 'forwards' }
        );

        function updateTimerState() {
            if (hovered || focused || dragging) {
                progressAnimation.pause();
            } else {
                progressAnimation.play();
            }
        }

        function dismiss() {
            if (dismissed) {
                return;
            }

            dismissed = true;
            progressAnimation.cancel();
            toast.element.classList.remove('amt-toast--entering');
            toast.element.classList.add('amt-toast--leaving');

            const remove = () => toast.element.remove();

            if (recentToasts.get(signature)?.api === api) {
                recentToasts.delete(signature);
            }

            if (reduceMotion) {
                remove();
                return;
            }

            toast.element.addEventListener('animationend', remove, { once: true });
            window.setTimeout(remove, 350);
        }

        progressAnimation.addEventListener('finish', dismiss, { once: true });
        toast.closeButton.addEventListener('click', dismiss);

        toast.element.addEventListener('mouseenter', () => {
            hovered = true;
            updateTimerState();
        });

        toast.element.addEventListener('mouseleave', () => {
            hovered = false;
            updateTimerState();
        });

        toast.element.addEventListener('focusin', () => {
            focused = true;
            updateTimerState();
        });

        toast.element.addEventListener('focusout', (event) => {
            if (!toast.element.contains(event.relatedTarget)) {
                focused = false;
                updateTimerState();
            }
        });

        toast.element.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' || event.target.closest('a, button')) {
                return;
            }

            dragging = true;
            startX = event.clientX;
            deltaX = 0;
            toast.element.setPointerCapture(event.pointerId);
            toast.element.style.transition = 'none';
            updateTimerState();
        });

        toast.element.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            deltaX = event.clientX - startX;
            toast.element.style.transform = `translate3d(${deltaX}px, 0, 0)`;
            toast.element.style.opacity = String(Math.max(0.35, 1 - Math.abs(deltaX) / 280));
        });

        toast.element.addEventListener('pointerup', (event) => {
            if (!dragging) {
                return;
            }

            dragging = false;
            toast.element.releasePointerCapture(event.pointerId);

            if (Math.abs(deltaX) > 90) {
                dismiss();
                return;
            }

            toast.element.style.transition = 'transform 180ms ease, opacity 180ms ease';
            toast.element.style.transform = '';
            toast.element.style.opacity = '';
            window.setTimeout(() => {
                toast.element.style.transition = '';
            }, 200);
            updateTimerState();
        });

        api = {
            element: toast.element,
            close: dismiss,
            pause() {
                hovered = true;
                updateTimerState();
            },
            resume() {
                hovered = false;
                updateTimerState();
            }
        };

        recentToasts.set(signature, {api});

        return api;
    }

    toolkit.toast = showToast;
})();
