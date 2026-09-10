(function () {
    'use strict';

    /*
     * TICKETING_CARTABLE_AUTO_FILTER_V1
     *
     * Dropdown filters apply immediately.
     * Text search stays explicit via Enter/search button.
     */

    document.addEventListener(
        'change',
        function (event) {
            var field = event.target;

            if (
                !field
                || !field.matches
                || !field.matches(
                    '[data-ticketing-filter-auto-submit]'
                )
                || field.disabled
            ) {
                return;
            }

            var form =
                field.form
                || field.closest('form');

            if (!form) {
                return;
            }

            if (
                typeof form.requestSubmit
                === 'function'
            ) {
                form.requestSubmit();
                return;
            }

            form.submit();
        },
        true
    );
}());

(function () {
    'use strict';

    /*
     * TICKETING_CARTABLE_AUTO_SEARCH_T3E
     *
     * Text search follows the same automatic-filter UX as My Tickets:
     * - 500 ms debounce while typing
     * - immediate Enter submit
     * - IME/composition safe
     * - native GET form submission
     * - no AJAX
     *
     * Existing select auto-submit behavior above remains untouched.
     */

    var debounceMs = 500;
    var searchTimer = null;
    var composing = false;


    function clearTimer() {
        if (searchTimer === null) {
            return;
        }

        window.clearTimeout(
            searchTimer
        );

        searchTimer = null;
    }


    function searchFieldFromEvent(event) {
        var field =
            event.target;

        if (
            !field
            || !field.matches
            || !field.matches(
                '[data-ticketing-search-auto-submit]'
            )
            || field.disabled
        ) {
            return null;
        }

        return field;
    }


    function submitSearch(field) {
        var form =
            field.form
            || field.closest('form');

        if (!form) {
            return;
        }

        clearTimer();

        if (
            typeof form.requestSubmit
            === 'function'
        ) {
            form.requestSubmit();
            return;
        }

        form.submit();
    }


    function scheduleSearch(field) {
        clearTimer();

        if (composing) {
            return;
        }

        searchTimer =
            window.setTimeout(
                function () {
                    submitSearch(
                        field
                    );
                },
                debounceMs
            );
    }


    document.addEventListener(
        'compositionstart',
        function (event) {
            var field =
                searchFieldFromEvent(
                    event
                );

            if (!field) {
                return;
            }

            composing = true;
            clearTimer();
        },
        true
    );


    document.addEventListener(
        'compositionend',
        function (event) {
            var field =
                searchFieldFromEvent(
                    event
                );

            if (!field) {
                return;
            }

            composing = false;

            scheduleSearch(
                field
            );
        },
        true
    );


    document.addEventListener(
        'input',
        function (event) {
            var field =
                searchFieldFromEvent(
                    event
                );

            if (
                !field
                || composing
            ) {
                return;
            }

            scheduleSearch(
                field
            );
        },
        true
    );


    document.addEventListener(
        'search',
        function (event) {
            var field =
                searchFieldFromEvent(
                    event
                );

            if (!field) {
                return;
            }

            /*
             * Native clear button on input[type=search].
             * Submit immediately after clearing instead of waiting
             * for a second debounce cycle.
             */
            if (field.value === '') {
                submitSearch(
                    field
                );
            }
        },
        true
    );


    document.addEventListener(
        'keydown',
        function (event) {
            var field =
                searchFieldFromEvent(
                    event
                );

            if (
                !field
                || event.key !== 'Enter'
                || event.isComposing
                || composing
            ) {
                return;
            }

            event.preventDefault();

            submitSearch(
                field
            );
        },
        true
    );
}());

/*
 * TICKETING_COMPACT_FILTER_TOGGLE_T3G
 *
 * Shared compact-filter interaction for Staff cartable.
 */
(function () {
    'use strict';

    var form =
        document.querySelector(
            'form.ticketing-staff-filter-grid'
        );

    if (!form) {
        return;
    }

    var toggle =
        form.querySelector(
            '[data-ticketing-advanced-toggle]'
        );

    var panel =
        form.querySelector(
            '[data-ticketing-advanced-panel]'
        );

    if (
        !toggle
        ||
        !panel
    ) {
        return;
    }

    toggle.addEventListener(
        'click',
        function () {
            var open =
                panel.hidden;

            panel.hidden =
                !open;

            toggle.setAttribute(
                'aria-expanded',
                open
                    ? 'true'
                    : 'false'
            );
        }
    );
}());
