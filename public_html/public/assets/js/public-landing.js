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


/* runtime-strip-persian-digits-v1 */
(() => {
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

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            normalizeRuntimeStrip,
            { once: true }
        );
    } else {
        normalizeRuntimeStrip();
    }
})();
