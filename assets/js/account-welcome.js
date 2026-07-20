(() => {
    'use strict';

    const config = window.AMTAccountWelcome || {};
    const overlay = document.querySelector('[data-am-account-welcome]');
    const animationContainer = overlay?.querySelector('[data-am-account-welcome-animation]');

    if (!overlay || !animationContainer || !config.storageKey) {
        return;
    }

    const localDate = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const cookieName = config.storageKey.replace(/[^a-zA-Z0-9_-]/g, '_');

    const readLastVisit = () => {
        try {
            return window.localStorage.getItem(config.storageKey) || '';
        } catch (error) {
            const prefix = `${cookieName}=`;
            const cookie = document.cookie
                .split(';')
                .map((item) => item.trim())
                .find((item) => item.startsWith(prefix));
            return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : '';
        }
    };

    const rememberVisit = (date) => {
        try {
            window.localStorage.setItem(config.storageKey, date);
        } catch (error) {
            document.cookie = `${cookieName}=${encodeURIComponent(date)}; max-age=172800; path=/; SameSite=Lax`;
        }
    };

    const today = localDate();
    const preview = config.preview === true;

    if (!preview && readLastVisit() === today) {
        overlay.remove();
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        if (!preview) rememberVisit(today);
        overlay.remove();
        return;
    }

    let closed = false;
    let pathAnimation = null;

    const close = () => {
        if (closed) return;
        closed = true;
        overlay.classList.add('is-leaving');
        window.setTimeout(() => {
            pathAnimation?.cancel();
            overlay.remove();
        }, 420);
    };

    const pathData = (shape) => {
        if (!shape?.v?.length) return '';

        const vertices = shape.v;
        const incoming = shape.i || [];
        const outgoing = shape.o || [];
        let data = `M ${vertices[0][0]} ${vertices[0][1]}`;

        for (let index = 1; index < vertices.length; index++) {
            const previous = vertices[index - 1];
            const current = vertices[index];
            const previousOut = outgoing[index - 1] || [0, 0];
            const currentIn = incoming[index] || [0, 0];
            data += ` C ${previous[0] + previousOut[0]} ${previous[1] + previousOut[1]}`;
            data += ` ${current[0] + currentIn[0]} ${current[1] + currentIn[1]}`;
            data += ` ${current[0]} ${current[1]}`;
        }

        if (shape.c) {
            const previous = vertices[vertices.length - 1];
            const current = vertices[0];
            const previousOut = outgoing[vertices.length - 1] || [0, 0];
            const currentIn = incoming[0] || [0, 0];
            data += ` C ${previous[0] + previousOut[0]} ${previous[1] + previousOut[1]}`;
            data += ` ${current[0] + currentIn[0]} ${current[1] + currentIn[1]}`;
            data += ` ${current[0]} ${current[1]} Z`;
        }

        return data;
    };

    const lastShape = (shapeProperty) => {
        if (!shapeProperty) return null;
        if (shapeProperty.a !== 1) return shapeProperty.k || null;

        const keyframes = shapeProperty.k || [];
        const lastKeyframe = keyframes[keyframes.length - 1];
        return lastKeyframe?.s?.[0] || lastKeyframe?.e?.[0] || null;
    };

    const createVector = (animation) => {
        const layer = animation?.layers?.find((item) => item.ty === 4);
        const group = layer?.shapes?.find((item) => item.ty === 'gr');
        const shapeItem = group?.it?.find((item) => item.ty === 'sh');
        const transform = group?.it?.find((item) => item.ty === 'tr');
        const shape = lastShape(shapeItem?.ks);
        const data = pathData(shape);

        if (!layer || !group || !shape || !data) {
            throw new Error('Unsupported welcome animation structure.');
        }

        const namespace = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(namespace, 'svg');
        const path = document.createElementNS(namespace, 'path');
        const layerPosition = layer.ks?.p?.k || [animation.w / 2, animation.h / 2];
        const layerAnchor = layer.ks?.a?.k || [0, 0];
        const groupPosition = transform?.p?.k || [0, 0];
        const translateX = layerPosition[0] - layerAnchor[0] + groupPosition[0];
        const translateY = layerPosition[1] - layerAnchor[1] + groupPosition[1];

        svg.setAttribute('viewBox', `0 0 ${animation.w || 428} ${animation.h || 123}`);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        svg.setAttribute('focusable', 'false');
        svg.setAttribute('aria-hidden', 'true');
        path.setAttribute('d', data);
        path.setAttribute('transform', `translate(${translateX} ${translateY})`);
        svg.appendChild(path);
        animationContainer.appendChild(svg);
        animationContainer.classList.add('has-vector');

        const length = path.getTotalLength();
        path.style.strokeDasharray = `${length}`;
        path.style.strokeDashoffset = `${length}`;
        pathAnimation = path.animate(
            [
                {strokeDashoffset: length, offset: 0},
                {strokeDashoffset: 0, offset: 0.48},
                {strokeDashoffset: 0, offset: 0.66},
                {strokeDashoffset: -length, offset: 1},
            ],
            {
                duration: 4200,
                delay: 700,
                easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
                fill: 'forwards',
            }
        );

        pathAnimation.finished
            .then(() => window.setTimeout(close, 250))
            .catch(() => {});
    };

    const start = async () => {
        if (!preview) rememberVisit(today);

        overlay.hidden = false;
        window.requestAnimationFrame(() => overlay.classList.add('is-active'));

        try {
            const response = await window.fetch(config.animationUrl, {credentials: 'same-origin'});
            if (!response.ok) throw new Error(`Animation request failed: ${response.status}`);
            createVector(await response.json());
        } catch (error) {
            animationContainer.classList.add('is-fallback');
            window.setTimeout(close, 3000);
        }

        window.setTimeout(close, 6500);
    };

    start();
})();
