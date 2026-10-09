define(['jquery'], function ($) {
    'use strict';
    return function (config, element) {
        var submitted = false;
        $(element).on('submit.coreshQuoteAdmin', function (event) {
            if (submitted) {
                event.preventDefault();
                return;
            }
            submitted = true;
            $(element).find('button[type="submit"]').prop('disabled', true);
        });
    };
});
