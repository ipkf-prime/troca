document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');

    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute(
                'aria-expanded',
                open ? 'true' : 'false'
            );
        });
    }

    const slider = document.querySelector('[data-slider]');
    if (!slider) return;

    const slides = [...slider.querySelectorAll('[data-slide]')];
    const dotsHost = slider.querySelector('[data-slider-dots]');

    if (slides.length < 2 || !dotsHost) return;

    let active = 0;
    const dots = slides.map((_, index) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', `اسلاید ${index + 1}`);
        dot.addEventListener('click', () => show(index));
        dotsHost.appendChild(dot);
        return dot;
    });

    function show(index) {
        active = index;
        slides.forEach((slide, i) => {
            slide.classList.toggle('is-active', i === active);
        });
        dots.forEach((dot, i) => {
            dot.classList.toggle('is-active', i === active);
        });
    }

    show(0);

    window.setInterval(() => {
        show((active + 1) % slides.length);
    }, 6500);
});


/* runtime-strip-live-clock-v2 */
(() => {
    'use strict';

    const persianDigits = {
        '0': '۰',
        '1': '۱',
        '2': '۲',
        '3': '۳',
        '4': '۴',
        '5': '۵',
        '6': '۶',
        '7': '۷',
        '8': '۸',
        '9': '۹'
    };

    const toPersianDigits = (value) =>
        String(value).replace(
            /[0-9]/g,
            digit => persianDigits[digit]
        );

    const normalizeRuntimeStrip = () => {
        const strip =
            document.querySelector(
                '.runtime-strip'
            );

        if (!strip) {
            return;
        }

        const walker =
            document.createTreeWalker(
                strip,
                NodeFilter.SHOW_TEXT
            );

        const nodes = [];

        while (walker.nextNode()) {
            nodes.push(
                walker.currentNode
            );
        }

        for (const node of nodes) {
            node.nodeValue =
                toPersianDigits(
                    node.nodeValue
                );
        }
    };

    const runtimeClockFormatter = (
        timeZone
    ) => {
        if (
            !timeZone
            || typeof Intl === 'undefined'
            || typeof Intl.DateTimeFormat
                !== 'function'
        ) {
            return null;
        }

        try {
            return new Intl.DateTimeFormat(
                'en-US-u-ca-persian-nu-latn',
                {
                    timeZone,
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hourCycle: 'h23'
                }
            );
        } catch (error) {
            return null;
        }
    };

    const runtimeClockText = (
        instant,
        formatter
    ) => {
        if (
            !(instant instanceof Date)
            || Number.isNaN(
                instant.getTime()
            )
            || !formatter
            || typeof formatter.formatToParts
                !== 'function'
        ) {
            return null;
        }

        const parts = {};

        formatter
            .formatToParts(instant)
            .forEach(
                part => {
                    if (
                        part.type !==
                        'literal'
                    ) {
                        parts[
                            part.type
                        ] = part.value;
                    }
                }
            );

        const required = [
            'year',
            'month',
            'day',
            'hour',
            'minute',
            'second'
        ];

        if (
            required.some(
                key => !parts[key]
            )
        ) {
            return null;
        }

        return (
            parts.year
            + '/'
            + parts.month
            + '/'
            + parts.day
            + ' | '
            + parts.hour
            + ':'
            + parts.minute
            + ':'
            + parts.second
        );
    };

    const startRuntimeClock = () => {
        const item =
            document.querySelector(
                '[data-runtime-datetime]'
            );

        if (!item) {
            return;
        }

        const serverUtc =
            item.getAttribute(
                'data-runtime-utc'
            )
            || '';

        const timeZone =
            item.getAttribute(
                'data-runtime-timezone'
            )
            || '';

        const serverInstant =
            new Date(serverUtc);

        if (
            Number.isNaN(
                serverInstant.getTime()
            )
        ) {
            return;
        }

        const formatter =
            runtimeClockFormatter(
                timeZone
            );

        if (!formatter) {
            return;
        }

        /*
         * Anchor the live display to the UTC instant
         * rendered by the server. The browser clock is
         * used only to measure elapsed time after render.
         */
        const serverStartMs =
            serverInstant.getTime();

        const clientStartMs =
            Date.now();

        const render = () => {
            const elapsedMs =
                Date.now()
                - clientStartMs;

            const currentInstant =
                new Date(
                    serverStartMs
                    + elapsedMs
                );

            const text =
                runtimeClockText(
                    currentInstant,
                    formatter
                );

            if (text === null) {
                return;
            }

            item.textContent =
                toPersianDigits(
                    text
                );
        };

        render();

        window.setInterval(
            render,
            1000
        );
    };

    const initializeRuntimeStrip = () => {
        normalizeRuntimeStrip();
        startRuntimeClock();
    };

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            initializeRuntimeStrip,
            { once: true }
        );
    } else {
        initializeRuntimeStrip();
    }
})();
