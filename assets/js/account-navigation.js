(() => {
    'use strict';

    const selectors = {
        root: '[data-am-account-menu]',
        trigger: '[data-am-account-menu-trigger]',
        panel: '[data-am-account-menu-panel]'
    };

    const openMenus = new Set();
    let positionFrame = 0;

    const positionPanel = (menu) => {
        const { trigger, panel } = menu;

        if (!trigger.isConnected || !panel.isConnected) {
            return;
        }

        const triggerRect = trigger.getBoundingClientRect();
        const viewportPadding = 12;
        const top = Math.min(
            triggerRect.bottom + 10,
            window.innerHeight - viewportPadding
        );

        panel.style.setProperty('--am-account-menu-top', `${Math.round(top)}px`);

        if (window.matchMedia('(max-width: 767px)').matches) {
            panel.style.setProperty('--am-account-menu-right', `${viewportPadding}px`);
            return;
        }

        const right = Math.max(
            viewportPadding,
            window.innerWidth - triggerRect.right
        );

        panel.style.setProperty('--am-account-menu-right', `${Math.round(right)}px`);
    };

    const setOpen = (menu, shouldOpen, returnFocus = false) => {
        menu.root.classList.toggle('is-open', shouldOpen);
        menu.panel.classList.toggle('is-open', shouldOpen);
        menu.trigger.setAttribute('aria-expanded', String(shouldOpen));
        menu.panel.setAttribute('aria-hidden', String(!shouldOpen));

        if (shouldOpen) {
            openMenus.add(menu);
            positionPanel(menu);
        } else {
            openMenus.delete(menu);
        }

        if (returnFocus) {
            menu.trigger.focus();
        }
    };

    const closeAll = (except = null, returnFocus = false) => {
        [...openMenus].forEach((menu) => {
            if (menu !== except) {
                setOpen(menu, false, returnFocus);
            }
        });
    };

    const schedulePosition = () => {
        if (positionFrame) {
            return;
        }

        positionFrame = window.requestAnimationFrame(() => {
            openMenus.forEach(positionPanel);
            positionFrame = 0;
        });
    };

    const initialize = () => {
        document.querySelectorAll(`${selectors.root}:not([data-am-account-menu-ready])`).forEach((root) => {
            const trigger = root.querySelector(selectors.trigger);
            const panel = root.querySelector(selectors.panel);

            if (!trigger || !panel) {
                return;
            }

            root.dataset.amAccountMenuReady = 'true';

            const menu = { root, trigger, panel };

            /*
             * Elementor headers frequently use overflow or transformed containers.
             * Moving the panel to <body> prevents those containers from clipping it.
             */
            document.body.appendChild(panel);

            trigger.addEventListener('click', (event) => {
                event.preventDefault();

                const shouldOpen = trigger.getAttribute('aria-expanded') !== 'true';

                closeAll(menu);
                setOpen(menu, shouldOpen);
            });

            panel.addEventListener('click', (event) => {
                if (event.target.closest('a')) {
                    setOpen(menu, false);
                }
            });

            root.addEventListener('keydown', (event) => {
                if (
                    event.key === 'ArrowDown' &&
                    trigger.getAttribute('aria-expanded') !== 'true'
                ) {
                    event.preventDefault();
                    closeAll(menu);
                    setOpen(menu, true);
                    panel.querySelector('a')?.focus();
                }
            });

            panel.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                event.preventDefault();
                setOpen(menu, false, true);
            });
        });
    };

    document.addEventListener('click', (event) => {
        [...openMenus].forEach((menu) => {
            if (
                !menu.root.contains(event.target) &&
                !menu.panel.contains(event.target)
            ) {
                setOpen(menu, false);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll(null, true);
        }
    });

    window.addEventListener('resize', schedulePosition, { passive: true });
    window.addEventListener('scroll', schedulePosition, {
        passive: true,
        capture: true
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }

    window.addEventListener('elementor/frontend/init', () => {
        initialize();

        if (window.elementorFrontend?.hooks) {
            window.elementorFrontend.hooks.addAction(
                'frontend/element_ready/global',
                initialize
            );
        }
    });
})();
