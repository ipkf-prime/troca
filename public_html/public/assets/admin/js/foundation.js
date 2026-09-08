(function () {
    'use strict';

    window.IPKF = window.IPKF || {};
    window.IPKF.UI = window.IPKF.UI || {};

    var persianDigits = '۰۱۲۳۴۵۶۷۸۹';


    function toEnglishDigits(value) {
        return String(value == null ? '' : value)
            .replace(/[۰-۹]/g, function (digit) {
                return String(
                    persianDigits.indexOf(digit)
                );
            })
            .replace(/[٠-٩]/g, function (digit) {
                return String(
                    '٠١٢٣٤٥٦٧٨٩'.indexOf(digit)
                );
            });
    }


    function toPersianDigits(value) {
        return String(value == null ? '' : value)
            .replace(/[0-9]/g, function (digit) {
                return persianDigits[
                    Number(digit)
                ];
            });
    }


    window.IPKF.UI.toEnglishDigits =
        toEnglishDigits;

    window.IPKF.UI.normalizeDigits =
        toEnglishDigits;

    window.IPKF.UI.toPersianDigits =
        toPersianDigits;


    /*
     * Explicit opt-in contract.
     *
     * Inputs carrying data-ui-normalize-digits may display Persian digits,
     * while their value is normalized immediately before a native form
     * submission enters the application request.
     */
    document.addEventListener(
        'submit',
        function (event) {
            var form = event.target;

            if (
                !form
                || form.nodeName !== 'FORM'
            ) {
                return;
            }

            var fields =
                form.querySelectorAll(
                    '[data-ui-normalize-digits]'
                );

            fields.forEach(
                function (field) {
                    if (
                        typeof field.value
                        !== 'string'
                    ) {
                        return;
                    }

                    field.value =
                        toEnglishDigits(
                            field.value
                        );
                }
            );
        },
        true
    );
}());
