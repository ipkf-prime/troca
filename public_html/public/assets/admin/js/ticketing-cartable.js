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
